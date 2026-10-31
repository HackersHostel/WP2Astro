"""Export every ink of the WA logo as flat SVG + PNG.

The mark: a W whose last stroke becomes the A, set as high-contrast capitals
(thick strokes going down, thin strokes going up), with flat cuts at cap line and baseline.
64 grid, tile corner 14 (favicon 13). Flat = one solid fill per shape, no gradients or opacity.

Usage: python export_inks.py OUT_DIR   (needs Playwright with Chromium)
"""
import base64, json, pathlib, re, sys
from playwright.sync_api import sync_playwright

OUT = pathlib.Path(sys.argv[1])
NAME, LABEL = "wa", "WA"

ACCENTS = {"violet": "#6e56cf", "blue": "#2b62d9", "teal": "#0f766e", "ember": "#c2410c", "ink": "#18181b"}
GLYPHS = {  # name: (ink, made for)
    "dark": "#18181b",        # white / light backgrounds
    "light": "#ededf0",       # dark backgrounds
    "teal": "#0f766e",        # white / light backgrounds
    "teal-dark": "#2dd4bf",   # dark backgrounds
}

MARK = "M32.34 47.38 L39.44 47.38 L38.46 43.51 L39.50 39.38 L45.88 39.38 L47.91 47.38 L55.00 47.38 L47.22 16.62 L40.13 16.62 L41.11 20.49 L36.87 37.23 L31.66 16.62 L24.56 16.62 L25.54 20.49 L21.31 37.23 L16.09 16.62 L9.00 16.62 L16.78 47.38 L23.87 47.38 L22.89 43.51 L27.13 26.77ZM42.69 26.77 L45.11 36.31 L40.28 36.31Z"
BUBBLE = "M17 3h30a14 14 0 0 1 14 14v23a14 14 0 0 1-14 14H31.5l-11.2 8.2a1.2 1.2 0 0 1-1.9-1V54H17A14 14 0 0 1 3 40V17A14 14 0 0 1 17 3Z"
GLYPH_VB = "7 7 50 50"


def glyph(c):
    return f'<path d="{MARK}" fill="{c}" fill-rule="evenodd"/>'


def svg(vb, body, label=LABEL):
    return f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="{vb}" role="img" aria-label="{label}">{body}</svg>\n'


def build():
    files = {}
    g = glyph("#ffffff")
    for name, ac in ACCENTS.items():
        files[f"{name}/{NAME}-mark-{name}"] = (svg("0 0 64 64", f'<rect width="64" height="64" rx="14" fill="{ac}"/>{g}'), "square")
        files[f"{name}/{NAME}-favicon-{name}"] = (svg("0 0 64 64", f'<rect width="64" height="64" rx="13" fill="{ac}"/>{g}'), "favicon")
        files[f"{name}/{NAME}-round-{name}"] = (svg("0 0 64 64", f'<circle cx="32" cy="32" r="32" fill="{ac}"/><g transform="translate(4.8 4.8) scale(.85)">{g}</g>'), "square")
        files[f"{name}/{NAME}-maskable-{name}"] = (svg("0 0 64 64", f'<rect width="64" height="64" fill="{ac}"/><g transform="translate(9.6 9.6) scale(.7)">{g}</g>'), "square")
        files[f"{name}/{NAME}-bubble-{name}"] = (svg("0 0 64 64", f'<path d="{BUBBLE}" fill="{ac}"/><g transform="translate(5.76 1.86) scale(.82)">{g}</g>', f"{LABEL}, chat bubble variant"), "square")
    for name, ink in GLYPHS.items():
        files[f"glyph/{NAME}-glyph-{name}"] = (svg(GLYPH_VB, glyph(ink)), "square")
    return files


PNG = {"square": [64, 128, 256, 512, 1024], "favicon": [16, 32, 48, 64, 180, 192, 512]}

files = build()
report = {}
with sync_playwright() as p:
    br = p.chromium.launch()
    pg = br.new_page()
    for path, (s, kind) in files.items():
        f = OUT / "svg" / f"{path}.svg"
        f.parent.mkdir(parents=True, exist_ok=True)
        f.write_text(s)
        report[path] = sorted(set(re.findall(r"#[0-9a-f]{6}", s)))
        for size in PNG[kind]:
            pg.set_viewport_size({"width": size, "height": size})
            pg.set_content(f'<html><body style="margin:0;background:transparent">'
                           f'<img src="data:image/svg+xml;base64,{base64.b64encode(s.encode()).decode()}" width="{size}" height="{size}" style="display:block"></body></html>')
            pg.wait_for_function("document.images[0].complete")
            o = OUT / "png" / f"{path}-{size}.png"
            o.parent.mkdir(parents=True, exist_ok=True)
            pg.locator("img").screenshot(path=str(o), omit_background=True)
    br.close()

(OUT / "colours.json").write_text(json.dumps(report, indent=1))
print(len(files), "svgs")
