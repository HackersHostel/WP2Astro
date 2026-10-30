# Mock object storage on :9998 for the media "Upload to storage" option. Speaks the S3 PUT API (path style,
# /{bucket}/{key}) and checks each AWS Signature V4 with its own implementation (key id test-key, secret
# test-secret), and Bunny Storage's PUT /{zone}/{key} with an AccessKey header (test-secret).
# Public URL of a bucket or zone: http://127.0.0.1:9998/{bucket}. Uploads are written to dev-out/storage/.
import hashlib, hmac, os
from http.server import BaseHTTPRequestHandler, HTTPServer
from urllib.parse import unquote

KEY_ID, SECRET, OUT = 'test-key', 'test-secret', './dev-out/storage'
FILES = {}

def sign(method, path, headers, signed, amz_date, payload):
    canon = '\n'.join([method, path, '', ''.join(f'{h}:{headers[h].strip()}\n' for h in signed), ';'.join(signed), payload])
    day = amz_date[:8]; scope = f'{day}/{headers.get("_region")}/s3/aws4_request'
    to_sign = '\n'.join(['AWS4-HMAC-SHA256', amz_date, scope, hashlib.sha256(canon.encode()).hexdigest()])
    k = ('AWS4' + SECRET).encode()
    for part in (day, headers.get('_region'), 's3', 'aws4_request'): k = hmac.new(k, part.encode(), hashlib.sha256).digest()
    return hmac.new(k, to_sign.encode(), hashlib.sha256).hexdigest()

class H(BaseHTTPRequestHandler):
    def reply(self, code, body=b'', ctype='text/plain'):
        self.send_response(code); self.send_header('Content-Type', ctype); self.send_header('Content-Length', str(len(body))); self.end_headers(); self.wfile.write(body)
    def do_PUT(self):
        data = self.rfile.read(int(self.headers.get('Content-Length', 0)))
        if 'AccessKey' in self.headers:
            if self.headers['AccessKey'] != SECRET: return self.reply(401, b'Unauthorized')
            if self.headers.get('Checksum', '').lower() not in ('', hashlib.sha256(data).hexdigest()): return self.reply(400, b'Checksum mismatch')
        else:
            auth = self.headers.get('Authorization', '')
            try:
                cred = auth.split('Credential=')[1].split(',')[0]; signed = auth.split('SignedHeaders=')[1].split(',')[0].split(';')
                given = auth.split('Signature=')[1]
                key_id, day, region = cred.split('/')[:3]
            except IndexError:
                return self.reply(403, b'<Error><Message>Missing signature</Message></Error>')
            h = {k.lower(): v for k, v in self.headers.items()}; h['_region'] = region
            if key_id != KEY_ID: return self.reply(403, b'<Error><Message>InvalidAccessKeyId</Message></Error>')
            if hashlib.sha256(data).hexdigest() != h.get('x-amz-content-sha256'): return self.reply(400, b'<Error><Message>XAmzContentSHA256Mismatch</Message></Error>')
            if sign('PUT', self.path, h, signed, h['x-amz-date'], h['x-amz-content-sha256']) != given:
                return self.reply(403, b'<Error><Message>SignatureDoesNotMatch</Message></Error>')
        FILES[unquote(self.path)] = (data, self.headers.get('Content-Type', 'application/octet-stream'))
        f = os.path.join(OUT, unquote(self.path).lstrip('/')); os.makedirs(os.path.dirname(f), exist_ok=True); open(f, 'wb').write(data)
        self.reply(200)
    def do_GET(self):
        hit = FILES.get(unquote(self.path.split('?')[0]))
        if not hit: return self.reply(404, b'Not found')
        self.reply(200, hit[0], hit[1])
    def log_message(self, *a): pass

HTTPServer(('127.0.0.1', 9998), H).serve_forever()
