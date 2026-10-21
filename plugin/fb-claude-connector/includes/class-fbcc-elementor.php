<?php
/**
 * Full build control (1.3.0) — what Claude could not do without a browser:
 *   page_html          read the page visitors get (or a CSS/JS file of this site)
 *   elementor_get      read a page's Elementor widgets (or just an outline)
 *   elementor_set      write a page's Elementor widgets: whole page, a template, or small patches
 *   elementor_kit_get  read the Elementor Site Settings (global colours, fonts, buttons)
 *   elementor_kit_set  change the Elementor Site Settings
 *   snippets_list / snippet_get   read Code Snippets (read-only)
 * Published pages and Site Settings still go to approvals unless the build plan covers them.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FBCC_Elementor {

	const BACKUP = '_fbcc_elementor_backup';
	const KEEP   = 5;

	public static function init() {
		add_filter( 'fbcc_tools', array( __CLASS__, 'tools' ), 8 );
		add_action( 'admin_post_fbcc_elementor_restore', array( __CLASS__, 'restore' ) );
	}

	private static function obj( array $props, array $required = array() ) {
		$s = array( 'type' => 'object', 'properties' => (object) $props, 'additionalProperties' => false );
		if ( $required ) {
			$s['required'] = $required;
		}
		return $s;
	}

	public static function tools( $t ) {
		$t['page_html'] = array(
			'title'       => 'Read what visitors get',
			'description' => 'Fetch a page of this site the way a visitor gets it (rendered HTML after Elementor, shortcodes, snippets and plugins), or a CSS/JS file of this site. Give a page/post id or a URL on this site. Use "find" to get only the parts around a text or class name (e.g. "wb-bmodal" or "Randevu talebiniz"); otherwise the HTML comes in slices (offset/length). Also lists the stylesheets and scripts the page loads, so you can read them with this tool too.',
			'level'       => 'read',
			'kind'        => 'read',
			'schema'      => self::obj( array(
				'id'      => array( 'type' => 'integer', 'description' => 'Page or post id' ),
				'url'     => array( 'type' => 'string', 'description' => 'A URL on this site (page, .css or .js file)' ),
				'find'    => array( 'type' => 'string', 'description' => 'Only return the parts around this text (case-insensitive), up to 20 matches' ),
				'context' => array( 'type' => 'integer', 'minimum' => 50, 'maximum' => 5000, 'default' => 600, 'description' => 'Characters around each match' ),
				'offset'  => array( 'type' => 'integer', 'minimum' => 0, 'default' => 0 ),
				'length'  => array( 'type' => 'integer', 'minimum' => 1000, 'maximum' => 200000, 'default' => 60000 ),
			) ),
			'run'         => array( __CLASS__, 'page_html' ),
		);

		$t['page_screenshot'] = array(
			'title'       => 'See a page',
			'description' => 'A screenshot of a published page of this site, as a visitor sees it on a phone (device "mobile", 390 px wide, the default), a tablet or a desktop. Use it to check your work by eye after a change. The picture is made by the free WordPress.com mShots service, so only public pages work and a new picture can take up to half a minute; if "pending" comes back, call again shortly.',
			'level'       => 'read',
			'kind'        => 'read',
			'schema'      => self::obj( array(
				'id'     => array( 'type' => 'integer', 'description' => 'Page or post id' ),
				'url'    => array( 'type' => 'string', 'description' => 'A URL on this site' ),
				'device' => array( 'type' => 'string', 'enum' => array( 'mobile', 'tablet', 'desktop' ), 'default' => 'mobile' ),
				'height' => array( 'type' => 'integer', 'minimum' => 400, 'maximum' => 3000, 'description' => 'How much of the page to show, in px from the top (default one screen)' ),
			) ),
			'run'         => array( __CLASS__, 'page_screenshot' ),
		);

		$t['elementor_get'] = array(
			'title'       => 'Read Elementor widgets',
			'description' => 'The Elementor content of a page or post: page settings and the element tree (containers, sections, widgets with their settings). outline=true returns only ids, types and a short label per element — read that first on big pages, then fetch one element with "element". Use the ids with elementor_set patches.',
			'level'       => 'read',
			'kind'        => 'read',
			'schema'      => self::obj( array(
				'id'      => array( 'type' => 'integer' ),
				'outline' => array( 'type' => 'boolean', 'default' => false ),
				'element' => array( 'type' => 'string', 'description' => 'Return only this element (and its children)' ),
			), array( 'id' ) ),
			'run'         => array( __CLASS__, 'get' ),
		);

		$t['elementor_set'] = array(
			'title'       => 'Write Elementor widgets',
			'description' => 'Build or change a page with native Elementor widgets (the owner edits them later in Elementor). Give ONE of: "elements" (the whole element tree, as in _elementor_data), "template" (an Elementor template export: {"content":[...],"page_settings":{...}}) or "patch" (small changes by element id: {"op":"set","id":..,"settings":{..}} merges settings, {"op":"delete","id":..}, {"op":"insert_after","id":..,"element":{..}}, {"op":"append","id":..,"element":{..}} adds a child; id "" with append adds to the page end). "page_settings" (e.g. {"hide_title":"yes","template":"elementor_header_footer"}) is merged in. Missing element ids are created. The previous version is kept for undo. Drafts change at once; a PUBLISHED page goes to approval unless the build plan covers it.',
			'level'       => 'editor',
			'kind'        => 'write',
			'schema'      => self::obj( array(
				'id'            => array( 'type' => 'integer' ),
				'elements'      => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
				'template'      => array( 'type' => 'object' ),
				'patch'         => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
				'page_settings' => array( 'type' => 'object' ),
				'reason'        => array( 'type' => 'string', 'description' => 'Why, in one sentence — shown to the owner' ),
			), array( 'id', 'reason' ) ),
			'run'         => array( __CLASS__, 'set' ),
			'execute'     => array( __CLASS__, 'set_execute' ),
		);

		$t['elementor_kit_get'] = array(
			'title'       => 'Read Elementor Site Settings',
			'description' => 'The active Elementor kit (Site Settings): global colours (system_colors, custom_colors), global fonts (system_typography, custom_typography), and the theme style settings for body, headings, links, buttons, forms and images. keys = only these settings.',
			'level'       => 'read',
			'kind'        => 'read',
			'schema'      => self::obj( array(
				'keys' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			) ),
			'run'         => array( __CLASS__, 'kit_get' ),
		);

		$t['elementor_kit_set'] = array(
			'title'       => 'Change Elementor Site Settings',
			'description' => 'Change the Elementor kit (Site Settings): give the settings to change, with the same keys elementor_kit_get returns (e.g. system_colors, system_typography, button_background_color, button_text_color, body_typography_font_family). Each key you give replaces that key; other keys stay. Elementor CSS is regenerated. The previous settings are kept for undo. Runs at once when the active build plan includes the theme; otherwise goes to approvals.',
			'level'       => 'editor',
			'kind'        => 'approval',
			'schema'      => self::obj( array(
				'settings' => array( 'type' => 'object' ),
				'reason'   => array( 'type' => 'string' ),
			), array( 'settings', 'reason' ) ),
			'prepare'     => array( __CLASS__, 'kit_prepare' ),
			'execute'     => array( __CLASS__, 'kit_execute' ),
		);

		$t['snippets_list'] = array(
			'title'       => 'List code snippets',
			'description' => 'Snippets of the Code Snippets plugin: id, name, type (php, html, css, js), scope, active and priority. Read one with snippet_get.',
			'level'       => 'editor',
			'kind'        => 'read',
			'schema'      => self::obj( array(
				'search' => array( 'type' => 'string', 'description' => 'Text in the name, description or code' ),
			) ),
			'run'         => array( __CLASS__, 'snippets_list' ),
		);

		$t['snippet_get'] = array(
			'title'       => 'Read a code snippet',
			'description' => 'The full code and settings of one Code Snippets snippet.',
			'level'       => 'editor',
			'kind'        => 'read',
			'schema'      => self::obj( array( 'id' => array( 'type' => 'integer' ) ), array( 'id' ) ),
			'run'         => array( __CLASS__, 'snippet_get' ),
		);

		return $t;
	}

	/* -- helpers -------------------------------------------------------- */

	private static function ready() {
		if ( ! did_action( 'elementor/loaded' ) || ! class_exists( '\Elementor\Plugin' ) ) {
			return new WP_Error( 'no_elementor', 'Elementor is not active on this site.' );
		}
		return true;
	}

	private static function document( $id ) {
		$ok = self::ready();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$p = get_post( (int) $id );
		if ( ! $p || ! in_array( $p->post_type, array( 'page', 'post', 'elementor_library' ), true ) ) {
			return new WP_Error( 'not_found', 'No page, post or Elementor template with id ' . (int) $id . '.' );
		}
		$doc = \Elementor\Plugin::$instance->documents->get( $p->ID, false );
		if ( ! $doc ) {
			return new WP_Error( 'not_elementor', 'Elementor cannot open this item.' );
		}
		return $doc;
	}

	private static function elements_of( $doc ) {
		$raw = get_post_meta( $doc->get_main_id(), '_elementor_data', true );
		$els = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
		return is_array( $els ) ? $els : array();
	}

	private static function settings_of( $id ) {
		$s = get_post_meta( (int) $id, '_elementor_page_settings', true );
		return is_array( $s ) ? $s : array();
	}

	private static function new_id() {
		return substr( md5( uniqid( '', true ) . wp_rand() ), 0, 7 );
	}

	/** Gives every element an id and the keys Elementor expects. */
	private static function normalize( array $els ) {
		$out = array();
		foreach ( $els as $e ) {
			if ( ! is_array( $e ) || empty( $e['elType'] ) ) {
				continue;
			}
			$e['id']       = ! empty( $e['id'] ) ? preg_replace( '/[^a-z0-9]/i', '', (string) $e['id'] ) : self::new_id();
			$e['settings'] = isset( $e['settings'] ) && is_array( $e['settings'] ) ? $e['settings'] : array();
			$e['elements'] = self::normalize( isset( $e['elements'] ) && is_array( $e['elements'] ) ? $e['elements'] : array() );
			if ( 'widget' === $e['elType'] && empty( $e['widgetType'] ) ) {
				continue;
			}
			if ( ! isset( $e['isInner'] ) ) {
				$e['isInner'] = false;
			}
			$out[] = $e;
		}
		return $out;
	}

	private static function label( array $e ) {
		$s = $e['settings'] ?? array();
		foreach ( array( 'title', 'editor', 'text', 'title_text', 'shortcode', 'html', '_title', 'description_text', '_element_id', 'css_id' ) as $k ) {
			if ( ! empty( $s[ $k ] ) && is_string( $s[ $k ] ) ) {
				$l = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $s[ $k ] ) ) );
				if ( '' !== $l ) {
					return ( function_exists( 'mb_substr' ) ? mb_substr( $l, 0, 70 ) : substr( $l, 0, 70 ) );
				}
			}
		}
		return '';
	}

	private static function outline( array $els ) {
		$out = array();
		foreach ( $els as $e ) {
			$row = array(
				'id'   => $e['id'] ?? '',
				'type' => 'widget' === ( $e['elType'] ?? '' ) ? ( $e['widgetType'] ?? 'widget' ) : ( $e['elType'] ?? '' ),
			);
			$l = self::label( $e );
			if ( '' !== $l ) {
				$row['label'] = $l;
			}
			if ( ! empty( $e['elements'] ) ) {
				$row['children'] = self::outline( $e['elements'] );
			}
			$out[] = $row;
		}
		return $out;
	}

	private static function find( array $els, $id ) {
		foreach ( $els as $e ) {
			if ( ( $e['id'] ?? '' ) === $id ) {
				return $e;
			}
			if ( ! empty( $e['elements'] ) ) {
				$f = self::find( $e['elements'], $id );
				if ( $f ) {
					return $f;
				}
			}
		}
		return null;
	}

	/** Applies one patch op to the tree. Returns true when the target was found. */
	private static function apply_op( array &$els, array $op ) {
		$id = (string) ( $op['id'] ?? '' );
		foreach ( $els as $i => &$e ) {
			if ( ( $e['id'] ?? '' ) === $id ) {
				switch ( $op['op'] ) {
					case 'set':
						$e['settings'] = array_merge( (array) $e['settings'], (array) ( $op['settings'] ?? array() ) );
						break;
					case 'delete':
						array_splice( $els, $i, 1 );
						break;
					case 'insert_after':
						array_splice( $els, $i + 1, 0, self::normalize( array( (array) $op['element'] ) ) );
						break;
					case 'append':
						$e['elements'] = array_merge( (array) $e['elements'], self::normalize( array( (array) $op['element'] ) ) );
						break;
				}
				return true;
			}
			if ( ! empty( $e['elements'] ) && self::apply_op( $e['elements'], $op ) ) {
				return true;
			}
		}
		unset( $e );
		return false;
	}

	/* -- page_html ------------------------------------------------------ */

	public static function page_html( $a ) {
		if ( ! empty( $a['id'] ) ) {
			$p = get_post( (int) $a['id'] );
			if ( ! $p || 'publish' !== $p->post_status ) {
				return new WP_Error( 'not_found', 'No published page or post with id ' . (int) $a['id'] . '. Drafts are not visible to visitors.' );
			}
			$url = get_permalink( $p );
		} else {
			$url = esc_url_raw( trim( (string) ( $a['url'] ?? '' ) ) );
			if ( '' === $url ) {
				$url = home_url( '/' );
			}
			if ( 0 === strpos( $url, '/' ) ) {
				$url = home_url( $url );
			}
		}
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) !== strtolower( (string) $host ) ) {
			return new WP_Error( 'other_site', 'Only URLs on this site (' . $host . ') can be read.' );
		}
		$r = wp_remote_get( add_query_arg( 'fbcc_nc', time(), $url ), array(
			'timeout'     => 20,
			'redirection' => 3,
			'sslverify'   => apply_filters( 'https_local_ssl_verify', false ),
			'user-agent'  => 'Mozilla/5.0 (FB-Claude-Connector/' . FBCC_VERSION . '; ' . home_url() . ')',
			'headers'     => array( 'Cache-Control' => 'no-cache' ),
		) );
		if ( is_wp_error( $r ) ) {
			return new WP_Error( 'fetch_failed', 'Could not load ' . $url . ': ' . $r->get_error_message() );
		}
		$body = (string) wp_remote_retrieve_body( $r );
		$out  = array(
			'url'    => $url,
			'status' => (int) wp_remote_retrieve_response_code( $r ),
			'type'   => (string) wp_remote_retrieve_header( $r, 'content-type' ),
			'size'   => strlen( $body ),
		);
		if ( false !== stripos( $out['type'], 'html' ) ) {
			preg_match_all( '#<link[^>]+rel=["\']stylesheet["\'][^>]*href=["\']([^"\']+)#i', $body, $css );
			preg_match_all( '#<script[^>]+src=["\']([^"\']+)#i', $body, $js );
			$out['stylesheets'] = array_values( array_unique( $css[1] ) );
			$out['scripts']     = array_values( array_unique( $js[1] ) );
		}
		$find = (string) ( $a['find'] ?? '' );
		if ( '' !== $find ) {
			$ctx  = min( 5000, max( 50, (int) ( $a['context'] ?? 600 ) ) );
			$hits = array();
			$pos  = 0;
			while ( count( $hits ) < 20 && false !== ( $at = stripos( $body, $find, $pos ) ) ) {
				$from   = max( 0, $at - $ctx );
				$hits[] = array( 'at' => $at, 'text' => substr( $body, $from, strlen( $find ) + 2 * $ctx ) );
				$pos    = $at + max( 1, strlen( $find ) + $ctx ); // skip overlapping excerpts
			}
			$out['matches'] = $hits;
			$out['found']   = count( $hits );
			return $out;
		}
		$offset          = max( 0, (int) ( $a['offset'] ?? 0 ) );
		$length          = min( 200000, max( 1000, (int) ( $a['length'] ?? 60000 ) ) );
		$out['offset']   = $offset;
		$out['html']     = substr( $body, $offset, $length );
		$out['has_more'] = $offset + $length < strlen( $body );
		return $out;
	}

	/* -- page_screenshot ------------------------------------------------- */

	public static function page_screenshot( $a ) {
		if ( ! empty( $a['id'] ) ) {
			$p = get_post( (int) $a['id'] );
			if ( ! $p || 'publish' !== $p->post_status ) {
				return new WP_Error( 'not_found', 'No published page or post with id ' . (int) $a['id'] . '. Drafts cannot be photographed.' );
			}
			$url = get_permalink( $p );
		} else {
			$url = esc_url_raw( trim( (string) ( $a['url'] ?? '' ) ) );
			if ( '' === $url ) {
				$url = home_url( '/' );
			}
			if ( 0 === strpos( $url, '/' ) ) {
				$url = home_url( $url );
			}
		}
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) !== strtolower( (string) $host ) ) {
			return new WP_Error( 'other_site', 'Only pages on this site (' . $host . ') can be photographed.' );
		}
		$sizes  = array(
			'mobile'  => array( 390, 844 ),
			'tablet'  => array( 768, 1024 ),
			'desktop' => array( 1280, 800 ),
		);
		$device = isset( $sizes[ $a['device'] ?? '' ] ) ? $a['device'] : 'mobile';
		list( $vpw, $vph ) = $sizes[ $device ];
		if ( ! empty( $a['height'] ) ) {
			$vph = min( 3000, max( 400, (int) $a['height'] ) );
		}
		// A fresh query arg every minute, so mShots does not serve an old picture after a change.
		$target = add_query_arg( 'fbcc_shot', (string) floor( time() / 60 ), $url );
		$shot   = add_query_arg( array(
			'w'   => min( $vpw, 1280 ),
			'vpw' => $vpw,
			'vph' => $vph,
		), 'https://s0.wp.com/mshots/v1/' . rawurlencode( $target ) );

		for ( $try = 0; $try < 4; $try++ ) {
			if ( $try ) {
				sleep( 5 );
			}
			$r = wp_remote_get( $shot, array(
				'timeout'     => 20,
				'redirection' => 0,
				'user-agent'  => 'FB-Claude-Connector/' . FBCC_VERSION . '; ' . home_url(),
			) );
			if ( is_wp_error( $r ) ) {
				return new WP_Error( 'shot_failed', 'Could not reach the screenshot service: ' . $r->get_error_message() );
			}
			$type = (string) wp_remote_retrieve_header( $r, 'content-type' );
			if ( 200 === (int) wp_remote_retrieve_response_code( $r ) && 0 === stripos( $type, 'image/jpeg' ) ) {
				return array(
					'url'    => $url,
					'device' => $device,
					'width'  => $vpw,
					'height' => $vph,
					'image'  => array(
						'data'     => base64_encode( (string) wp_remote_retrieve_body( $r ) ),
						'mimeType' => 'image/jpeg',
					),
				);
			}
		}
		return array(
			'url'     => $url,
			'device'  => $device,
			'pending' => true,
			'message' => 'The screenshot is still being made. Call page_screenshot again in about 20 seconds.',
		);
	}

	/* -- elementor_get / elementor_set ---------------------------------- */

	public static function get( $a ) {
		$doc = self::document( $a['id'] );
		if ( is_wp_error( $doc ) ) {
			return $doc;
		}
		$id  = $doc->get_main_id();
		$els = self::elements_of( $doc );
		$out = array(
			'id'                     => $id,
			'title'                  => get_the_title( $id ),
			'status'                 => get_post_status( $id ),
			'built_with_elementor'   => $doc->is_built_with_elementor(),
			'page_template'          => get_page_template_slug( $id ),
			'page_settings'          => self::settings_of( $id ),
			'edit'                   => admin_url( 'post.php?post=' . $id . '&action=elementor' ),
		);
		if ( ! empty( $a['element'] ) ) {
			$e = self::find( $els, (string) $a['element'] );
			if ( ! $e ) {
				return new WP_Error( 'no_element', 'No element with id ' . sanitize_key( $a['element'] ) . ' on this page.' );
			}
			$out['element'] = $e;
			return $out;
		}
		if ( ! empty( $a['outline'] ) ) {
			$out['outline'] = self::outline( $els );
		} else {
			$out['elements'] = $els;
		}
		return $out;
	}

	/** Works out the new element tree and settings from the arguments. */
	private static function build( $doc, array $a ) {
		$given = array_filter( array( isset( $a['elements'] ), isset( $a['template'] ), isset( $a['patch'] ) ) );
		if ( count( $given ) > 1 ) {
			return new WP_Error( 'one_of', 'Give only one of elements, template or patch.' );
		}
		$settings = isset( $a['page_settings'] ) && is_array( $a['page_settings'] ) ? $a['page_settings'] : array();
		if ( isset( $a['template'] ) ) {
			$tpl = (array) $a['template'];
			if ( ! isset( $tpl['content'] ) || ! is_array( $tpl['content'] ) ) {
				return new WP_Error( 'bad_template', 'template needs a "content" array (an Elementor template export).' );
			}
			$els      = self::normalize( $tpl['content'] );
			$settings = array_merge( isset( $tpl['page_settings'] ) && is_array( $tpl['page_settings'] ) ? $tpl['page_settings'] : array(), $settings );
		} elseif ( isset( $a['elements'] ) ) {
			$els = self::normalize( (array) $a['elements'] );
		} elseif ( isset( $a['patch'] ) ) {
			$els     = self::elements_of( $doc );
			$missing = array();
			foreach ( (array) $a['patch'] as $op ) {
				$op = (array) $op;
				if ( ! in_array( $op['op'] ?? '', array( 'set', 'delete', 'insert_after', 'append' ), true ) ) {
					return new WP_Error( 'bad_op', 'Patch op must be set, delete, insert_after or append.' );
				}
				if ( in_array( $op['op'], array( 'insert_after', 'append' ), true ) && empty( $op['element'] ) ) {
					return new WP_Error( 'bad_op', $op['op'] . ' needs an "element".' );
				}
				if ( 'append' === $op['op'] && '' === (string) ( $op['id'] ?? '' ) ) {
					$els = array_merge( $els, self::normalize( array( (array) $op['element'] ) ) );
					continue;
				}
				if ( ! self::apply_op( $els, $op ) ) {
					$missing[] = (string) ( $op['id'] ?? '' );
				}
			}
			if ( $missing ) {
				return new WP_Error( 'no_element', 'No element with id ' . implode( ', ', array_map( 'sanitize_key', $missing ) ) . ' — nothing was changed. Read the ids with elementor_get outline=true.' );
			}
		} elseif ( $settings ) {
			$els = null; // page settings only
		} else {
			return new WP_Error( 'nothing_to_change', 'Give elements, template, patch or page_settings.' );
		}
		return array( 'elements' => $els, 'settings' => $settings );
	}

	public static function set( $a ) {
		$doc = self::document( $a['id'] );
		if ( is_wp_error( $doc ) ) {
			return $doc;
		}
		$id = $doc->get_main_id();
		if ( ! current_user_can( 'edit_post', $id ) ) {
			return new WP_Error( 'forbidden', 'claude-agent may not edit this item.' );
		}
		$new = self::build( $doc, $a );
		if ( is_wp_error( $new ) ) {
			return $new;
		}
		$payload = array( 'id' => $id, 'elements' => $new['elements'], 'settings' => $new['settings'] );
		$what    = self::describe( $payload );
		if ( in_array( get_post_status( $id ), array( 'publish', 'private', 'future' ), true ) ) {
			$title   = 'Elementor: ' . $what . ' on “' . get_the_title( $id ) . '”';
			$by_plan = FBCC_Plan::intercept( 'elementor_set', $title, $payload );
			if ( null !== $by_plan ) {
				return $by_plan;
			}
			$qid = FBCC_Gateway::queue( 'elementor_set', $title . ' (live)', $a['reason'] ?? '', $payload );
			return array(
				'_queued'  => $qid,
				'_summary' => 'Asked to change live “' . get_the_title( $id ) . '” in Elementor — waiting for approval #' . $qid,
				'queued'   => true,
				'approval' => $qid,
				'message'  => 'This page is published, so the change is waiting for the owner to approve it. Check approvals_list later.',
			);
		}
		return self::set_execute( $payload );
	}

	private static function describe( array $p ) {
		$parts = array();
		if ( is_array( $p['elements'] ) ) {
			$parts[] = count( $p['elements'] ) . ' top-level element' . ( 1 === count( $p['elements'] ) ? '' : 's' );
		}
		if ( $p['settings'] ) {
			$parts[] = 'page settings';
		}
		return implode( ' + ', $parts );
	}

	public static function set_execute( $p ) {
		$doc = self::document( $p['id'] );
		if ( is_wp_error( $doc ) ) {
			return $doc;
		}
		$id   = $doc->get_main_id();
		$undo = self::backup( $id );
		$data = array();
		if ( is_array( $p['elements'] ?? null ) ) {
			$data['elements'] = $p['elements'];
		}
		if ( ! empty( $p['settings'] ) ) {
			$data['settings'] = array_merge( self::settings_of( $id ), (array) $p['settings'] );
		}
		$doc->set_is_built_with_elementor( true );
		if ( ! $doc->save( $data ) ) {
			return new WP_Error( 'save_failed', 'Elementor refused to save (the current user may not edit this page with Elementor).' );
		}
		if ( ! empty( $p['settings']['template'] ) ) {
			update_post_meta( $id, '_wp_page_template', sanitize_text_field( $p['settings']['template'] ) );
		}
		return array(
			'_summary' => 'Elementor: saved ' . self::describe( $p ) . ' on “' . get_the_title( $id ) . '”',
			'_undo'    => $undo,
			'id'       => $id,
			'link'     => get_permalink( $id ),
			'edit'     => admin_url( 'post.php?post=' . $id . '&action=elementor' ),
		);
	}

	/** Keeps the last versions of a page's Elementor data; returns the undo link. */
	private static function backup( $id ) {
		$list   = get_post_meta( $id, self::BACKUP, true );
		$list   = is_array( $list ) ? $list : array();
		$key    = time() . wp_rand( 100, 999 );
		$list[ $key ] = array(
			'data'     => (string) get_post_meta( $id, '_elementor_data', true ),
			'settings' => self::settings_of( $id ),
			'mode'     => (string) get_post_meta( $id, '_elementor_edit_mode', true ),
		);
		update_post_meta( $id, self::BACKUP, wp_slash( array_slice( $list, -self::KEEP, null, true ) ) );
		return wp_nonce_url( admin_url( 'admin-post.php?action=fbcc_elementor_restore&post=' . $id . '&b=' . $key ), 'fbcc_elementor_restore_' . $id . '_' . $key );
	}

	public static function restore() {
		$id  = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		$key = isset( $_GET['b'] ) ? sanitize_key( wp_unslash( $_GET['b'] ) ) : '';
		if ( ! current_user_can( 'edit_post', $id ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		check_admin_referer( 'fbcc_elementor_restore_' . $id . '_' . $key );
		$list = get_post_meta( $id, self::BACKUP, true );
		if ( ! is_array( $list ) || empty( $list[ $key ] ) ) {
			wp_die( 'This version is no longer stored.' );
		}
		$b = $list[ $key ];
		update_metadata( 'post', $id, '_elementor_data', wp_slash( $b['data'] ) );
		update_post_meta( $id, '_elementor_page_settings', $b['settings'] );
		if ( '' === $b['mode'] ) {
			delete_post_meta( $id, '_elementor_edit_mode' );
		} else {
			update_post_meta( $id, '_elementor_edit_mode', $b['mode'] );
		}
		self::clear_cache( $id );
		FBCC_Store::log( 'elementor_set', 'Restored the earlier Elementor version of “' . get_the_title( $id ) . '”', 'done' );
		wp_safe_redirect( admin_url( 'post.php?post=' . $id . '&action=elementor' ) );
		exit;
	}

	private static function clear_cache( $id = 0 ) {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return;
		}
		if ( $id && class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
			\Elementor\Core\Files\CSS\Post::create( $id )->delete();
		}
		if ( isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
	}

	/* -- kit ------------------------------------------------------------ */

	private static function kit() {
		$ok = self::ready();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();
		if ( ! $kit || ! $kit->get_main_id() ) {
			return new WP_Error( 'no_kit', 'This site has no active Elementor kit (Site Settings).' );
		}
		return $kit;
	}

	public static function kit_get( $a ) {
		$kit = self::kit();
		if ( is_wp_error( $kit ) ) {
			return $kit;
		}
		$s = self::settings_of( $kit->get_main_id() );
		if ( ! empty( $a['keys'] ) ) {
			$s = array_intersect_key( $s, array_flip( array_map( 'strval', (array) $a['keys'] ) ) );
		}
		return array(
			'kit'      => $kit->get_main_id(),
			'settings' => (object) $s,
		);
	}

	public static function kit_prepare( $a ) {
		$kit = self::kit();
		if ( is_wp_error( $kit ) ) {
			return $kit;
		}
		$set = isset( $a['settings'] ) && is_array( $a['settings'] ) ? $a['settings'] : array();
		if ( ! $set ) {
			return new WP_Error( 'empty', 'settings is empty.' );
		}
		$keys = array_keys( $set );
		return array(
			'title'   => 'Elementor Site Settings: ' . implode( ', ', array_slice( $keys, 0, 6 ) ) . ( count( $keys ) > 6 ? ' +' . ( count( $keys ) - 6 ) . ' more' : '' ),
			'payload' => array( 'kit' => $kit->get_main_id(), 'settings' => $set ),
		);
	}

	public static function kit_execute( $p ) {
		$kit = self::kit();
		if ( is_wp_error( $kit ) ) {
			return $kit;
		}
		if ( (int) $kit->get_main_id() !== (int) $p['kit'] ) {
			return new WP_Error( 'kit_changed', 'The active Elementor kit changed since this was prepared.' );
		}
		$id   = $kit->get_main_id();
		$undo = self::backup( $id );
		update_post_meta( $id, '_elementor_page_settings', array_merge( self::settings_of( $id ), (array) $p['settings'] ) );
		self::clear_cache( $id );
		return array(
			'_summary' => 'Elementor Site Settings changed (' . implode( ', ', array_keys( (array) $p['settings'] ) ) . ')',
			'_undo'    => $undo,
		);
	}

	/* -- Code Snippets -------------------------------------------------- */

	private static function snippets_ready() {
		if ( ! function_exists( '\Code_Snippets\get_snippet' ) || ! class_exists( '\Code_Snippets\Snippet' ) ) {
			return new WP_Error( 'no_snippets', 'The Code Snippets plugin is not active (install "code-snippets" first).' );
		}
		return true;
	}

	private static function snippet_row( $s, $with_code = false ) {
		$row = array(
			'id'       => (int) $s->id,
			'name'     => (string) $s->name,
			'type'     => method_exists( $s, 'get_type' ) ? $s->get_type() : (string) $s->type,
			'scope'    => (string) $s->scope,
			'active'   => (bool) $s->active,
			'priority' => (int) $s->priority,
			'modified' => (string) $s->modified,
		);
		if ( $with_code ) {
			$row['desc'] = (string) $s->desc;
			$row['tags'] = (array) $s->tags;
			$row['code'] = (string) $s->code;
		}
		return $row;
	}

	public static function snippets_list( $a ) {
		$ok = self::snippets_ready();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$q   = strtolower( (string) ( $a['search'] ?? '' ) );
		$out = array();
		foreach ( \Code_Snippets\get_snippets() as $s ) {
			if ( '' !== $q && false === strpos( strtolower( $s->name . ' ' . $s->desc . ' ' . $s->code ), $q ) ) {
				continue;
			}
			$out[] = self::snippet_row( $s );
		}
		return array( 'snippets' => $out );
	}

	public static function snippet_get( $a ) {
		$ok = self::snippets_ready();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$s = \Code_Snippets\get_snippet( (int) $a['id'] );
		if ( ! $s || ! $s->id ) {
			return new WP_Error( 'not_found', 'No snippet with id ' . (int) $a['id'] . '.' );
		}
		return self::snippet_row( $s, true );
	}
}
