<?php
/**
 * Site-building tools: media upload, menus, front page / site identity.
 * Menus and site settings change the live site, so they always go to approvals.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FBCC_SiteTools {

	public static function init() {
		add_filter( 'fbcc_tools', array( __CLASS__, 'tools' ), 5 );
	}

	private static function obj( array $props, array $required = array() ) {
		$s = array( 'type' => 'object', 'properties' => (object) $props, 'additionalProperties' => false );
		if ( $required ) {
			$s['required'] = $required;
		}
		return $s;
	}

	public static function tools( $t ) {
		$t['media_upload'] = array(
			'title'       => 'Upload an image',
			'description' => 'Copy an image (jpg, png, webp, gif; https only, max 8 MB) into the media library. Optionally attach it to a page and set it as featured image. Returns the new image url for use in block markup.',
			'level'       => 'editor',
			'kind'        => 'write',
			'schema'      => self::obj( array(
				'url'      => array( 'type' => 'string', 'description' => 'https address of the image' ),
				'title'    => array( 'type' => 'string' ),
				'alt'      => array( 'type' => 'string', 'description' => 'Alt text (describe the image)' ),
				'attach'   => array( 'type' => 'integer', 'description' => 'Page/post id to attach to' ),
				'featured' => array( 'type' => 'boolean', 'default' => false ),
			), array( 'url', 'alt' ) ),
			'run'         => array( __CLASS__, 'media_upload' ),
		);
		$t['menu_set'] = array(
			'title'       => 'Set a menu',
			'description' => 'Create or replace a navigation menu and place it in a theme location (default "primary", also the mobile menu). Items point to pages by id or to a url; parent = index of an earlier item for a dropdown. Goes to the approval queue.',
			'level'       => 'editor',
			'kind'        => 'approval',
			'schema'      => self::obj( array(
				'name'     => array( 'type' => 'string' ),
				'location' => array( 'type' => 'string', 'default' => 'primary' ),
				'items'    => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => (object) array(
							'title'  => array( 'type' => 'string' ),
							'page'   => array( 'type' => 'integer' ),
							'url'    => array( 'type' => 'string' ),
							'parent' => array( 'type' => 'integer', 'description' => 'index (0-based) of the parent item' ),
						),
					),
				),
				'reason'   => array( 'type' => 'string' ),
			), array( 'name', 'items', 'reason' ) ),
			'prepare'     => array( __CLASS__, 'menu_prepare' ),
			'execute'     => array( __CLASS__, 'menu_execute' ),
		);
		$t['site_settings'] = array(
			'title'       => 'Site settings',
			'description' => 'Set the homepage (a published page id), the site title and/or the tagline. Goes to the approval queue.',
			'level'       => 'editor',
			'kind'        => 'approval',
			'schema'      => self::obj( array(
				'front_page' => array( 'type' => 'integer' ),
				'title'      => array( 'type' => 'string' ),
				'tagline'    => array( 'type' => 'string' ),
				'reason'     => array( 'type' => 'string' ),
			), array( 'reason' ) ),
			'prepare'     => array( __CLASS__, 'settings_prepare' ),
			'execute'     => array( __CLASS__, 'settings_execute' ),
		);
		return $t;
	}

	/* -- media ------------------------------------------------------ */

	public static function media_upload( $a ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'forbidden', 'claude-agent may not upload files.' );
		}
		$url = esc_url_raw( (string) $a['url'] );
		if ( 0 !== strpos( $url, 'https://' ) || ! wp_http_validate_url( $url ) ) {
			return new WP_Error( 'bad_url', 'Use a public https image address.' );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $url, 20 ); // wp_safe_remote_get: refuses private / local addresses
		if ( is_wp_error( $tmp ) ) {
			return new WP_Error( 'download', 'Could not download: ' . $tmp->get_error_message() );
		}
		if ( filesize( $tmp ) > 8 * MB_IN_BYTES ) {
			@unlink( $tmp ); // phpcs:ignore
			return new WP_Error( 'too_big', 'Image is larger than 8 MB.' );
		}
		$mime = function_exists( 'wp_get_image_mime' ) ? wp_get_image_mime( $tmp ) : '';
		$ext  = array( 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif' );
		if ( ! isset( $ext[ $mime ] ) ) {
			@unlink( $tmp ); // phpcs:ignore
			return new WP_Error( 'not_image', 'That file is not a jpg, png, webp or gif image.' );
		}
		$base  = sanitize_file_name( pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_FILENAME ) );
		$name  = ( $base ? $base : 'image' ) . '.' . $ext[ $mime ];
		$attach = ! empty( $a['attach'] ) ? (int) $a['attach'] : 0;
		if ( $attach && ! current_user_can( 'edit_post', $attach ) ) {
			$attach = 0;
		}
		$id = media_handle_sideload( array( 'name' => $name, 'tmp_name' => $tmp ), $attach, sanitize_text_field( (string) ( $a['title'] ?? '' ) ) );
		if ( is_wp_error( $id ) ) {
			@unlink( $tmp ); // phpcs:ignore
			return $id;
		}
		update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( (string) $a['alt'] ) );
		if ( $attach && ! empty( $a['featured'] ) ) {
			set_post_thumbnail( $attach, $id );
		}
		return array(
			'_summary' => 'Uploaded image “' . $name . '”' . ( $attach ? ' to #' . $attach : '' ),
			'_undo'    => admin_url( 'post.php?post=' . $id . '&action=edit' ),
			'id'       => $id,
			'url'      => wp_get_attachment_url( $id ),
		);
	}

	/* -- menu ------------------------------------------------------- */

	public static function menu_prepare( $a ) {
		$locs = get_registered_nav_menus();
		$loc  = sanitize_key( (string) ( $a['location'] ?? 'primary' ) );
		if ( ! isset( $locs[ $loc ] ) ) {
			return new WP_Error( 'bad_location', 'Unknown menu location. This theme has: ' . implode( ', ', array_keys( $locs ) ) );
		}
		$items = array_slice( (array) $a['items'], 0, 40 );
		if ( ! $items ) {
			return new WP_Error( 'no_items', 'Give at least one menu item.' );
		}
		$clean = array();
		foreach ( $items as $i => $it ) {
			$page = (int) ( $it['page'] ?? 0 );
			$url  = esc_url_raw( (string) ( $it['url'] ?? '' ) );
			if ( $page && ( ! get_post( $page ) || 'page' !== get_post_type( $page ) ) ) {
				return new WP_Error( 'bad_page', 'Item ' . $i . ': page ' . $page . ' does not exist.' );
			}
			if ( ! $page && ! $url ) {
				return new WP_Error( 'bad_item', 'Item ' . $i . ' needs a page id or a url.' );
			}
			$parent = isset( $it['parent'] ) ? (int) $it['parent'] : -1;
			$clean[] = array(
				'title'  => sanitize_text_field( (string) ( $it['title'] ?? ( $page ? get_the_title( $page ) : '' ) ) ),
				'page'   => $page,
				'url'    => $url,
				'parent' => ( $parent >= 0 && $parent < $i ) ? $parent : -1,
			);
		}
		$name = sanitize_text_field( (string) $a['name'] );
		return array(
			'title'   => 'Set menu “' . $name . '” (' . count( $clean ) . ' items) on ' . $locs[ $loc ],
			'payload' => array( 'name' => $name, 'location' => $loc, 'items' => $clean ),
		);
	}

	public static function menu_execute( $p ) {
		$menu = wp_get_nav_menu_object( $p['name'] );
		$id   = $menu ? (int) $menu->term_id : wp_create_nav_menu( $p['name'] );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		foreach ( (array) wp_get_nav_menu_items( $id, array( 'post_status' => 'any' ) ) as $old ) {
			wp_delete_post( $old->ID, true ); // replace the old items of THIS menu only
		}
		$made = array();
		foreach ( $p['items'] as $i => $it ) {
			$args = array(
				'menu-item-title'     => $it['title'],
				'menu-item-status'    => 'publish',
				'menu-item-parent-id' => ( $it['parent'] >= 0 && isset( $made[ $it['parent'] ] ) ) ? $made[ $it['parent'] ] : 0,
			);
			if ( $it['page'] ) {
				$args['menu-item-object-id'] = $it['page'];
				$args['menu-item-object']    = 'page';
				$args['menu-item-type']      = 'post_type';
			} else {
				$args['menu-item-url']  = $it['url'];
				$args['menu-item-type'] = 'custom';
			}
			$made[ $i ] = wp_update_nav_menu_item( $id, 0, $args );
		}
		$locs                   = (array) get_theme_mod( 'nav_menu_locations', array() );
		$locs[ $p['location'] ] = $id;
		if ( 'primary' === $p['location'] && isset( get_registered_nav_menus()['mobile'] ) && empty( $locs['mobile'] ) ) {
			$locs['mobile'] = $id;
		}
		set_theme_mod( 'nav_menu_locations', $locs );
		return array(
			'_summary' => 'Menu “' . $p['name'] . '” set with ' . count( $made ) . ' items on ' . $p['location'],
			'_undo'    => admin_url( 'nav-menus.php?action=edit&menu=' . $id ),
		);
	}

	/* -- site settings ---------------------------------------------- */

	public static function settings_prepare( $a ) {
		$p    = array();
		$what = array();
		if ( ! empty( $a['front_page'] ) ) {
			$page = get_post( (int) $a['front_page'] );
			if ( ! $page || 'page' !== $page->post_type ) {
				return new WP_Error( 'bad_page', 'front_page must be a page id.' );
			}
			$p['front_page'] = $page->ID;
			$what[]          = 'homepage → “' . get_the_title( $page ) . '”';
		}
		if ( isset( $a['title'] ) && '' !== $a['title'] ) {
			$p['title'] = sanitize_text_field( (string) $a['title'] );
			$what[]     = 'title → “' . $p['title'] . '”';
		}
		if ( isset( $a['tagline'] ) ) {
			$p['tagline'] = sanitize_text_field( (string) $a['tagline'] );
			$what[]       = 'tagline → “' . $p['tagline'] . '”';
		}
		if ( ! $p ) {
			return new WP_Error( 'nothing', 'Give front_page, title or tagline.' );
		}
		return array( 'title' => 'Site settings: ' . implode( ', ', $what ), 'payload' => $p );
	}

	public static function settings_execute( $p ) {
		if ( ! empty( $p['front_page'] ) ) {
			if ( 'publish' !== get_post_status( (int) $p['front_page'] ) ) {
				return new WP_Error( 'not_published', 'Publish that page first, then approve this again.' );
			}
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', (int) $p['front_page'] );
		}
		if ( isset( $p['title'] ) ) {
			update_option( 'blogname', $p['title'] );
		}
		if ( isset( $p['tagline'] ) ) {
			update_option( 'blogdescription', $p['tagline'] );
		}
		return array(
			'_summary' => 'Site settings updated',
			'_undo'    => admin_url( 'options-reading.php' ),
		);
	}
}
