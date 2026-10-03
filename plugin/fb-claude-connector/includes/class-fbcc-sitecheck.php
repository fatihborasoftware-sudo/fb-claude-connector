<?php
/**
 * Site check (1.1.0) — Claude (and the owner) can check the live site without a browser.
 *
 * Opens each published page and post the way a visitor does and reports:
 *   problems  – page not loading, broken internal links, broken images,
 *               block or shortcode code showing as text, CSS showing as text
 *   warnings  – images without alt text, no or several H1 headings,
 *               blog posts without a featured image, empty <title>
 * The latest result is kept so Claude Connection can show it.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FBCC_SiteCheck {

	const OPT     = 'fbcc_site_check';
	const BUDGET  = 25;   // seconds per run
	const MAX_URL = 40;   // pages per run
	const MAX_REF = 120;  // links + images verified per run

	public static function init() {
		add_filter( 'fbcc_tools', array( __CLASS__, 'tools' ), 8 );
		add_action( 'admin_post_fbcc_site_check', array( __CLASS__, 'action_run' ) );
	}

	public static function tools( $t ) {
		$t['site_check'] = array(
			'title'       => 'Check the live site',
			'description' => 'Open the published pages and posts like a visitor and report problems: pages that do not load, broken internal links and images, block/shortcode/CSS code showing as text, images without alt text, missing or repeated H1, blog posts without a featured image. Use it after building or changing pages, before reporting work as done. Optional "urls" (same site) or "ids" limit the check; default = all published pages and posts (up to 40).',
			'level'       => 'read',
			'kind'        => 'read',
			'schema'      => array(
				'type'                 => 'object',
				'properties'           => (object) array(
					'urls'        => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'ids'         => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
					'check_links' => array( 'type' => 'boolean', 'default' => true, 'description' => 'Also verify internal links and images (slower)' ),
				),
				'additionalProperties' => false,
			),
			'run'         => array( __CLASS__, 'run' ),
		);
		return $t;
	}

	/* ------------------------------------------------------------ */

	private static function host() {
		return wp_parse_url( home_url(), PHP_URL_HOST );
	}

	private static function same_site( $url ) {
		$h = wp_parse_url( $url, PHP_URL_HOST );
		return ! $h || $h === self::host();
	}

	private static function targets( array $a ) {
		$out = array();
		if ( ! empty( $a['ids'] ) ) {
			foreach ( array_slice( array_map( 'intval', (array) $a['ids'] ), 0, self::MAX_URL ) as $id ) {
				if ( 'publish' === get_post_status( $id ) ) {
					$out[ get_permalink( $id ) ] = $id;
				}
			}
		}
		if ( ! empty( $a['urls'] ) ) {
			foreach ( array_slice( (array) $a['urls'], 0, self::MAX_URL ) as $u ) {
				$u = esc_url_raw( (string) $u );
				if ( $u && self::same_site( $u ) ) {
					$out[ $u ] = url_to_postid( $u );
				}
			}
		}
		if ( ! $out ) {
			$out[ home_url( '/' ) ] = (int) get_option( 'page_on_front' );
			$posts = get_posts( array(
				'post_type'      => array( 'page', 'post' ),
				'post_status'    => 'publish',
				'posts_per_page' => self::MAX_URL,
				'orderby'        => array( 'post_type' => 'ASC', 'menu_order' => 'ASC', 'date' => 'DESC' ),
				'fields'         => 'ids',
			) );
			foreach ( $posts as $id ) {
				if ( count( $out ) >= self::MAX_URL ) {
					break;
				}
				$out[ get_permalink( $id ) ] = $id;
			}
		}
		return $out;
	}

	private static function fetch( $url, $head = false ) {
		$args = array(
			'timeout'     => 10,
			'redirection' => 3,
			'user-agent'  => 'FB-Claude-Connector-SiteCheck/' . FBCC_VERSION . '; ' . home_url(),
			'headers'     => array( 'Cache-Control' => 'no-cache' ),
		);
		$r = $head ? wp_remote_head( $url, $args ) : wp_remote_get( $url, $args );
		if ( $head && ! is_wp_error( $r ) && in_array( (int) wp_remote_retrieve_response_code( $r ), array( 403, 405, 501 ), true ) ) {
			$r = wp_remote_get( $url, $args ); // some servers refuse HEAD
		}
		return $r;
	}

	private static function short( $url ) {
		$p = wp_parse_url( $url );
		return ( $p['path'] ?? '/' ) . ( isset( $p['query'] ) ? '?' . $p['query'] : '' );
	}

	/* ------------------------------------------------------------ */

	/** What a visitor would notice: active theme stylesheet, <title>, headings and main text. */
	private static function fingerprint( $html ) {
		$theme = preg_match( '#/themes/([a-z0-9_-]+)/#i', $html, $m ) ? $m[1] : '';
		$title = preg_match( '#<title[^>]*>(.*?)</title>#is', $html, $t ) ? trim( $t[1] ) : '';
		$body  = preg_match( '#<main\b.*?</main>#is', $html, $b ) ? $b[0] : $html;
		$body  = preg_replace( '#<(script|style|noscript)\b.*?</\1>#is', '', $body );
		$text  = preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $body ), ENT_QUOTES, 'UTF-8' ) );
		$text  = preg_replace( '/\b\d{1,2}:\d{2}(:\d{2})?\b|\b[0-9a-f]{10}\b/i', '', $text ); // times and nonces change
		return md5( $theme . '|' . $title . '|' . trim( $text ) );
	}

	public static function run( $a = array() ) {
		$start   = microtime( true );
		$check   = ! isset( $a['check_links'] ) || ! empty( $a['check_links'] );
		$targets = self::targets( (array) $a );
		$issues  = array();
		$refs    = array(); // url => list of pages using it
		$pages   = 0;
		$partial = false;
		$stale   = array();

		$add = function ( $sev, $page, $what, $detail = '' ) use ( &$issues ) {
			if ( count( $issues ) < 300 ) {
				$issues[] = array( 'severity' => $sev, 'page' => $page, 'issue' => $what, 'detail' => $detail );
			}
		};

		foreach ( $targets as $url => $post_id ) {
			if ( microtime( true ) - $start > self::BUDGET ) {
				$partial = true;
				break;
			}
			$pages++;
			$page = self::short( $url );
			// Fresh copy straight from WordPress (a unique query string skips page caches and CDNs).
			$r    = self::fetch( add_query_arg( 'fbcc_check', time() . wp_rand( 100, 999 ), $url ) );
			if ( is_wp_error( $r ) ) {
				$add( 'problem', $page, 'Page did not load', $r->get_error_message() );
				continue;
			}
			$code = (int) wp_remote_retrieve_response_code( $r );
			if ( $code >= 400 ) {
				$add( 'problem', $page, 'Page answers ' . $code );
				continue;
			}
			$html = (string) wp_remote_retrieve_body( $r );

			// What visitors get: if a cache serves something different, say so.
			$pub = self::fetch( $url );
			if ( ! is_wp_error( $pub ) && 200 === (int) wp_remote_retrieve_response_code( $pub ) ) {
				$fresh_fp = self::fingerprint( $html );
				$pub_fp   = self::fingerprint( (string) wp_remote_retrieve_body( $pub ) );
				if ( $fresh_fp !== $pub_fp ) {
					$age = (int) wp_remote_retrieve_header( $pub, 'age' );
					$add( 'problem', $page, 'Visitors see an old cached copy', 'Purge the CDN / page cache' . ( $age ? ' (cached ' . human_time_diff( time() - $age ) . ' ago)' : '' ) );
					$stale[] = $page;
				}
			}

			// Featured image on blog posts (house rule: no post without its banner).
			if ( $post_id && 'post' === get_post_type( $post_id ) && ! has_post_thumbnail( $post_id ) ) {
				$add( 'problem', $page, 'Blog post has no featured image', get_the_title( $post_id ) );
			}

			$doc = new DOMDocument();
			libxml_use_internal_errors( true );
			$doc->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
			libxml_clear_errors();
			$xp = new DOMXPath( $doc );

			$title = $xp->query( '//title' );
			if ( ! $title->length || '' === trim( $title->item( 0 )->textContent ) ) {
				$add( 'warning', $page, 'Empty page <title>' );
			}

			// Only the main content area is judged for headings and leaked code.
			$main = $xp->query( '//main | //*[@id="primary"] | //*[contains(concat(" ",normalize-space(@class)," ")," entry-content ")]' );
			$scope = $main->length ? $main->item( 0 ) : $doc->getElementsByTagName( 'body' )->item( 0 );
			if ( $scope ) {
				$h1 = $xp->query( './/h1', $scope )->length + ( $main->length ? $xp->query( '//header[not(ancestor::main)]//h1' )->length : 0 );
				if ( 0 === $h1 ) {
					$add( 'warning', $page, 'No H1 heading on the page' );
				} elseif ( $h1 > 1 ) {
					$add( 'warning', $page, $h1 . ' H1 headings (use one)' );
				}
				// Text that should never be visible.
				$text = '';
				foreach ( $xp->query( './/text()[not(ancestor::script) and not(ancestor::style) and not(ancestor::code) and not(ancestor::pre) and not(ancestor::textarea) and not(ancestor::noscript)]', $scope ) as $n ) {
					$text .= ' ' . $n->nodeValue;
				}
				$text = preg_replace( '/\s+/u', ' ', $text );
				if ( preg_match( '/<!--\s*\/?wp:[a-z]|\bwp:[a-z-]+\s*\{"/i', $text ) ) {
					$add( 'problem', $page, 'Block code shows as text' );
				}
				if ( preg_match( '/\[(?:[a-z][a-z0-9_-]{2,})(?:\s+[a-z_-]+=(?:"[^"]*"|\'[^\']*\'|\S+))+\s*\/?\]/i', $text, $m ) ) {
					$add( 'problem', $page, 'Shortcode shows as text', $m[0] );
				}
				if ( preg_match( '/[.#][a-z][\w-]*(?:\s+[\w.#-]+)*\s*\{[^{}]{0,200}:[^{}]{0,200};[^{}]*\}/i', $text, $m ) ) {
					$add( 'problem', $page, 'CSS code shows as text', substr( $m[0], 0, 80 ) );
				}
			}

			// Images: alt text + collect for verification.
			foreach ( $xp->query( '//body//img' ) as $img ) {
				$src = $img->getAttribute( 'src' );
				if ( ! $img->hasAttribute( 'alt' ) ) {
					$add( 'warning', $page, 'Image without alt text', wp_basename( (string) wp_parse_url( $src, PHP_URL_PATH ) ) );
				}
				if ( $src && 0 !== strpos( $src, 'data:' ) && self::same_site( $src ) ) {
					$refs[ WP_Http::make_absolute_url( $src, $url ) ][] = $page;
				}
			}
			// Internal links.
			foreach ( $xp->query( '//body//a[@href]' ) as $a_el ) {
				$href = trim( $a_el->getAttribute( 'href' ) );
				if ( '' === $href || '#' === $href[0] || preg_match( '/^(mailto|tel|javascript):/i', $href ) ) {
					continue;
				}
				$abs = WP_Http::make_absolute_url( $href, $url );
				if ( ! self::same_site( $abs ) || false !== strpos( $abs, '/wp-admin' ) || false !== strpos( $abs, '/wp-login' ) || false !== strpos( $abs, '/feed' ) ) {
					continue;
				}
				$abs = strtok( $abs, '#' );
				if ( '' === trim( $a_el->textContent ) && ! $xp->query( './/img', $a_el )->length && ! $a_el->hasAttribute( 'aria-label' ) ) {
					$add( 'warning', $page, 'Link without text', self::short( $abs ) );
				}
				$refs[ $abs ][] = $page;
			}
		}

		// Verify each internal link / image once.
		$checked = 0;
		if ( $check ) {
			foreach ( $refs as $ref => $on ) {
				if ( isset( $targets[ $ref ] ) ) {
					continue; // already loaded above
				}
				if ( $checked >= self::MAX_REF || microtime( true ) - $start > self::BUDGET ) {
					$partial = true;
					break;
				}
				$checked++;
				$r    = self::fetch( $ref, true );
				$code = is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
				if ( 0 === $code || $code >= 400 ) {
					$on = array_values( array_unique( $on ) );
					$add( 'problem', $on[0], ( preg_match( '/\.(jpe?g|png|gif|webp|svg|avif)(\?|$)/i', $ref ) ? 'Broken image' : 'Broken link' ) . ( $code ? ' (' . $code . ')' : '' ), self::short( $ref ) . ( count( $on ) > 1 ? ' — also on ' . ( count( $on ) - 1 ) . ' more page(s)' : '' ) );
				}
			}
		}

		$problems = count( array_filter( $issues, function ( $i ) { return 'problem' === $i['severity']; } ) );
		$result   = array(
			'_summary'       => 'Site check: ' . $pages . ' page(s), ' . $problems . ' problem(s), ' . ( count( $issues ) - $problems ) . ' warning(s)' . ( $partial ? ' (partial)' : '' ),
			'pages_checked'  => $pages,
			'links_checked'  => $checked,
			'problems'       => $problems,
			'warnings'       => count( $issues ) - $problems,
			'partial'        => $partial,
			'seconds'        => round( microtime( true ) - $start, 1 ),
			'issues'         => $issues,
			'stale_cache'    => $stale,
			'message'        => ( $stale ? 'Visitors get old cached copies of ' . count( $stale ) . ' page(s): the owner must purge the CDN / page cache (Hostinger: hPanel → Websites → CDN → Purge cache). ' : '' ) . ( $problems || $issues ? 'Fix the problems, then run site_check again.' : 'All clear.' ),
		);
		update_option( self::OPT, array_merge( array_diff_key( $result, array( '_summary' => 1 ) ), array( 'time' => time() ) ), false );
		return $result;
	}

	/* ------------------------------------------------------------ */
	/* Overview card                                                */
	/* ------------------------------------------------------------ */

	public static function action_run() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		check_admin_referer( 'fbcc_site_check' );
		$r = self::run( array() );
		FBCC_Store::log( 'site_check', $r['_summary'] . ' — run by ' . wp_get_current_user()->display_name, $r['problems'] ? 'error' : 'done' );
		wp_safe_redirect( admin_url( 'admin.php?page=fbcc#fbcc-site-check' ) );
		exit;
	}

	public static function card() {
		$last = get_option( self::OPT );
		echo '<section class="fbcc-card" id="fbcc-site-check"><div class="fbcc-head"><h2>Site check</h2>';
		if ( is_array( $last ) ) {
			echo '<span class="fbcc-pill ' . ( $last['problems'] ? 'fbcc-red' : 'fbcc-green' ) . '">' . (int) $last['problems'] . ' / ' . (int) $last['warnings'] . '</span>';
		}
		echo '</div><p class="fbcc-muted">Checks every published page and post: broken links and images, missing alt text, missing featured images, code or shortcodes showing as text, and page titles.</p>';
		if ( is_array( $last ) ) {
			echo '<dl class="fbcc-dl"><dt>Last check</dt><dd>' . esc_html( wp_date( 'j M H:i', (int) $last['time'] ) ) . '</dd>';
			echo '<dt>Pages checked</dt><dd>' . (int) $last['pages_checked'] . '</dd>';
			echo '<dt>Problems</dt><dd>' . (int) $last['problems'] . '</dd><dt>Warnings</dt><dd>' . (int) $last['warnings'] . '</dd></dl>';
			if ( $last['issues'] ) {
				echo '<table class="widefat striped"><thead><tr><th>Page</th><th>Issue</th><th>Details</th></tr></thead><tbody>';
				foreach ( array_slice( $last['issues'], 0, 25 ) as $i ) {
					echo '<tr><td><code>' . esc_html( $i['page'] ) . '</code></td><td' . ( 'problem' === $i['severity'] ? ' class="fbcc-t-red"' : '' ) . '>' . esc_html( $i['issue'] ) . '</td><td>' . esc_html( $i['detail'] ) . '</td></tr>';
				}
				echo '</tbody></table>';
			} else {
				echo '<p class="fbcc-t-green">All clear — nothing to fix.</p>';
			}
		} else {
			echo '<p class="fbcc-muted">No check yet.</p>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="fbcc_site_check">';
		wp_nonce_field( 'fbcc_site_check' );
		echo '<p><button class="button">Run site check</button></p></form></section>';
	}
}
