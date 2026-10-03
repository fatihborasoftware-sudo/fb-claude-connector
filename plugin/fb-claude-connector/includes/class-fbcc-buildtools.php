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

	const JOB = 'fbcc_backup_job';

	public static function init() {
		add_filter( 'fbcc_tools', array( __CLASS__, 'tools' ), 7 );
		// Background runner for WPvivid backups (started by backup_create).
		add_action( 'wp_ajax_nopriv_fbcc_run_backup', array( __CLASS__, 'ajax_run_backup' ) );
		add_action( 'wp_ajax_fbcc_run_backup', array( __CLASS__, 'ajax_run_backup' ) );
		add_action( 'fbcc_run_backup_cron', array( __CLASS__, 'run_backup_task' ) );
		add_action( 'admin_post_fbcc_backup_now', array( __CLASS__, 'action_backup_now' ) );
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
		$t['backup_create'] = array(
			'title'       => 'Take a backup',
			'description' => 'Start a full WPvivid backup (database + files, kept on this server). It runs in the background — usually 1 to 3 minutes; follow it with backup_status until "running" is false and the latest backup is fresh. Take one before every build plan, plugin install or theme change.',
			'level'       => 'editor',
			'kind'        => 'write',
			'schema'      => self::obj( array(
				'what'   => array( 'type' => 'string', 'enum' => array( 'files+db', 'db', 'files' ), 'default' => 'files+db' ),
				'reason' => array( 'type' => 'string' ),
			), array( 'reason' ) ),
			'run'         => array( __CLASS__, 'backup_create' ),
		);
		$t['media_upload_data'] = array(
			'title'       => 'Upload an image from chat',
			'description' => 'Put an image you made in this chat into the media library: send it as base64 (a data: URI is fine). jpg, png, webp or gif, at most 6 MB — prefer webp or jpg under 1 MB. Optionally attach it to a page/post and make it the featured image. Returns the id and url.',
			'level'       => 'editor',
			'kind'        => 'write',
			'schema'      => self::obj( array(
				'data'     => array( 'type' => 'string', 'description' => 'Base64 image data or a data: URI' ),
				'filename' => array( 'type' => 'string', 'description' => 'e.g. banner-spring-sale.webp' ),
				'alt'      => array( 'type' => 'string', 'description' => 'Alt text (describe the image)' ),
				'title'    => array( 'type' => 'string' ),
				'attach'   => array( 'type' => 'integer', 'description' => 'Page/post id to attach to' ),
				'featured' => array( 'type' => 'boolean', 'default' => false ),
			), array( 'data', 'filename', 'alt' ) ),
			'run'         => array( __CLASS__, 'media_upload_data' ),
		);
		$t['content_trash'] = array(
			'title'       => 'Move to Trash',
			'description' => 'Move a page or post to the WordPress Trash (it can be restored from Trash for 30 days — nothing is deleted for good). Use it for WordPress\'s default "Hello world!" post and "Sample Page" on a fresh site. Never the homepage or the posts page. Goes to approvals unless the build plan lists the id (plan field "trash").',
			'level'       => 'editor',
			'kind'        => 'approval',
			'schema'      => self::obj( array(
				'id'     => array( 'type' => 'integer' ),
				'reason' => array( 'type' => 'string' ),
			), array( 'id', 'reason' ) ),
			'prepare'     => array( __CLASS__, 'trash_prepare' ),
			'execute'     => array( __CLASS__, 'trash_execute' ),
		);
		$t['backup_status'] = array(
			'title'       => 'Backup status',
			'description' => 'The latest WPvivid backup on this site (when, kind, age) and whether a backup is running right now. Check it before any build or plugin change; if it is not fresh, run backup_create first.',
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

	public static function wpvivid_ready() {
		global $wpvivid_plugin;
		return class_exists( 'WPvivid_Interface_MainWP' ) && is_object( $wpvivid_plugin ) && isset( $wpvivid_plugin->backup2 ) && has_filter( 'wpvivid_prepare_backup_mainwp' );
	}

	private static function wpvivid_running() {
		global $wpvivid_plugin;
		try {
			return self::wpvivid_ready() && $wpvivid_plugin->backup2->is_tasks_backup_running();
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/** Latest backup from WPvivid's own list (create_time is a Unix timestamp). */
	public static function latest_backup() {
		$list = get_option( 'wpvivid_backup_list', null );
		if ( ! is_array( $list ) ) {
			return null;
		}
		$latest = null;
		foreach ( $list as $id => $b ) {
			$t = is_array( $b ) ? (int) ( $b['create_time'] ?? 0 ) : 0;
			if ( $t && ( ! $latest || $t > $latest['t'] ) ) {
				$latest = array( 't' => $t, 'type' => (string) ( $b['type'] ?? '' ), 'id' => (string) $id );
			}
		}
		return $latest ? array_merge( $latest, array( 'count' => count( $list ) ) ) : array( 't' => 0, 'count' => count( $list ) );
	}

	public static function backup_status() {
		$latest  = self::latest_backup();
		$running = self::wpvivid_running();
		$job     = get_option( self::JOB );
		if ( null === $latest && ! self::wpvivid_ready() ) {
			return array(
				'found'   => false,
				'message' => 'WPvivid is not active. Install it with plugins_install slug "wpvivid-backuprestore" (on a build, list it first in the plan\'s plugins), then run backup_create. If another plugin or the hosting panel makes the backups, ask the owner to confirm a fresh one instead.',
			);
		}
		$out = array(
			'found'   => true,
			'running' => $running,
			'count'   => $latest ? (int) $latest['count'] : 0,
			'page'    => admin_url( 'admin.php?page=WPvivid' ),
		);
		if ( $latest && $latest['t'] ) {
			$age             = max( 0, time() - $latest['t'] ) / HOUR_IN_SECONDS;
			$out['latest']   = wp_date( 'Y-m-d H:i', $latest['t'] );
			$out['type']     = $latest['type'];
			$out['age_hours'] = round( $age, 2 );
			$out['fresh']    = $age < 2;
		} else {
			$out['latest'] = null;
			$out['fresh']  = false;
		}
		if ( is_array( $job ) && time() - (int) $job['started'] < DAY_IN_SECONDS ) {
			$done             = $latest && $latest['t'] >= (int) $job['started'] - 5;
			$out['last_job']  = array(
				'started' => wp_date( 'Y-m-d H:i:s', (int) $job['started'] ),
				'state'   => $done ? 'finished' : ( $running ? 'running' : ( time() - (int) $job['started'] > 900 ? 'failed or stalled — check WPvivid → Logs' : 'starting' ) ),
			);
		}
		return $out;
	}

	/* -- take a backup (WPvivid) --------------------------------------- */

	public static function backup_create( $a ) {
		if ( ! self::wpvivid_ready() ) {
			return new WP_Error( 'no_wpvivid', 'WPvivid Backup is not active on this site. Install it first with plugins_install slug "wpvivid-backuprestore" (list it in the build plan\'s plugins on a fresh site), then call backup_create again.' );
		}
		if ( self::wpvivid_running() ) {
			return array( 'started' => false, 'running' => true, 'message' => 'A backup is already running. Follow it with backup_status.' );
		}
		$what = in_array( $a['what'] ?? 'files+db', array( 'files+db', 'db', 'files' ), true ) ? ( $a['what'] ?? 'files+db' ) : 'files+db';
		$r    = self::start_wpvivid( $what );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return array(
			'_summary' => 'Backup started (WPvivid, ' . $what . ')' . ( ! empty( $a['reason'] ) ? ' — ' . sanitize_text_field( $a['reason'] ) : '' ),
			'_undo'    => admin_url( 'admin.php?page=WPvivid' ),
			'started'  => true,
			'task_id'  => $r,
			'message'  => 'Backup is running in the background. Call backup_status in about a minute; it is done when "running" is false and "fresh" is true.',
		);
	}

	/** Prepares the WPvivid task and hands it to a background request. Returns the task id. */
	public static function start_wpvivid( $what = 'files+db' ) {
		$prev_user = get_current_user_id();
		$job_user  = $prev_user && user_can( $prev_user, 'manage_options' ) ? $prev_user : self::an_admin();
		wp_set_current_user( $job_user );
		try {
			$ret = apply_filters( 'wpvivid_prepare_backup_mainwp', array(
				'backup' => array(
					'backup_files' => $what,
					'local'        => '1',
					'remote'       => '0',
					'ismerge'      => '1',
					'lock'         => '0',
					'type'         => 'Manual',
				),
			) );
		} catch ( \Throwable $e ) {
			$ret = array( 'error' => $e->getMessage() );
		}
		wp_set_current_user( $prev_user );
		if ( ! is_array( $ret ) || empty( $ret['task_id'] ) || ( isset( $ret['result'] ) && 'success' !== $ret['result'] ) ) {
			return new WP_Error( 'backup_prepare', 'WPvivid did not start: ' . ( is_array( $ret ) && ! empty( $ret['error'] ) ? wp_strip_all_tags( (string) $ret['error'] ) : 'unknown reason' ) );
		}
		$task = sanitize_key( $ret['task_id'] );
		$key  = wp_generate_password( 32, false, false );
		update_option( self::JOB, array( 'task' => $task, 'started' => time(), 'key_hash' => wp_hash_password( $key ), 'user' => $job_user, 'what' => $what ), false );

		// Kick off the backup in its own request (like WPvivid's own button), with wp-cron as a fallback.
		wp_remote_post( admin_url( 'admin-ajax.php' ), array(
			'timeout'   => 0.01,
			'blocking'  => false,
			'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			'body'      => array( 'action' => 'fbcc_run_backup', 'task' => $task, 'key' => $key ),
		) );
		wp_schedule_single_event( time() + 45, 'fbcc_run_backup_cron', array( $task ) );
		return $task;
	}

	private static function an_admin() {
		$u = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
		return $u ? (int) $u[0] : 0;
	}

	public static function ajax_run_backup() {
		$job  = get_option( self::JOB );
		$task = isset( $_POST['task'] ) ? sanitize_key( wp_unslash( $_POST['task'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- one-time key below
		$key  = isset( $_POST['key'] ) ? (string) wp_unslash( $_POST['key'] ) : ''; // phpcs:ignore
		if ( ! is_array( $job ) || $task !== $job['task'] || empty( $job['key_hash'] ) || ! wp_check_password( $key, $job['key_hash'] ) ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		$job['key_hash'] = '';
		update_option( self::JOB, $job, false ); // the key works once
		self::run_backup_task( $task );
		wp_die();
	}

	public static function run_backup_task( $task ) {
		$job = get_option( self::JOB );
		if ( ! is_array( $job ) || $task !== $job['task'] || ! empty( $job['ran'] ) || ! self::wpvivid_ready() ) {
			return;
		}
		$job['ran'] = time();
		update_option( self::JOB, $job, false );
		ignore_user_abort( true );
		wp_set_current_user( (int) $job['user'] );
		try {
			apply_filters( 'wpvivid_backup_now_mainwp', array( 'task_id' => $task ) );
		} catch ( \Throwable $e ) {
			FBCC_Store::log( 'backup_create', 'Backup failed: ' . $e->getMessage(), 'error' );
		}
	}

	/** Overview card: latest backup + "Take a backup now". */
	public static function card() {
		$st = self::backup_status();
		echo '<section class="fbcc-card"><div class="fbcc-head"><h2>Backups</h2>';
		if ( ! empty( $st['found'] ) && isset( $st['fresh'] ) ) {
			echo '<span class="fbcc-pill ' . ( $st['fresh'] ? 'fbcc-green' : 'fbcc-red' ) . '">' . ( $st['fresh'] ? 'Fresh' : 'Old' ) . '</span>';
		}
		echo '</div>';
		if ( empty( $st['found'] ) ) {
			echo '<p class="fbcc-muted">' . esc_html( $st['message'] ) . '</p></section>';
			return;
		}
		echo '<dl class="fbcc-dl"><dt>Latest backup</dt><dd>' . ( $st['latest'] ? esc_html( $st['latest'] ) . ' · ' . esc_html( human_time_diff( time() - (int) round( $st['age_hours'] * HOUR_IN_SECONDS ) ) ) : 'No backup found.' ) . '</dd></dl>';
		if ( ! empty( $st['running'] ) ) {
			echo '<p><strong>A backup is running…</strong></p>';
		} else {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="fbcc_backup_now">';
			wp_nonce_field( 'fbcc_backup_now' );
			echo '<p><button class="button">Take a backup now</button> <a href="' . esc_url( $st['page'] ) . '">WPvivid ↗</a></p></form>';
		}
		echo '</section>';
	}

	public static function action_backup_now() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		check_admin_referer( 'fbcc_backup_now' );
		$r = self::start_wpvivid( 'files+db' );
		FBCC_Store::log( 'backup_create', is_wp_error( $r ) ? 'Backup could not start: ' . $r->get_error_message() : 'Backup started by ' . wp_get_current_user()->display_name, is_wp_error( $r ) ? 'error' : 'done' );
		wp_safe_redirect( admin_url( 'admin.php?page=fbcc' ) );
		exit;
	}

	/* -- image from chat ------------------------------------------------- */

	public static function media_upload_data( $a ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'forbidden', 'claude-agent may not upload files.' );
		}
		$data = (string) $a['data'];
		if ( preg_match( '#^data:image/[a-z0-9.+-]+;base64,#i', $data, $m ) ) {
			$data = substr( $data, strlen( $m[0] ) );
		}
		$bin = base64_decode( preg_replace( '/\s+/', '', $data ), true );
		if ( false === $bin || strlen( $bin ) < 64 ) {
			return new WP_Error( 'bad_data', 'data is not valid base64 image data.' );
		}
		if ( strlen( $bin ) > 6 * MB_IN_BYTES ) {
			return new WP_Error( 'too_big', 'Image is larger than 6 MB.' );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$tmp = wp_tempnam( 'fbcc-img' );
		if ( ! $tmp || false === file_put_contents( $tmp, $bin ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			return new WP_Error( 'tmp', 'Could not write a temporary file.' );
		}
		$mime = function_exists( 'wp_get_image_mime' ) ? wp_get_image_mime( $tmp ) : '';
		$ext  = array( 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif' );
		if ( ! isset( $ext[ $mime ] ) ) {
			@unlink( $tmp ); // phpcs:ignore
			return new WP_Error( 'not_image', 'That data is not a jpg, png, webp or gif image.' );
		}
		$base = sanitize_file_name( pathinfo( (string) $a['filename'], PATHINFO_FILENAME ) );
		$name = ( $base ? $base : 'image' ) . '.' . $ext[ $mime ];
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
		$featured = false;
		if ( $attach && ! empty( $a['featured'] ) ) {
			if ( 'publish' === get_post_status( $attach ) ) {
				$res      = self::post_settings( array( 'id' => $attach, 'featured_media' => $id, 'reason' => 'Featured image uploaded from chat' ) );
				$featured = is_array( $res ) && empty( $res['queued'] ) ? true : 'waiting for approval';
			} else {
				set_post_thumbnail( $attach, $id );
				$featured = true;
			}
		}
		$meta = wp_get_attachment_metadata( $id );
		return array(
			'_summary' => 'Uploaded image “' . $name . '” from chat' . ( $attach ? ' to #' . $attach : '' ),
			'_undo'    => admin_url( 'post.php?post=' . $id . '&action=edit' ),
			'id'       => $id,
			'url'      => wp_get_attachment_url( $id ),
			'size'     => is_array( $meta ) && ! empty( $meta['width'] ) ? $meta['width'] . '×' . $meta['height'] : '',
			'featured' => $featured,
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

	/* -- content_trash (1.2.0) -------------------------------------------- */

	public static function trash_prepare( $a ) {
		$id   = (int) $a['id'];
		$post = get_post( $id );
		if ( ! $post || ! in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
			return new WP_Error( 'not_found', 'No page or post with id ' . $id . '.' );
		}
		if ( 'trash' === $post->post_status ) {
			return new WP_Error( 'already', '“' . $post->post_title . '” is already in the Trash.' );
		}
		if ( in_array( $id, array( (int) get_option( 'page_on_front' ), (int) get_option( 'page_for_posts' ) ), true ) ) {
			return new WP_Error( 'protected', '“' . $post->post_title . '” is the homepage or the posts page — set another one first.' );
		}
		return array(
			'title'   => 'Move ' . ( 'page' === $post->post_type ? 'page' : 'post' ) . ' “' . $post->post_title . '” (#' . $id . ', ' . $post->post_status . ') to the Trash',
			'payload' => array( 'id' => $id ),
		);
	}

	public static function trash_execute( $p ) {
		$id   = (int) $p['id'];
		$post = get_post( $id );
		if ( ! $post ) {
			return new WP_Error( 'not_found', 'It no longer exists.' );
		}
		if ( in_array( $id, array( (int) get_option( 'page_on_front' ), (int) get_option( 'page_for_posts' ) ), true ) ) {
			return new WP_Error( 'protected', 'This is now the homepage or the posts page; it was not moved.' );
		}
		$r = wp_trash_post( $id );
		if ( ! $r ) {
			return new WP_Error( 'trash_failed', 'WordPress could not move it to the Trash.' );
		}
		return array(
			'_summary' => '“' . $post->post_title . '” moved to the Trash',
			'_undo'    => admin_url( 'edit.php?post_status=trash&post_type=' . $post->post_type ),
		);
	}
}
