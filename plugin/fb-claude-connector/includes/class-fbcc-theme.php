<?php
/**
 * Theme & site-building tools (0.6.0):
 *   theme_settings_get / theme_settings_set   – the active theme's settings (Kadence: header, footer, colours, fonts)
 *   widgets_set                                – put block content into a widget area (e.g. Kadence footer columns)
 *   plugins_install                            – install + activate a plugin from WordPress.org
 *   form_create                                – a Contact Form 7 form (when CF7 is active)
 * Theme, widget and plugin changes change the live site, so they go to approvals — unless an approved build plan covers them.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FBCC_Theme {

	const BACKUP = 'fbcc_theme_backups';
	const DENY   = array( 'nav_menu_locations', 'sidebars_widgets', 'custom_css_post_id' );
	/** Settings some themes keep as options instead of theme mods. */
	const OPTION_KEYS = array( 'kadence_global_palette' );

	public static function init() {
		add_filter( 'fbcc_tools', array( __CLASS__, 'tools' ), 6 );
		add_action( 'admin_post_fbcc_theme_restore', array( __CLASS__, 'restore' ) );
		add_filter( 'wp_kses_allowed_html', array( __CLASS__, 'allow_svg' ), 10, 2 );
	}

	/** True while a connector tool or an approved connector action is running. */
	public static $running = false;

	/**
	 * Inline SVG icons (shapes only — no scripts, no styles, no links) are allowed in content
	 * that the connector writes. Everything else keeps WordPress's normal post filter.
	 */
	public static function allow_svg( $tags, $context ) {
		if ( ! self::$running || 'post' !== $context ) {
			return $tags;
		}
		$paint = array(
			'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true,
			'stroke-dasharray' => true, 'opacity' => true, 'fill-rule' => true, 'clip-rule' => true, 'transform' => true, 'class' => true,
		);
		$tags['svg']      = array_merge( $paint, array( 'xmlns' => true, 'width' => true, 'height' => true, 'viewbox' => true, 'aria-hidden' => true, 'aria-label' => true, 'role' => true, 'focusable' => true, 'style' => true ) );
		$tags['g']        = $paint;
		$tags['title']    = array();
		$tags['path']     = array_merge( $paint, array( 'd' => true ) );
		$tags['circle']   = array_merge( $paint, array( 'cx' => true, 'cy' => true, 'r' => true ) );
		$tags['ellipse']  = array_merge( $paint, array( 'cx' => true, 'cy' => true, 'rx' => true, 'ry' => true ) );
		$tags['rect']     = array_merge( $paint, array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true ) );
		$tags['line']     = array_merge( $paint, array( 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true ) );
		$tags['polyline'] = array_merge( $paint, array( 'points' => true ) );
		$tags['polygon']  = array_merge( $paint, array( 'points' => true ) );
		return $tags;
	}

	private static function obj( array $props, array $required = array() ) {
		$s = array( 'type' => 'object', 'properties' => (object) $props, 'additionalProperties' => false );
		if ( $required ) {
			$s['required'] = $required;
		}
		return $s;
	}

	public static function tools( $t ) {
		$t['theme_settings_get'] = array(
			'title'       => 'Read theme settings',
			'description' => 'The active theme and its settings (theme mods). Without "keys": every key with a short preview, plus the widget areas and menu locations. With "keys": the full values. Use it to learn the exact shape before theme_settings_set (Kadence: kadence_global_palette, base_font, heading_font, header_* and footer_* keys).',
			'level'       => 'read',
			'kind'        => 'read',
			'schema'      => self::obj( array(
				'keys'   => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'search' => array( 'type' => 'string', 'description' => 'Only keys containing this text' ),
			) ),
			'run'         => array( __CLASS__, 'get' ),
		);
		$t['theme_settings_set'] = array(
			'title'       => 'Change theme settings',
			'description' => 'Set theme settings (theme mods) of the active theme — colours, fonts, header and footer layout, header button, footer text. "mods" is an object key → value (arrays allowed). The previous values are kept for undo. Goes to approvals unless the build plan includes the theme.',
			'level'       => 'editor',
			'kind'        => 'approval',
			'schema'      => self::obj( array(
				'mods'   => array( 'type' => 'object' ),
				'reason' => array( 'type' => 'string' ),
			), array( 'mods', 'reason' ) ),
			'prepare'     => array( __CLASS__, 'set_prepare' ),
			'execute'     => array( __CLASS__, 'set_execute' ),
		);
		$t['widgets_set'] = array(
			'title'       => 'Fill a widget area',
			'description' => 'Replace the content of one widget area (e.g. a Kadence footer column "footer1") with block markup — one item per widget. Old widgets move to Inactive widgets, nothing is deleted. Goes to approvals unless the build plan includes the theme.',
			'level'       => 'editor',
			'kind'        => 'approval',
			'schema'      => self::obj( array(
				'area'   => array( 'type' => 'string', 'description' => 'Widget area id from theme_settings_get' ),
				'blocks' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Block markup, one widget each (max 10)' ),
				'reason' => array( 'type' => 'string' ),
			), array( 'area', 'blocks', 'reason' ) ),
			'prepare'     => array( __CLASS__, 'widgets_prepare' ),
			'execute'     => array( __CLASS__, 'widgets_execute' ),
		);
		$t['plugins_install'] = array(
			'title'       => 'Install a plugin',
			'description' => 'Install and activate a plugin from WordPress.org by its slug (e.g. "contact-form-7"). Needs the Site maintainer level; blocked while the lab lock is on; goes to approvals unless the build plan lists this plugin.',
			'level'       => 'maintainer',
			'kind'        => 'approval',
			'site'        => true,
			'schema'      => self::obj( array(
				'slug'   => array( 'type' => 'string' ),
				'reason' => array( 'type' => 'string' ),
			), array( 'slug', 'reason' ) ),
			'prepare'     => array( __CLASS__, 'plugin_prepare' ),
			'execute'     => array( __CLASS__, 'plugin_execute' ),
		);
		$t['theme_install'] = array(
			'title'       => 'Install a theme',
			'description' => 'Install a theme from WordPress.org by its slug (e.g. "kadence") and make it the active theme. The previous theme stays installed and can be switched back with the undo link. Needs the Site maintainer level; goes to approvals unless the build plan lists this theme (plan field "themes"). Take a backup first.',
			'level'       => 'maintainer',
			'kind'        => 'approval',
			'site'        => true,
			'schema'      => self::obj( array(
				'slug'   => array( 'type' => 'string' ),
				'reason' => array( 'type' => 'string' ),
			), array( 'slug', 'reason' ) ),
			'prepare'     => array( __CLASS__, 'theme_prepare' ),
			'execute'     => array( __CLASS__, 'theme_execute' ),
		);
		$t['custom_css_set'] = array(
			'title'       => 'Site CSS (Additional CSS)',
			'description' => 'Set the site-wide Additional CSS of the active theme (Appearance → Customize → Additional CSS). Use it for styles that blocks cannot carry — <style> tags inside page content are removed. mode "append" adds to the existing CSS (default), "replace" swaps it. The previous CSS is kept for undo. Goes to approvals unless the build plan includes the theme.',
			'level'       => 'editor',
			'kind'        => 'approval',
			'schema'      => self::obj( array(
				'css'    => array( 'type' => 'string' ),
				'mode'   => array( 'type' => 'string', 'enum' => array( 'append', 'replace' ), 'default' => 'append' ),
				'reason' => array( 'type' => 'string' ),
			), array( 'css', 'reason' ) ),
			'prepare'     => array( __CLASS__, 'css_prepare' ),
			'execute'     => array( __CLASS__, 'css_execute' ),
		);
		{ // 1.2.1: always listed — a chat keeps the tool list it started with, and CF7 may be installed mid-build.
			$t['form_create'] = array(
				'title'       => 'Create a contact form',
				'description' => 'Create a Contact Form 7 form (install the plugin "contact-form-7" first). Returns the shortcode to place in a page (wrap it in a wp:shortcode block). Field types: text, email, tel, textarea, select, acceptance. Messages go to "recipient" (default: the site admin email).',
				'level'       => 'editor',
				'kind'        => 'write',
				'schema'      => self::obj( array(
					'title'     => array( 'type' => 'string' ),
					'fields'    => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => (object) array(
								'name'     => array( 'type' => 'string', 'description' => 'a-z, 0-9 and dashes, e.g. your-name' ),
								'label'    => array( 'type' => 'string' ),
								'type'     => array( 'type' => 'string', 'enum' => array( 'text', 'email', 'tel', 'textarea', 'select', 'acceptance' ) ),
								'required' => array( 'type' => 'boolean' ),
								'options'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
							),
						),
					),
					'submit'    => array( 'type' => 'string', 'default' => 'Send message' ),
					'recipient' => array( 'type' => 'string' ),
					'subject'   => array( 'type' => 'string' ),
				), array( 'title', 'fields' ) ),
				'run'         => array( __CLASS__, 'form_create' ),
			);
		}
		return $t;
	}

	/* -- read -------------------------------------------------------- */

	public static function get( $a ) {
		global $wp_registered_sidebars;
		$mods  = (array) get_theme_mods();
		$theme = wp_get_theme();
		$keys  = isset( $a['keys'] ) ? array_map( 'strval', (array) $a['keys'] ) : array();
		$out   = array();
		if ( $keys ) {
			foreach ( $keys as $k ) {
				$out[ $k ] = array_key_exists( $k, $mods ) ? $mods[ $k ] : null;
			}
		} else {
			$search = (string) ( $a['search'] ?? '' );
			foreach ( $mods as $k => $v ) {
				if ( '' !== $search && false === strpos( (string) $k, $search ) ) {
					continue;
				}
				$json      = wp_json_encode( $v );
				$out[ $k ] = strlen( (string) $json ) > 160 ? substr( $json, 0, 160 ) . '… (' . strlen( $json ) . ' chars — ask with keys for the full value)' : $v;
			}
		}
		$areas = array();
		foreach ( (array) $wp_registered_sidebars as $id => $sb ) {
			$areas[ $id ] = $sb['name'];
		}
		return array(
			'theme'          => $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ),
			'stylesheet'     => get_stylesheet(),
			'settings'       => (object) $out,
			'widget_areas'   => $areas,
			'menu_locations' => get_registered_nav_menus(),
			'note'           => $keys ? '' : 'Settings never changed keep the theme default and are not listed. Kadence: set kadence_global_palette as a JSON string; fonts as arrays like {"family":"Manrope","google":true,"variant":"regular","weight":"400"}.',
		);
	}

	/* -- theme settings ---------------------------------------------- */

	private static function clean_value( $v ) {
		if ( is_array( $v ) ) {
			$o = array();
			foreach ( $v as $k => $x ) {
				$o[ is_int( $k ) ? $k : sanitize_text_field( (string) $k ) ] = self::clean_value( $x );
			}
			return $o;
		}
		if ( is_string( $v ) ) {
			return wp_kses_post( $v );
		}
		if ( is_bool( $v ) || is_int( $v ) || is_float( $v ) || null === $v ) {
			return $v;
		}
		return '';
	}

	public static function set_prepare( $a ) {
		$mods = is_array( $a['mods'] ) ? $a['mods'] : (array) $a['mods'];
		if ( ! $mods ) {
			return new WP_Error( 'empty', 'mods is empty.' );
		}
		if ( count( $mods ) > 60 || strlen( (string) wp_json_encode( $mods ) ) > 200000 ) {
			return new WP_Error( 'too_big', 'At most 60 settings and 200 KB per change.' );
		}
		$clean = array();
		foreach ( $mods as $k => $v ) {
			$k = (string) $k;
			if ( ! preg_match( '/^[A-Za-z0-9_\-]{1,100}$/', $k ) || in_array( $k, self::DENY, true ) ) {
				return new WP_Error( 'bad_key', 'Setting "' . sanitize_text_field( $k ) . '" cannot be changed with this tool.' );
			}
			$clean[ $k ] = self::clean_value( $v );
		}
		$names = array_keys( $clean );
		return array(
			'title'   => 'Theme settings (' . wp_get_theme()->get( 'Name' ) . '): ' . count( $names ) . ' change' . ( count( $names ) > 1 ? 's' : '' ) . ' — ' . implode( ', ', array_slice( $names, 0, 6 ) ) . ( count( $names ) > 6 ? ', …' : '' ),
			'payload' => array( 'stylesheet' => get_stylesheet(), 'mods' => $clean ),
		);
	}

	private static function backup( $kind, array $data ) {
		$b   = get_option( self::BACKUP, array() );
		$b   = is_array( $b ) ? $b : array();
		$id  = time() . wp_rand( 100, 999 );
		$b[ $id ] = array( 'kind' => $kind, 'time' => time(), 'data' => $data );
		update_option( self::BACKUP, array_slice( $b, -10, null, true ), false );
		return wp_nonce_url( admin_url( 'admin-post.php?action=fbcc_theme_restore&b=' . $id ), 'fbcc_theme_restore_' . $id );
	}

	public static function set_execute( $p ) {
		if ( get_stylesheet() !== $p['stylesheet'] ) {
			return new WP_Error( 'theme_changed', 'The active theme changed since this was prepared.' );
		}
		$old = array();
		foreach ( $p['mods'] as $k => $v ) {
			if ( in_array( $k, self::OPTION_KEYS, true ) ) {
				$o         = get_option( $k, null );
				$old[ $k ] = array( '__option__' => null === $o ? '__unset__' : $o );
				continue;
			}
			$old[ $k ] = array_key_exists( $k, (array) get_theme_mods() ) ? get_theme_mod( $k ) : '__unset__';
		}
		$undo = self::backup( 'mods', $old );
		foreach ( $p['mods'] as $k => $v ) {
			if ( in_array( $k, self::OPTION_KEYS, true ) ) {
				// Kadence reads its global palette from an option, not a theme mod.
				update_option( $k, is_array( $v ) ? wp_json_encode( $v ) : $v );
				continue;
			}
			set_theme_mod( $k, $v );
		}
		return array(
			'_summary' => 'Theme settings changed: ' . implode( ', ', array_keys( $p['mods'] ) ),
			'_undo'    => $undo,
		);
	}

	/* -- widgets ------------------------------------------------------ */

	public static function widgets_prepare( $a ) {
		global $wp_registered_sidebars;
		$area = sanitize_key( (string) $a['area'] );
		if ( empty( $wp_registered_sidebars[ $area ] ) ) {
			return new WP_Error( 'bad_area', 'Unknown widget area. This theme has: ' . implode( ', ', array_keys( (array) $wp_registered_sidebars ) ) );
		}
		$blocks = array_slice( array_values( array_filter( array_map( 'strval', (array) $a['blocks'] ) ) ), 0, 10 );
		if ( ! $blocks ) {
			return new WP_Error( 'empty', 'Give at least one block.' );
		}
		return array(
			'title'   => 'Widget area “' . $wp_registered_sidebars[ $area ]['name'] . '”: ' . count( $blocks ) . ' block' . ( count( $blocks ) > 1 ? 's' : '' ),
			'payload' => array( 'area' => $area, 'blocks' => array_map( 'wp_kses_post', $blocks ) ),
		);
	}

	public static function widgets_execute( $p ) {
		$sidebars = wp_get_sidebars_widgets();
		$undo     = self::backup( 'sidebars', array( 'sidebars' => $sidebars ) );
		$opt      = get_option( 'widget_block', array() );
		$opt      = is_array( $opt ) ? $opt : array();
		$next     = 1;
		foreach ( array_keys( $opt ) as $k ) {
			if ( is_int( $k ) && $k >= $next ) {
				$next = $k + 1;
			}
		}
		$ids = array();
		foreach ( $p['blocks'] as $html ) {
			$opt[ $next ] = array( 'content' => $html );
			$ids[]        = 'block-' . $next;
			$next++;
		}
		$opt['_multiwidget'] = 1;
		update_option( 'widget_block', $opt );
		$old = isset( $sidebars[ $p['area'] ] ) ? (array) $sidebars[ $p['area'] ] : array();
		$sidebars['wp_inactive_widgets'] = array_merge( (array) ( $sidebars['wp_inactive_widgets'] ?? array() ), $old );
		$sidebars[ $p['area'] ]          = $ids;
		wp_set_sidebars_widgets( $sidebars );
		return array(
			'_summary' => 'Widget area ' . $p['area'] . ' filled with ' . count( $ids ) . ' block(s)',
			'_undo'    => $undo,
		);
	}

	/* -- undo ---------------------------------------------------------- */

	public static function restore() {
		$id = isset( $_GET['b'] ) ? sanitize_key( wp_unslash( $_GET['b'] ) ) : '';
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		check_admin_referer( 'fbcc_theme_restore_' . $id );
		$b = get_option( self::BACKUP, array() );
		if ( empty( $b[ $id ] ) ) {
			wp_die( 'This backup is no longer stored.' );
		}
		$item = $b[ $id ];
		if ( 'mods' === $item['kind'] ) {
			foreach ( $item['data'] as $k => $v ) {
				if ( is_array( $v ) && array_key_exists( '__option__', $v ) ) {
					if ( '__unset__' === $v['__option__'] ) {
						delete_option( $k );
					} else {
						update_option( $k, $v['__option__'] );
					}
					continue;
				}
				if ( '__unset__' === $v ) {
					remove_theme_mod( $k );
				} else {
					set_theme_mod( $k, $v );
				}
			}
		} elseif ( 'sidebars' === $item['kind'] ) {
			wp_set_sidebars_widgets( $item['data']['sidebars'] );
		} elseif ( 'css' === $item['kind'] ) {
			wp_update_custom_css_post( (string) $item['data']['css'] );
		} elseif ( 'theme' === $item['kind'] ) {
			$prev = wp_get_theme( (string) $item['data']['stylesheet'] );
			if ( ! $prev->exists() ) {
				wp_die( 'The previous theme is no longer installed.' );
			}
			switch_theme( $prev->get_stylesheet() );
		}
		FBCC_Store::log( 'theme_settings_set', 'Restored the ' . ( 'mods' === $item['kind'] ? 'theme settings' : ( 'css' === $item['kind'] ? 'Additional CSS' : ( 'theme' === $item['kind'] ? 'previous theme' : 'widget areas' ) ) ) . ' from ' . wp_date( 'j M H:i', (int) $item['time'] ), 'done' );
		wp_safe_redirect( admin_url( 'admin.php?page=fbcc&tab=activity&fbcc_msg=' . rawurlencode( 'Restored.' ) ) );
		exit;
	}

	/* -- Additional CSS ------------------------------------------------ */

	public static function css_prepare( $a ) {
		$css = (string) $a['css'];
		$css = str_ireplace( array( '</style', '<script', '<?php' ), '', $css );
		$css = wp_strip_all_tags( $css );
		if ( '' === trim( $css ) ) {
			return new WP_Error( 'empty', 'css is empty.' );
		}
		if ( strlen( $css ) > 100000 ) {
			return new WP_Error( 'too_big', 'At most 100 KB of CSS per change.' );
		}
		$mode = ( $a['mode'] ?? 'append' ) === 'replace' ? 'replace' : 'append';
		$rules = substr_count( $css, '}' );
		return array(
			'title'   => 'Additional CSS: ' . ( 'replace' === $mode ? 'replace with ' : 'add ' ) . $rules . ' rule' . ( 1 === $rules ? '' : 's' ) . ' (' . size_format( strlen( $css ) ) . ')',
			'payload' => array( 'stylesheet' => get_stylesheet(), 'css' => $css, 'mode' => $mode ),
		);
	}

	public static function css_execute( $p ) {
		if ( get_stylesheet() !== $p['stylesheet'] ) {
			return new WP_Error( 'theme_changed', 'The active theme changed since this was prepared.' );
		}
		$old  = (string) wp_get_custom_css();
		$undo = self::backup( 'css', array( 'css' => $old ) );
		$new  = 'replace' === $p['mode'] ? $p['css'] : rtrim( $old ) . ( '' !== trim( $old ) ? "\n\n" : '' ) . $p['css'];
		$r    = wp_update_custom_css_post( $new );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return array(
			'_summary' => 'Additional CSS ' . ( 'replace' === $p['mode'] ? 'replaced' : 'extended' ) . ' (' . size_format( strlen( $new ) ) . ' in total)',
			'_undo'    => $undo,
		);
	}

	/* -- themes -------------------------------------------------------- */

	public static function theme_prepare( $a ) {
		$slug = sanitize_key( (string) $a['slug'] );
		if ( ! $slug ) {
			return new WP_Error( 'bad_slug', 'Give the theme slug from WordPress.org.' );
		}
		$have = wp_get_theme( $slug );
		if ( $have->exists() ) {
			return array(
				'title'   => ( get_stylesheet() === $slug ? 'Theme “' . $have->get( 'Name' ) . '” is already active' : 'Activate theme “' . $have->get( 'Name' ) . '” (installed)' ),
				'payload' => array( 'slug' => $slug ),
			);
		}
		require_once ABSPATH . 'wp-admin/includes/theme.php';
		$info = themes_api( 'theme_information', array( 'slug' => $slug, 'fields' => array( 'sections' => false ) ) );
		if ( is_wp_error( $info ) || empty( $info->download_link ) ) {
			return new WP_Error( 'not_found', 'No theme “' . $slug . '” on WordPress.org.' );
		}
		return array(
			'title'   => 'Install & activate theme “' . wp_strip_all_tags( $info->name ) . '” (' . $slug . ' ' . $info->version . ')',
			'payload' => array( 'slug' => $slug ),
		);
	}

	public static function theme_execute( $p ) {
		require_once ABSPATH . 'wp-admin/includes/theme.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		$slug  = sanitize_key( (string) $p['slug'] );
		$theme = wp_get_theme( $slug );
		if ( ! $theme->exists() ) {
			$info = themes_api( 'theme_information', array( 'slug' => $slug, 'fields' => array( 'sections' => false ) ) );
			if ( is_wp_error( $info ) ) {
				return $info;
			}
			$up = new Theme_Upgrader( new Automatic_Upgrader_Skin() );
			$ok = $up->install( $info->download_link );
			if ( is_wp_error( $ok ) || ! $ok ) {
				return new WP_Error( 'install_failed', 'Theme install failed' . ( is_wp_error( $ok ) ? ': ' . $ok->get_error_message() : '.' ) );
			}
			wp_clean_themes_cache();
			$theme = wp_get_theme( $slug );
			if ( ! $theme->exists() ) {
				return new WP_Error( 'install_failed', 'Installed, but the theme folder “' . $slug . '” was not found.' );
			}
		}
		$prev = get_stylesheet();
		if ( $prev === $slug ) {
			return array( '_summary' => 'Theme ' . $theme->get( 'Name' ) . ' is installed and already active' );
		}
		if ( $theme->errors() ) {
			return new WP_Error( 'broken_theme', 'The theme is installed but broken: ' . $theme->errors()->get_error_message() );
		}
		$undo = self::backup( 'theme', array( 'stylesheet' => $prev ) );
		switch_theme( $slug );
		return array(
			'_summary' => 'Theme ' . $theme->get( 'Name' ) . ' installed and active (was ' . $prev . ')',
			'_undo'    => $undo,
		);
	}

	/* -- plugins ------------------------------------------------------- */

	public static function plugin_prepare( $a ) {
		$slug = sanitize_key( (string) $a['slug'] );
		if ( ! $slug ) {
			return new WP_Error( 'bad_slug', 'Give the plugin slug from WordPress.org.' );
		}
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		$info = plugins_api( 'plugin_information', array( 'slug' => $slug, 'fields' => array( 'sections' => false ) ) );
		if ( is_wp_error( $info ) ) {
			return new WP_Error( 'not_found', 'No plugin “' . $slug . '” on WordPress.org.' );
		}
		return array(
			'title'   => 'Install & activate plugin “' . wp_strip_all_tags( $info->name ) . '” (' . $slug . ' ' . $info->version . ')',
			'payload' => array( 'slug' => $slug ),
		);
	}

	public static function plugin_execute( $p ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		$slug = $p['slug'];
		$file = '';
		foreach ( array_keys( get_plugins() ) as $f ) {
			if ( 0 === strpos( $f, $slug . '/' ) ) {
				$file = $f;
				break;
			}
		}
		if ( ! $file ) {
			$info = plugins_api( 'plugin_information', array( 'slug' => $slug, 'fields' => array( 'sections' => false ) ) );
			if ( is_wp_error( $info ) ) {
				return $info;
			}
			$up = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
			$ok = $up->install( $info->download_link );
			if ( is_wp_error( $ok ) || ! $ok ) {
				return new WP_Error( 'install_failed', 'Install failed' . ( is_wp_error( $ok ) ? ': ' . $ok->get_error_message() : '.' ) );
			}
			$file = $up->plugin_info();
		}
		if ( ! $file ) {
			return new WP_Error( 'install_failed', 'Installed, but the plugin file was not found.' );
		}
		if ( ! is_plugin_active( $file ) ) {
			$act = activate_plugin( $file );
			if ( is_wp_error( $act ) ) {
				return $act;
			}
		}
		return array(
			'_summary' => 'Plugin ' . $slug . ' installed and active',
			'_undo'    => admin_url( 'plugins.php' ),
		);
	}

	/* -- forms (Contact Form 7) ---------------------------------------- */

	public static function form_create( $a ) {
		if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
			return new WP_Error( 'no_cf7', 'Contact Form 7 is not active. Install it with plugins_install slug "contact-form-7", then call form_create again.' );
		}
		if ( ! current_user_can( 'wpcf7_edit_contact_forms' ) ) {
			return new WP_Error( 'forbidden', 'claude-agent may not create forms.' );
		}
		$lines = array();
		$mail  = array();
		foreach ( array_slice( (array) $a['fields'], 0, 20 ) as $f ) {
			$name  = sanitize_key( (string) ( $f['name'] ?? '' ) );
			$label = sanitize_text_field( (string) ( $f['label'] ?? $name ) );
			$type  = in_array( $f['type'] ?? 'text', array( 'text', 'email', 'tel', 'textarea', 'select', 'acceptance' ), true ) ? $f['type'] : 'text';
			if ( ! $name ) {
				continue;
			}
			$req = ! empty( $f['required'] ) && 'acceptance' !== $type ? '*' : '';
			if ( 'select' === $type ) {
				$opts    = array_map( function ( $o ) {
					return '"' . str_replace( '"', '', sanitize_text_field( (string) $o ) ) . '"';
				}, (array) ( $f['options'] ?? array() ) );
				$lines[] = '<label> ' . $label . "\n    [select" . $req . ' ' . $name . ' ' . implode( ' ', $opts ) . "] </label>\n";
			} elseif ( 'acceptance' === $type ) {
				$lines[] = '[acceptance ' . $name . '] ' . $label . " [/acceptance]\n";
				continue;
			} else {
				$lines[] = '<label> ' . $label . "\n    [" . $type . $req . ' ' . $name . "] </label>\n";
			}
			$mail[] = $label . ': [' . $name . ']';
		}
		if ( ! $lines ) {
			return new WP_Error( 'no_fields', 'Give at least one field.' );
		}
		$submit    = sanitize_text_field( (string) ( $a['submit'] ?? 'Send message' ) );
		$lines[]   = '[submit "' . str_replace( '"', '', $submit ) . '"]';
		$recipient = sanitize_email( (string) ( $a['recipient'] ?? '' ) );
		$recipient = $recipient ? $recipient : get_option( 'admin_email' );
		$title     = sanitize_text_field( (string) $a['title'] );

		$cf = WPCF7_ContactForm::get_template( array( 'title' => $title ) );
		$props = array(
			'form' => implode( "\n", $lines ),
			'mail' => array(
				'active'             => true,
				'subject'            => sanitize_text_field( (string) ( $a['subject'] ?? '[_site_title] — new message' ) ),
				'sender'             => '[_site_title] <wordpress@' . wp_parse_url( home_url(), PHP_URL_HOST ) . '>',
				'recipient'          => $recipient,
				'body'               => implode( "\n", $mail ) . "\n\n-- \nSent from [_site_title] ([_site_url])",
				'additional_headers' => '',
				'attachments'        => '',
				'use_html'           => false,
				'exclude_blank'      => false,
			),
		);
		foreach ( (array) $a['fields'] as $f ) {
			if ( ( $f['type'] ?? '' ) === 'email' ) {
				$props['mail']['additional_headers'] = 'Reply-To: [' . sanitize_key( $f['name'] ) . ']';
				break;
			}
		}
		$cf->set_properties( $props );
		$id = $cf->save();
		if ( ! $id ) {
			return new WP_Error( 'save_failed', 'The form could not be saved.' );
		}
		$short = method_exists( $cf, 'shortcode' ) ? $cf->shortcode() : '[contact-form-7 id="' . (int) $id . '" title="' . $title . '"]';
		return array(
			'_summary'  => 'Created contact form “' . $title . '”',
			'_undo'     => admin_url( 'admin.php?page=wpcf7&post=' . (int) $id . '&action=edit' ),
			'id'        => (int) $id,
			'shortcode' => $short,
			'block'     => '<!-- wp:shortcode -->' . $short . '<!-- /wp:shortcode -->',
			'recipient' => $recipient,
		);
	}
}
