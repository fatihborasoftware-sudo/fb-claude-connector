<?php
/**
 * Build tools added in 0.7.0 — things Claude had to do in a browser during the Engine Lab build:
 *   post_settings_set  featured image, categories and tags of a page or post
 *   media_list         find images already in the media library (id, url, alt)
 *   backup_status      the latest WPvivid backup and its age (read-only)
 * plus site_settings gains "site_icon".
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FBCC_BuildTools {

	public static function init() {
		add_filter( 'fbcc_tools', array( __CLASS__, 'tools' ), 7 );
	}

	private static function obj( array $props, array $required = array() ) {
		$s = array( 'type' => 'object', 'properties' => (object) $props, 'additionalProperties' => false );
		if ( $required ) {
			$s['required'] = $required;
		}
		return $s;
	}

	public static function tools( $t ) {
		$t['post_settings_set'] = array(
			'title'       => 'Featured image, categories, tags',
			'description' => 'Set the featured image (an image id from media_list or media_upload), the categories (names; missing ones are created) and/or the tags of a page or post. Drafts change at once; a PUBLISHED item goes to approval unless the build plan covers it. Rule: a blog post is never published without its featured image.',
			'level'       => 'editor',
			'kind'        => 'write',
			'schema'      => self::obj( array(
				'id'             => array( 'type' => 'integer' ),
				'featured_media' => array( 'type' => 'integer', 'description' => 'Attachment id; 0 removes the featured image' ),
				'categories'     => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Category names (posts only); replaces the current ones' ),
				'tags'           => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Tag names (posts only); replaces the current ones' ),
				'reason'         => array( 'type' => 'string' ),
			), array( 'id', 'reason' ) ),
			'run'         => array( __CLASS__, 'post_settings' ),
			'execute'     => array( __CLASS__, 'post_settings_execute' ),
		);
		$t['media_list'] = array(
			'title'       => 'List images',
			'description' => 'Images in the media library with id, file, url, size and alt text — newest first. Use the id for post_settings_set or site_settings (site_icon).',
			'level'       => 'read',
			'kind'        => 'read',
			'schema'      => self::obj( array(
				'search'   => array( 'type' => 'string' ),
				'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20 ),
			) ),
			'run'         => array( __CLASS__, 'media_list' ),
		);
		$t['backup_status'] = array(
			'title'       => 'Backup status',
			'description' => 'The latest backup made with WPvivid on this site: when, what kind and how old. Check it before any build or plugin change; if it is old, ask the owner for a fresh backup (or take one in the linked browser so the owner can watch).',
			'level'       => 'read',
			'kind'        => 'read',
			'schema'      => self::obj( array() ),
			'run'         => array( __CLASS__, 'backup_status' ),
		);

		// site_settings: also the site icon.
		if ( isset( $t['site_settings'] ) ) {
			$props = (array) $t['site_settings']['schema']['properties'];
			$props['site_icon'] = array( 'type' => 'integer', 'description' => 'Attachment id of a square image (512×512 or larger)' );
			$t['site_settings']['schema']['properties'] = (object) $props;
			$t['site_settings']['description']          = 'Set the homepage (a published page id), the site title, the tagline and/or the site icon (an image id). Goes to the approval queue unless the build plan includes site settings.';
			$t['site_settings']['prepare']              = array( __CLASS__, 'settings_prepare' );
			$t['site_settings']['execute']              = array( __CLASS__, 'settings_execute' );
		}
		return $t;
	}

	/* -- post settings ------------------------------------------------ */

	private static function clean_terms( $list ) {
		return array_values( array_unique( array_filter( array_map( function ( $n ) {
			return sanitize_text_field( (string) $n );
		}, array_slice( (array) $list, 0, 20 ) ) ) ) );
	}

	public static function post_settings( $a ) {
		$p = get_post( (int) $a['id'] );
		if ( ! $p || ! in_array( $p->post_type, array( 'page', 'post' ), true ) ) {
			return new WP_Error( 'not_found', 'No page or post with id ' . (int) $a['id'] . '.' );
		}
		if ( ! current_user_can( 'edit_post', $p->ID ) ) {
			return new WP_Error( 'forbidden', 'claude-agent may not edit this item.' );
		}
		$payload = array( 'id' => $p->ID );
		$what    = array();
		if ( isset( $a['featured_media'] ) ) {
			$m = (int) $a['featured_media'];
			if ( $m && ! wp_attachment_is_image( $m ) ) {
				return new WP_Error( 'bad_image', 'featured_media must be an image id (see media_list).' );
			}
			$payload['featured_media'] = $m;
			$what[] = $m ? 'featured image #' . $m : 'no featured image';
		}
		if ( 'post' === $p->post_type ) {
			if ( isset( $a['categories'] ) ) {
				$payload['categories'] = self::clean_terms( $a['categories'] );
				$what[] = 'categories: ' . implode( ', ', $payload['categories'] );
			}
			if ( isset( $a['tags'] ) ) {
				$payload['tags'] = self::clean_terms( $a['tags'] );
				$what[] = 'tags: ' . implode( ', ', $payload['tags'] );
			}
		}
		if ( ! $what ) {
			return new WP_Error( 'nothing', 'Give featured_media, categories or tags (categories and tags are for posts).' );
		}
		$title = 'Settings of “' . get_the_title( $p ) . '”: ' . implode( ' · ', $what );
		if ( in_array( $p->post_status, array( 'publish', 'private', 'future' ), true ) ) {
			$by_plan = FBCC_Plan::intercept( 'post_settings_set', $title, $payload );
			if ( null !== $by_plan ) {
				return $by_plan;
			}
			$id = FBCC_Gateway::queue( 'post_settings_set', $title . ' (live)', $a['reason'] ?? '', $payload );
			return array(
				'_queued'  => $id,
				'_summary' => $title . ' — waiting for approval #' . $id,
				'queued'   => true,
				'approval' => $id,
				'message'  => 'This item is published, so the change is waiting for the owner to approve it.',
			);
		}
		return self::post_settings_execute( $payload );
	}

	public static function post_settings_execute( $p ) {
		$id   = (int) $p['id'];
		$done = array();
		if ( isset( $p['featured_media'] ) ) {
			if ( $p['featured_media'] ) {
				set_post_thumbnail( $id, (int) $p['featured_media'] );
				$done[] = 'featured image #' . (int) $p['featured_media'];
			} else {
				delete_post_thumbnail( $id );
				$done[] = 'featured image removed';
			}
		}
		if ( isset( $p['categories'] ) ) {
			$ids = array();
			foreach ( $p['categories'] as $name ) {
				$term = term_exists( $name, 'category' );
				if ( ! $term ) {
					$term = wp_insert_term( $name, 'category' );
				}
				if ( is_wp_error( $term ) ) {
					return $term;
				}
				$ids[] = (int) ( is_array( $term ) ? $term['term_id'] : $term );
			}
			wp_set_post_categories( $id, $ids, false );
			$done[] = 'categories ' . implode( ', ', $p['categories'] );
		}
		if ( isset( $p['tags'] ) ) {
			wp_set_post_tags( $id, $p['tags'], false );
			$done[] = 'tags ' . implode( ', ', $p['tags'] );
		}
		return array(
			'_summary' => '“' . get_the_title( $id ) . '”: ' . implode( ' · ', $done ),
			'_undo'    => get_edit_post_link( $id, 'raw' ),
			'id'       => $id,
			'updated'  => $done,
		);
	}

	/* -- media list ----------------------------------------------------- */

	public static function media_list( $a ) {
		$q = new WP_Query( array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image',
			's'              => sanitize_text_field( (string) ( $a['search'] ?? '' ) ),
			'posts_per_page' => min( 50, max( 1, (int) ( $a['per_page'] ?? 20 ) ) ),
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );
		return array( 'items' => array_map( function ( $p ) {
			$meta = wp_get_attachment_metadata( $p->ID );
			return array(
				'id'    => $p->ID,
				'file'  => wp_basename( (string) get_attached_file( $p->ID ) ),
				'url'   => wp_get_attachment_url( $p->ID ),
				'size'  => is_array( $meta ) && ! empty( $meta['width'] ) ? $meta['width'] . '×' . $meta['height'] : '',
				'alt'   => (string) get_post_meta( $p->ID, '_wp_attachment_image_alt', true ),
			);
		}, $q->posts ) );
	}

	/* -- backups ---------------------------------------------------------- */

	public static function backup_status() {
		$list = get_option( 'wpvivid_backup_list', null );
		if ( ! is_array( $list ) ) {
			return array(
				'found'   => false,
				'message' => 'No WPvivid backup list was found. If another backup plugin or the hosting panel makes the backups, ask the owner to confirm a fresh one.',
			);
		}
		$latest = null;
		foreach ( $list as $id => $b ) {
			$t = is_array( $b ) ? (int) ( $b['create_time'] ?? 0 ) : 0;
			if ( $t && ( ! $latest || $t > $latest['t'] ) ) {
				$latest = array( 't' => $t, 'type' => (string) ( $b['type'] ?? '' ), 'id' => (string) $id );
			}
		}
		if ( ! $latest ) {
			return array( 'found' => true, 'count' => count( $list ), 'latest' => null, 'message' => 'WPvivid has no backups listed.' );
		}
		// WPvivid stores create_time in the site's local time; compare like with like.
		$age_h = max( 0, ( current_time( 'timestamp' ) - $latest['t'] ) / HOUR_IN_SECONDS );
		return array(
			'found'      => true,
			'count'      => count( $list ),
			'latest'     => wp_date( 'Y-m-d H:i', $latest['t'] - (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ),
			'type'       => $latest['type'],
			'age_hours'  => round( $age_h, 1 ),
			'fresh'      => $age_h < 2,
			'page'       => admin_url( 'admin.php?page=WPvivid' ),
		);
	}

	/* -- site settings (+ site icon) ------------------------------------ */

	public static function settings_prepare( $a ) {
		$base = array_diff_key( $a, array( 'site_icon' => 1 ) );
		$has  = isset( $base['front_page'] ) || ( isset( $base['title'] ) && '' !== $base['title'] ) || isset( $base['tagline'] );
		$prep = $has ? FBCC_SiteTools::settings_prepare( $base ) : array( 'title' => 'Site settings', 'payload' => array() );
		if ( is_wp_error( $prep ) ) {
			return $prep;
		}
		if ( isset( $a['site_icon'] ) ) {
			$icon = (int) $a['site_icon'];
			if ( $icon && ! wp_attachment_is_image( $icon ) ) {
				return new WP_Error( 'bad_image', 'site_icon must be an image id (see media_list).' );
			}
			$prep['payload']['site_icon'] = $icon;
			$prep['title'] = ( $has ? $prep['title'] . ', ' : 'Site settings: ' ) . ( $icon ? 'site icon → image #' . $icon : 'remove site icon' );
		}
		if ( ! $prep['payload'] ) {
			return new WP_Error( 'nothing', 'Give front_page, title, tagline or site_icon.' );
		}
		return $prep;
	}

	public static function settings_execute( $p ) {
		$base = array_diff_key( $p, array( 'site_icon' => 1 ) );
		if ( $base ) {
			$r = FBCC_SiteTools::settings_execute( $base );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
		}
		if ( isset( $p['site_icon'] ) ) {
			update_option( 'site_icon', (int) $p['site_icon'] );
		}
		return array(
			'_summary' => 'Site settings updated' . ( isset( $p['site_icon'] ) ? ' (site icon ' . ( $p['site_icon'] ? '#' . (int) $p['site_icon'] : 'removed' ) . ')' : '' ),
			'_undo'    => admin_url( 'options-general.php' ),
		);
	}
}
