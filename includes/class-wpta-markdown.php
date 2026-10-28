<?php
defined( 'ABSPATH' ) || exit;

/**
 * Small HTML → Markdown converter for rendered WordPress content.
 * Anything without a clean Markdown equivalent (tables, embeds, forms…) is kept as raw HTML
 * inside a <div> block so CommonMark passes it through untouched.
 */
class WPTA_Markdown {

	const RAW_TAGS  = array( 'table', 'iframe', 'video', 'audio', 'form', 'embed', 'object', 'svg', 'canvas', 'details', 'dl', 'select', 'picture', 'math' );
	const DROP_TAGS = array( 'script', 'style', 'noscript', 'template', 'link', 'meta' );
	const BLOCKS    = array( 'div', 'section', 'article', 'header', 'footer', 'main', 'aside', 'nav', 'center', 'body' );

	public static function convert( string $html ): string {
		$html = trim( $html );
		if ( '' === $html ) {
			return '';
		}
		$doc  = new DOMDocument();
		$prev = libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8" ?><html><body><div id="wpta-root">' . $html . '</div></body></html>' );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		$root = $doc->getElementById( 'wpta-root' );
		if ( ! $root ) {
			return $html;
		}
		$md = self::children( $root, $doc );
		return self::tidy( $md );
	}

	/** @param bool $final Convert hard-break markers (only on the outermost pass). */
	private static function tidy( string $md, bool $final = true ): string {
		$md = preg_replace( "/[ \t]+\n/", "\n", $md );
		if ( $final ) {
			$md = str_replace( "\x01", "  \n", $md );
		}
		$md = preg_replace( "/\n{3,}/", "\n\n", $md );
		return trim( $md ) . "\n";
	}

	private static function children( DOMNode $node, DOMDocument $doc, bool $pre = false ): string {
		$out = '';
		foreach ( $node->childNodes as $child ) {
			$out .= self::node( $child, $doc, $pre );
		}
		return $out;
	}

	private static function node( DOMNode $n, DOMDocument $doc, bool $pre = false ): string {
		if ( XML_TEXT_NODE === $n->nodeType || XML_CDATA_SECTION_NODE === $n->nodeType ) {
			return $pre ? $n->nodeValue : self::text( $n->nodeValue );
		}
		if ( XML_ELEMENT_NODE !== $n->nodeType ) {
			return '';
		}
		/** @var DOMElement $n */
		$tag = strtolower( $n->nodeName );

		if ( in_array( $tag, self::DROP_TAGS, true ) ) {
			return '';
		}
		if ( in_array( $tag, self::RAW_TAGS, true ) ) {
			return self::raw( $n, $doc );
		}
		if ( in_array( $tag, self::BLOCKS, true ) ) {
			return "\n\n" . self::children( $n, $doc ) . "\n\n";
		}

		switch ( $tag ) {
			case 'p':
				$inner = trim( self::children( $n, $doc ) );
				if ( '' === $inner ) {
					return '';
				}
				// Don't let paragraph text turn into a heading/list/quote by accident.
				$inner = preg_replace( '/^(#{1,6}\s|>|[-+*]\s|\d+[.)]\s)/', '\\\\$1', $inner );
				return "\n\n" . $inner . "\n\n";

			case 'h1':
			case 'h2':
			case 'h3':
			case 'h4':
			case 'h5':
			case 'h6':
				$inner = trim( preg_replace( '/[\s\x01]+/', ' ', self::children( $n, $doc ) ) );
				return '' === $inner ? '' : "\n\n" . str_repeat( '#', (int) $tag[1] ) . ' ' . $inner . "\n\n";

			case 'strong':
			case 'b':
				return self::wrap( self::children( $n, $doc ), '**' );

			case 'em':
			case 'i':
				return self::wrap( self::children( $n, $doc ), '*' );

			case 'del':
			case 's':
			case 'strike':
				return self::wrap( self::children( $n, $doc ), '~~' );

			case 'code':
				if ( $pre ) {
					return $n->textContent;
				}
				$t = $n->textContent;
				$f = false !== strpos( $t, '`' ) ? '`` ' : '`';
				return $f . $t . strrev( $f );

			case 'pre':
				$lang = '';
				$code = $n->getElementsByTagName( 'code' )->item( 0 );
				foreach ( array( $n, $code ) as $el ) {
					if ( $el instanceof DOMElement && preg_match( '/(?:language|lang)-([\w+-]+)/', $el->getAttribute( 'class' ), $m ) ) {
						$lang = $m[1];
					}
				}
				$body  = rtrim( $n->textContent, "\n" );
				$fence = false !== strpos( $body, '```' ) ? '~~~~' : '```';
				return "\n\n" . $fence . $lang . "\n" . $body . "\n" . $fence . "\n\n";

			case 'a':
				$inner = self::children( $n, $doc );
				$href  = trim( $n->getAttribute( 'href' ) );
				if ( '' === $href || '' === trim( $inner ) ) {
					return $inner;
				}
				return '[' . trim( $inner ) . '](' . self::url( $href ) . ')';

			case 'img':
				$src = trim( $n->getAttribute( 'src' ) );
				if ( '' === $src ) {
					return '';
				}
				$alt = str_replace( array( '[', ']' ), '', $n->getAttribute( 'alt' ) );
				return '![' . $alt . '](' . self::url( $src ) . ')';

			case 'br':
				return "\x01";

			case 'hr':
				return "\n\n---\n\n";

			case 'ul':
			case 'ol':
				return "\n\n" . self::list_items( $n, $doc, 'ol' === $tag ) . "\n\n";

			case 'li':
				// Stray <li> outside a list.
				return "\n\n- " . trim( self::children( $n, $doc ) ) . "\n\n";

			case 'blockquote':
				$inner = trim( self::tidy( self::children( $n, $doc ), false ) );
				$lines = explode( "\n", $inner );
				return "\n\n" . implode( "\n", array_map( fn( $l ) => rtrim( '> ' . $l ), $lines ) ) . "\n\n";

			case 'figure':
				return "\n\n" . self::children( $n, $doc ) . "\n\n";

			case 'figcaption':
				$inner = trim( self::children( $n, $doc ) );
				return '' === $inner ? '' : "\n\n*" . $inner . "*\n\n";

			default:
				// span, small, sup, sub, abbr, mark, cite, time, label, u … → keep text.
				return self::children( $n, $doc );
		}
	}

