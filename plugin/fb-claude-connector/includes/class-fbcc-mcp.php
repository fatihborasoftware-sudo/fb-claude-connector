<?php
/**
 * MCP server endpoint (Streamable HTTP, JSON responses, stateless):
 *   POST /wp-json/fbsa/v1/mcp
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FBCC_MCP {

	const VERSIONS   = array( '2025-11-25', '2025-06-18', '2025-03-26' );
	const RATE_LIMIT = 120; // calls per minute per token

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'rest_exposed_cors_headers', function ( $h ) {
			$h[] = 'WWW-Authenticate';
			return $h;
		} );
	}

	public static function routes() {
		register_rest_route( FBCC_NS, '/mcp', array(
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle' ),
				'permission_callback' => '__return_true', // OAuth bearer checked in handle()
			),
			array(
				'methods'             => 'GET, DELETE',
				'callback'            => array( __CLASS__, 'not_allowed' ),
				'permission_callback' => '__return_true',
			),
		) );

		// Self-test: does the server pass the Authorization header to WordPress? Never echoes the value.
		register_rest_route( FBCC_NS, '/claude/auth-check', array(
			'methods'             => 'GET',
			'callback'            => function ( WP_REST_Request $req ) {
				return new WP_REST_Response( array( 'header_reaches_wordpress' => '' !== self::bearer( $req ) ) );
			},
			'permission_callback' => '__return_true',
		) );

		// Live status for the admin bar / AI Engine widget (logged-in admins only).
		register_rest_route( FBCC_NS, '/claude/status', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'status' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		) );
	}

	public static function not_allowed() {
		FBCC_Store::trace( 'mcp', array( 'result' => '405 (no SSE)' ) );
		$r = new WP_REST_Response( array( 'error' => 'This MCP server does not offer an SSE stream. Use POST.' ), 405 );
		$r->header( 'Allow', 'POST' );
		return $r;
	}

	public static function status() {
		$t = FBCC_Store::task();
		return new WP_REST_Response( array(
			'working'  => (bool) $t,
			'task'     => $t,
			'pending'  => FBCC_Store::pending_count(),
			'enabled'  => (bool) FBCC_Store::settings()['enabled'],
		) );
	}

	/* ------------------------------------------------------------ */
	/* Auth                                                         */
	/* ------------------------------------------------------------ */

	private static function bearer( WP_REST_Request $req ) {
		$h = $req->get_header( 'authorization' );
		if ( ! $h ) {
			foreach ( array( 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION' ) as $k ) {
				if ( ! empty( $_SERVER[ $k ] ) ) {
					$h = wp_unslash( $_SERVER[ $k ] ); // phpcs:ignore
					break;
				}
			}
		}
		if ( ! $h && function_exists( 'getallheaders' ) ) {
			foreach ( (array) getallheaders() as $k => $v ) {
				if ( 'authorization' === strtolower( $k ) ) {
					$h = $v;
				}
			}
		}
		return ( $h && 0 === stripos( $h, 'bearer ' ) ) ? trim( substr( $h, 7 ) ) : '';
	}

	private static function why_invalid( $raw ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT kind, revoked, expires_at FROM {$wpdb->prefix}fbcc_tokens WHERE token_hash = %s",
			FBCC_Store::hash( $raw )
		) );
		if ( ! $row ) {
			return 'unknown token';
		}
		if ( (int) $row->revoked ) {
			return 'revoked';
		}
		return 'expired ' . $row->expires_at;
	}

	private static function unauthorized( $msg ) {
		$r = new WP_REST_Response( array(
			'jsonrpc' => '2.0',
			'id'      => null,
			'error'   => array( 'code' => -32001, 'message' => $msg ),
		), 401 );
		$r->header( 'WWW-Authenticate', 'Bearer resource_metadata="' . FBCC_OAuth::resource_metadata_url() . '", error="invalid_token", error_description="' . $msg . '"' );
		return $r;
	}

	/* ------------------------------------------------------------ */
	/* JSON-RPC                                                     */
	/* ------------------------------------------------------------ */

	public static function handle( WP_REST_Request $req ) {
		$raw   = self::bearer( $req );
		$token = FBCC_Store::find_token( $raw, 'access' );
		$peek  = json_decode( $req->get_body(), true );
		FBCC_Store::trace( 'mcp', array(
			'rpc'    => is_array( $peek ) && isset( $peek['method'] ) ? substr( (string) $peek['method'], 0, 40 ) : ( is_array( $peek ) && isset( $peek[0] ) ? 'batch' : 'no-json' ),
			'header' => '' !== $raw ? ( 0 === strpos( $raw, 'fbcc_at_' ) ? 'bearer fbcc_at' : 'bearer other' ) : 'none',
			'token'  => $token ? 'valid' : ( '' !== $raw && 0 === strpos( $raw, 'fbcc_at_' ) ? self::why_invalid( $raw ) : '-' ),
			'ct'     => substr( (string) $req->get_header( 'content_type' ), 0, 40 ),
			'accept' => substr( (string) $req->get_header( 'accept' ), 0, 50 ),
		) );
		if ( ! $token ) {
			return self::unauthorized( 'Sign in required' );
		}
		if ( ! FBCC_Store::settings()['enabled'] ) {
			return self::unauthorized( 'Connector is switched off' );
		}
		$approver = get_userdata( (int) $token->user_id );
		if ( ! $approver || ! user_can( $approver, 'manage_options' ) ) {
			FBCC_Store::revoke_token( $token->id );
			return self::unauthorized( 'Approving administrator no longer has access' );
		}

		$rk = 'fbcc_rl_' . $token->id . '_' . gmdate( 'YmdHi' );
		$n  = (int) get_transient( $rk );
		if ( $n >= self::RATE_LIMIT ) {
			return new WP_REST_Response( array( 'jsonrpc' => '2.0', 'id' => null, 'error' => array( 'code' => -32000, 'message' => 'Rate limit reached, slow down.' ) ), 429 );
		}
		set_transient( $rk, $n + 1, 90 );
		FBCC_Store::touch_token( $token->id );

		$msg = json_decode( $req->get_body(), true );
		if ( ! is_array( $msg ) ) {
			return new WP_REST_Response( self::err( null, -32700, 'Parse error' ), 400 );
		}

		// Batches (older protocol versions).
		if ( isset( $msg[0] ) ) {
			$out = array();
			foreach ( $msg as $m ) {
				$r = is_array( $m ) ? self::dispatch( $m, $token ) : self::err( null, -32600, 'Invalid request' );
				if ( null !== $r ) {
					$out[] = $r;
				}
			}
			return $out ? new WP_REST_Response( $out, 200 ) : self::accepted();
		}

		$r = self::dispatch( $msg, $token );
		FBCC_Store::add_bytes( strlen( $req->get_body() ) + ( null === $r ? 0 : strlen( (string) wp_json_encode( $r ) ) ) );
		return null === $r ? self::accepted() : new WP_REST_Response( $r, 200 );
	}

	/** 202 with an empty body for notifications / responses. */
	private static function accepted() {
		status_header( 202 );
		header( 'Content-Length: 0' );
		exit;
	}

	private static function err( $id, $code, $message ) {
		return array( 'jsonrpc' => '2.0', 'id' => $id, 'error' => array( 'code' => $code, 'message' => $message ) );
	}

	private static function ok( $id, $result ) {
		return array( 'jsonrpc' => '2.0', 'id' => $id, 'result' => $result );
	}

	private static function dispatch( array $m, $token ) {
		$method = isset( $m['method'] ) ? (string) $m['method'] : '';
		$has_id = array_key_exists( 'id', $m );
		$id     = $has_id ? $m['id'] : null;
		$params = isset( $m['params'] ) && is_array( $m['params'] ) ? $m['params'] : array();

		if ( ! $method ) {
			return $has_id ? null : null; // a response from the client: ignore.
		}
		if ( ! $has_id ) {
			return null; // notification (e.g. notifications/initialized)
		}

		switch ( $method ) {
			case 'initialize':
				$want = isset( $params['protocolVersion'] ) ? (string) $params['protocolVersion'] : '';
				return self::ok( $id, array(
					'protocolVersion' => in_array( $want, self::VERSIONS, true ) ? $want : self::VERSIONS[0],
					'capabilities'    => array( 'tools' => array( 'listChanged' => false ) ),
					'serverInfo'      => array(
						'name'    => 'fb-claude-connector',
						'title'   => get_bloginfo( 'name' ) . ' (FB AI Engine)',
						'version' => FBCC_VERSION,
					),
					'instructions'    => self::instructions(),
				) );

			case 'ping':
				return self::ok( $id, new stdClass() );

			case 'tools/list':
				$tools = array();
				$open   = FBCC_Gateway::visible_tools();
				$levels = array( 'read' => 'Read-only', 'editor' => 'Content editor', 'maintainer' => 'Site maintainer' );
				$off    = (array) FBCC_Store::settings()['tools_off'];
				foreach ( FBCC_Tools::all() as $name => $t ) {
					if ( in_array( $name, $off, true ) ) {
						continue;
					}
					$tools[] = array(
						'name'        => $name,
						'title'       => $t['title'],
						'description' => ( isset( $open[ $name ] ) ? '' : '[Needs the "' . $levels[ $t['level'] ] . '" level — ask the owner to raise it on Claude Connection.] ' ) . $t['description'],
						'inputSchema' => $t['schema'],
						'annotations' => array(
							'title'           => $t['title'],
							'readOnlyHint'    => in_array( $t['kind'], array( 'read', 'status' ), true ),
							'destructiveHint' => false,
							'idempotentHint'  => 'read' === $t['kind'],
							'openWorldHint'   => false,
						),
					);
				}
				return self::ok( $id, array( 'tools' => $tools ) );

			case 'tools/call':
				$name = isset( $params['name'] ) ? (string) $params['name'] : '';
				$args = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : array();
				$res  = FBCC_Gateway::call( $name, $args, $token->client_id );
				if ( is_wp_error( $res ) ) {
					return self::ok( $id, array(
						'content' => array( array( 'type' => 'text', 'text' => $res->get_error_message() ) ),
						'isError' => true,
					) );
				}
				return self::ok( $id, array(
					'content'           => array( array( 'type' => 'text', 'text' => wp_json_encode( $res, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) ),
					'structuredContent' => (object) $res,
					'isError'           => false,
				) );

			case 'resources/list':
				return self::ok( $id, array( 'resources' => array() ) );
			case 'prompts/list':
				return self::ok( $id, array( 'prompts' => array() ) );
		}
		return self::err( $id, -32601, 'Method not found: ' . $method );
	}

	private static function instructions() {
		$s = FBCC_Store::settings();
		return 'You are connected to the WordPress site "' . get_bloginfo( 'name' ) . '" through FB AI Engine. '
			. 'Permission level: ' . $s['level'] . '. '
			. 'Call task_status at the start of every task, at each step, and with done=true when finished, so the owner can follow along live. '
			. 'Edits to published content and every publish or plugin update go to the owner for approval; tell the owner what is waiting. '
			. 'Never claim a change is live until approvals_list shows it approved. '
			. 'For a whole website: agree the mockups with the owner in chat first, then send them as ONE plan with build_plan_submit; once the owner confirms it, everything inside the plan runs without further approvals (check build_plan_status). '
			. 'Before any build plan, plugin install or theme change, take a backup with backup_create and wait until backup_status shows it fresh. '
			. 'After building or changing pages, run site_check and fix every problem it reports before telling the owner the work is done. '
			. 'Images you make in chat go into the media library with media_upload_data. '
			. 'Never publish a blog post without its featured image (post_settings_set). Put site-wide styles in custom_css_set — <style> tags in page content are removed; inline SVG icons are allowed. '
			. 'If you must use a web browser on this site (a screen no tool covers), first open ' . FBCC_Browser::url() . ' in that browser and click "Link this browser as Claude’s" so the owner can watch it live in Watch Me Live; the link ends when you send task_status with done=true. '
			. 'Voice briefs: the owner may listen on Watch Me Live. Send a short "brief" with task_status at the start (what you will do), at major steps, and with done=true (what was done, what is left for the owner). Brief language: ' . ( 'tr' === FBCC_Browser::voice_lang() ? 'Turkish — write notes and briefs in Turkish' : 'English' ) . '. '
			. ( class_exists( 'FB_Mind_Map_Data' ) ? 'Site plans live in FB Mind Map: read them with mindmap_list / mindmap_get, follow their rules, and mark finished steps with mindmap_update (mark_done). Read big maps one branch at a time.' : '' );
	}
}
