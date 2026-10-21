<?php
/**
 * Screenshots for Claude (1.3.3): the owner sends Claude a picture of what is wrong, with a note.
 *   Watch Me Live → "Send to Claude": a picture of the page on a phone, tablet or desktop,
 *   a capture of the owner's own screen, or an uploaded picture.
 *   feedback_list   Claude reads what the owner sent (one picture per call)
 *   feedback_done   Claude marks an item as handled, with a short answer the owner sees
 * Pictures are kept in uploads/fbcc-feedback (not in the media library), the last 30 items.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FBCC_Feedback {

	const OPT  = 'fbcc_feedback';
	const KEEP = 30;
	const MAX  = 8388608; // 8 MB per picture.

	public static function init() {
		add_filter( 'fbcc_tools', array( __CLASS__, 'tools' ), 9 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/* -- storage ---------------------------------------------------------- */

	public static function items() {
		$v = get_option( self::OPT, array() );
		return is_array( $v ) ? $v : array();
	}

	private static function save( array $items ) {
		$items = array_slice( $items, -self::KEEP, null, true );
		update_option( self::OPT, $items, false );
		self::prune( $items );
	}

	public static function open_count() {
		return count( array_filter( self::items(), function ( $i ) {
			return 'open' === $i['status'];
		} ) );
	}

	private static function dir() {
		$u   = wp_upload_dir();
		$dir = trailingslashit( $u['basedir'] ) . 'fbcc-feedback';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			// Pictures are read from disk by the connector only, never served to visitors.
			file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			file_put_contents( $dir . '/index.php', "<?php // Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		return $dir;
	}

	private static function store_file( $bytes, $ext ) {
		$name = wp_generate_password( 24, false ) . '.' . $ext;
		file_put_contents( self::dir() . '/' . $name, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return $name;
	}

	/** Deletes pictures that no longer belong to a kept item. */
	private static function prune( array $items ) {
		$keep = array_filter( wp_list_pluck( $items, 'file' ) );
		foreach ( array_merge( (array) glob( self::dir() . '/*.jpg' ), (array) glob( self::dir() . '/*.png' ) ) as $f ) {
			if ( ! in_array( basename( $f ), $keep, true ) ) {
				wp_delete_file( $f );
			}
		}
	}

	/* -- the "Send to Claude" button (administrators, REST nonce) ---------- */

	public static function routes() {
		register_rest_route( FBCC_NS, '/claude/feedback', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'receive' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		) );
	}

	public static function receive( WP_REST_Request $req ) {
		$note   = sanitize_textarea_field( (string) $req->get_param( 'note' ) );
		$url    = esc_url_raw( (string) $req->get_param( 'url' ) );
		$device = (string) $req->get_param( 'device' );
		$device = in_array( $device, array( 'mobile', 'tablet', 'desktop', 'screen', 'upload' ), true ) ? $device : 'mobile';
		$image  = (string) $req->get_param( 'image' );
		$file   = '';
		// What the owner boxed in the preview: page id, the Elementor element under it, its widget and text.
		$target = array();
		foreach ( (array) $req->get_param( 'target' ) as $k => $v ) {
			if ( in_array( $k, array( 'page', 'element', 'widget', 'parents', 'text', 'view' ), true ) ) {
				$target[ $k ] = is_array( $v ) ? array_map( 'sanitize_text_field', array_slice( $v, 0, 6 ) ) : sanitize_text_field( wp_trim_words( (string) $v, 30 ) );
			}
		}

		if ( '' !== $image ) {
			// A picture from the owner: their own screen or an upload, as a data URL.
			if ( ! preg_match( '#^data:image/(png|jpe?g);base64,#', $image, $m ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'message' => 'Please send a PNG or JPG picture.' ), 200 );
			}
			$bytes = base64_decode( substr( $image, strlen( $m[0] ) ), true );
			if ( false === $bytes || strlen( $bytes ) > self::MAX || ! @getimagesizefromstring( $bytes ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
				return new WP_REST_Response( array( 'ok' => false, 'message' => 'This picture could not be read, or it is bigger than 8 MB.' ), 200 );
			}
			$file = self::store_file( $bytes, 'png' === $m[1] ? 'png' : 'jpg' );
		} else {
			$shot = FBCC_Elementor::mshot( $url, $device );
			if ( is_wp_error( $shot ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'message' => $shot->get_error_message() ), 200 );
			}
			$url = $shot['url'];
			// While mShots is still busy the item is kept without a picture; feedback_list takes it later.
			if ( '' !== $shot['jpeg'] ) {
				$file = self::store_file( $shot['jpeg'], 'jpg' );
			}
		}
		if ( '' === $note && '' === $file && '' === $url ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'Write what Claude should fix, or add a picture.' ), 200 );
		}

		$items = self::items();
		$id    = $items ? max( array_keys( $items ) ) + 1 : 1;
		$items[ $id ] = array(
			'id'     => $id,
			'time'   => time(),
			'by'     => wp_get_current_user()->display_name,
			'url'    => $url,
			'device' => $device,
			'note'   => $note,
			'file'   => $file,
			'status' => 'open',
			'answer' => '',
			'target' => $target,
		);
		self::save( $items );
		FBCC_Store::log( 'feedback', 'Owner sent screenshot #' . $id . ( $note ? ': ' . wp_trim_words( $note, 12 ) : '' ), 'read', '', '' );
		return new WP_REST_Response( array( 'ok' => true, 'id' => $id, 'message' => 'Sent to Claude as #' . $id . '.' ), 200 );
	}

	/* -- tools -------------------------------------------------------------- */

	private static function obj( array $props, array $required = array() ) {
		$s = array( 'type' => 'object', 'properties' => (object) $props, 'additionalProperties' => false );
		if ( $required ) {
			$s['required'] = $required;
		}
		return $s;
	}

	public static function tools( $t ) {
		$t['feedback_list'] = array(
			'title'       => 'Read the owner’s screenshots',
			'description' => 'Screenshots and notes the owner sent from Watch Me Live ("Send to Claude") about things to fix. Usually the owner boxed one part of the page; "boxed" then names the page id, the Elementor element id under the box (use it with elementor_get / elementor_set), its widget type, its parents and the view (mobile, tablet or desktop). Without id: the list of open items (all=true also shows handled ones). With id: that item with its picture. Check it at the start of a task and whenever the owner says they sent a screenshot; fix what each one shows, then call feedback_done.',
			'level'       => 'read',
			'kind'        => 'read',
			'schema'      => self::obj( array(
				'id'  => array( 'type' => 'integer', 'description' => 'Open this item with its picture' ),
				'all' => array( 'type' => 'boolean', 'default' => false ),
			) ),
			'run'         => array( __CLASS__, 'tool_list' ),
		);
		$t['feedback_done'] = array(
			'title'       => 'Answer a screenshot',
			'description' => 'Mark an item from feedback_list as handled, with a short answer the owner sees in Watch Me Live (what you changed, or why not).',
			'level'       => 'read',
			'kind'        => 'status',
			'schema'      => self::obj( array(
				'id'     => array( 'type' => 'integer' ),
				'answer' => array( 'type' => 'string' ),
			), array( 'id', 'answer' ) ),
			'run'         => array( __CLASS__, 'tool_done' ),
		);
		return $t;
	}

	private static function summary( array $i ) {
		return array(
			'id'          => $i['id'],
			'sent'        => wp_date( 'Y-m-d H:i', $i['time'] ),
			'by'          => $i['by'],
			'page'        => $i['url'],
			'device'      => $i['device'],
			'note'        => $i['note'],
			'has_picture' => '' !== $i['file'] || '' !== $i['url'],
			'status'      => $i['status'],
			'answer'      => $i['answer'],
			'boxed'       => $i['target'] ?? array(),
		);
	}

	public static function tool_list( $a ) {
		$items = self::items();
		if ( empty( $a['id'] ) ) {
			$all  = ! empty( $a['all'] );
			$list = array();
			foreach ( array_reverse( $items ) as $i ) {
				if ( $all || 'open' === $i['status'] ) {
					$list[] = self::summary( $i );
				}
			}
			return array(
				'items'   => $list,
				'open'    => self::open_count(),
				'message' => $list ? 'Open one with feedback_list id=… to see its picture.' : 'The owner has not sent anything new.',
			);
		}
		$id = (int) $a['id'];
		if ( ! isset( $items[ $id ] ) ) {
			return new WP_Error( 'not_found', 'No screenshot #' . $id . '.' );
		}
		$i = $items[ $id ];
		if ( '' === $i['file'] && '' !== $i['url'] ) {
			// mShots was still busy when the owner sent it: take the picture now.
			$shot = FBCC_Elementor::mshot( $i['url'], in_array( $i['device'], array( 'mobile', 'tablet', 'desktop' ), true ) ? $i['device'] : 'mobile' );
			if ( ! is_wp_error( $shot ) && '' !== $shot['jpeg'] ) {
				$i['file']     = self::store_file( $shot['jpeg'], 'jpg' );
				$items[ $id ]  = $i;
				self::save( $items );
			}
		}
		$out  = self::summary( $i );
		$path = '' !== $i['file'] ? self::dir() . '/' . $i['file'] : '';
		if ( $path && is_readable( $path ) ) {
			$out['image'] = array(
				'data'     => base64_encode( (string) file_get_contents( $path ) ), // phpcs:ignore WordPress.WP.AlternativeFunctions
				'mimeType' => '.png' === substr( $path, -4 ) ? 'image/png' : 'image/jpeg',
			);
		} elseif ( '' !== $i['url'] ) {
			$out['message'] = 'The picture is still being made. Call again in about 20 seconds.';
		}
		return $out;
	}

	public static function tool_done( $a ) {
		$items = self::items();
		$id    = (int) $a['id'];
		if ( ! isset( $items[ $id ] ) ) {
			return new WP_Error( 'not_found', 'No screenshot #' . $id . '.' );
		}
		$items[ $id ]['status'] = 'done';
		$items[ $id ]['answer'] = sanitize_textarea_field( (string) $a['answer'] );
		self::save( $items );
		FBCC_Store::log( 'feedback_done', 'Answered screenshot #' . $id . ': ' . wp_trim_words( $items[ $id ]['answer'], 14 ), 'done', '', '' );
		return array( 'id' => $id, 'status' => 'done', 'open' => self::open_count() );
	}

	/** For the Watch Me Live feed: the last items with their status and Claude's answer. */
	public static function recent() {
		$out = array();
		foreach ( array_slice( array_reverse( self::items() ), 0, 8 ) as $i ) {
			$out[] = array(
				'id'     => $i['id'],
				'time'   => wp_date( 'H:i', $i['time'] ),
				'note'   => $i['note'],
				'device' => $i['device'],
				'status' => $i['status'],
				'answer' => $i['answer'],
			);
		}
		return $out;
	}
}