	private static function list_items( DOMElement $list, DOMDocument $doc, bool $ordered ): string {
		$items = array();
		$i     = (int) ( $list->getAttribute( 'start' ) ?: 1 );
		foreach ( $list->childNodes as $li ) {
			if ( ! ( $li instanceof DOMElement ) || 'li' !== strtolower( $li->nodeName ) ) {
				continue;
			}
			$marker  = $ordered ? ( $i++ ) . '. ' : '- ';
			$content = trim( self::tidy( self::children( $li, $doc ), false ) );
			// Tighten "text\n\n- nested" into "text\n- nested".
			$content = preg_replace( "/\n\n(?=\s*(?:[-*+]|\d+\.) )/", "\n", $content );
			$pad     = str_repeat( ' ', strlen( $marker ) );
			$lines   = explode( "\n", $content );
			foreach ( $lines as $k => $line ) {
				$lines[ $k ] = 0 === $k ? $marker . $line : ( '' === $line ? '' : $pad . $line );
			}
			$items[] = implode( "\n", $lines );
		}
		return implode( "\n", $items );
	}

	/** Raw HTML block: wrapped in a div, no blank lines, so CommonMark keeps it as one HTML block. */
	private static function raw( DOMElement $n, DOMDocument $doc ): string {
		$html = $doc->saveHTML( $n );
		$html = preg_replace( "/\n\s*\n/", "\n", $html );
		$html = str_replace( "\x01", '', $html );
		return "\n\n<div class=\"wp-raw\">\n" . trim( $html ) . "\n</div>\n\n";
	}

	private static function text( string $t ): string {
		$t = preg_replace( '/\s+/u', ' ', $t );
		$t = str_replace( array( '\\', '*', '`', '<' ), array( '\\\\', '\\*', '\\`', '&lt;' ), $t );
		return $t;
	}

	private static function wrap( string $inner, string $mark ): string {
		if ( '' === trim( $inner ) ) {
			return $inner;
		}
		// Keep surrounding spaces outside the markers, or CommonMark won't parse them.
		preg_match( '/^(\s*)(.*?)(\s*)$/s', $inner, $m );
		return $m[1] . $mark . $m[2] . $mark . $m[3];
	}

	private static function url( string $url ): string {
		$url = str_replace( array( ' ', '(', ')' ), array( '%20', '%28', '%29' ), $url );
		return $url;
	}
}
