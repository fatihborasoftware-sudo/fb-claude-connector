<?php
/**
 * The single write gateway. Every tool call from Claude and every approval
 * decision passes through here: on/off switch, permission level, per-tool
 * toggles, the AI Engine emergency lock, the lab lock, approvals and logging.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FBCC_Gateway {

	private static $client_id = '';

	/** Tools visible at the current level and not switched off. */
	public static function visible_tools() {
		$s      = FBCC_Store::settings();
		$levels = FBCC_Store::levels();
		$have   = $levels[ $s['level'] ] ?? 1;
		$out    = array();
		foreach ( FBCC_Tools::all() as $name => $t ) {
			if ( ( $levels[ $t['level'] ] ?? 99 ) > $have ) {
				continue;
			}
			if ( in_array( $name, (array) $s['tools_off'], true ) ) {
				continue;
			}
			$out[ $name ] = $t;
		}
		return $out;
	}

	/** Why a tool may not change anything right now ('' = allowed). */
	public static function lock_reason( array $tool ) {
		if ( in_array( $tool['kind'], array( 'read', 'status' ), true ) ) {
			return '';
		}
		if ( FBCC_Store::emergency_lock() ) {
			return 'AI Engine emergency write lock is ON — no changes are allowed.';
		}
		if ( ! empty( $tool['site'] ) && FBCC_Store::lab_lock() ) {
			return FBCC_Store::lab_lock_forced()
				? 'This site runs as the Online Lab: plugin and site changes are always locked.'
				: 'Lab lock is ON — plugin and site changes are blocked. The owner can turn it off in Claude Connection.';
		}
		return '';
	}

	/**
	 * Run a tool for Claude. Returns array result or WP_Error.
	 */
	public static function call( $name, array $args, $client_id = '' ) {
		self::$client_id = (string) $client_id;
		$s               = FBCC_Store::settings();

		if ( empty( $s['enabled'] ) ) {
			return new WP_Error( 'disabled', 'The Claude Connector is switched off on this site.' );
		}
		$tools = self::visible_tools();
		if ( ! isset( $tools[ $name ] ) ) {
			$exists = FBCC_Tools::get( $name );
			FBCC_Store::log( $name, 'Refused: tool not allowed at level "' . $s['level'] . '"', 'blocked', '', $client_id );
			return new WP_Error( 'not_allowed', $exists ? 'This tool is not allowed at the current permission level or is switched off.' : 'Unknown tool.' );
		}
		$tool = $tools[ $name ];

		foreach ( (array) ( $tool['schema']['required'] ?? array() ) as $req ) {
			if ( ! isset( $args[ $req ] ) || '' === $args[ $req ] ) {
				return new WP_Error( 'missing_argument', 'Missing required argument: ' . $req );
			}
		}

		$lock = self::lock_reason( $tool );
		if ( $lock ) {
			FBCC_Store::log( $name, 'Blocked: ' . $lock, 'blocked', '', $client_id );
			return new WP_Error( 'locked', $lock );
		}

		// Claude always works as its own WordPress user.
		$agent = FBCC_Store::agent_user_id();
		if ( ! $agent ) {
			return new WP_Error( 'no_agent', 'The claude-agent user is missing or does not have exactly the Editor role. Fix it in Users, then re-activate the plugin.' );
		}
		$previous = get_current_user_id();
		wp_set_current_user( $agent );
		FBCC_Theme::$running = true;

		try {
			if ( 'approval' === $tool['kind'] ) {
				$prep = call_user_func( $tool['prepare'], $args );
				if ( is_wp_error( $prep ) ) {
					$result = $prep;
				} else {
					$plan = 'build_plan_submit' === $name ? null : FBCC_Plan::intercept( $name, $prep['title'], $prep['payload'] );
					if ( null !== $plan ) {
						$result = $plan; // covered by the owner's approved build plan
					} else {
						$id     = self::queue( $name, $prep['title'], $args['reason'] ?? '', $prep['payload'] );
						$result = array(
							'_queued'  => $id,
							'_summary' => $prep['title'] . ' — waiting for approval #' . $id,
							'queued'   => true,
							'approval' => $id,
							'message'  => 'Sent to the site owner for approval. Nothing has changed yet. Check approvals_list later.',
						);
					}
				}
			} else {
				$result = call_user_func( $tool['run'], $args );
			}
		} catch ( \Throwable $e ) {
			$result = new WP_Error( 'tool_error', $e->getMessage() );
		}
		FBCC_Theme::$running = false;
		wp_set_current_user( $previous );

		if ( 'content_create_draft' === $name && is_array( $result ) && ! empty( $result['id'] ) ) {
			FBCC_Plan::note_built( (string) ( $args['title'] ?? '' ), (int) $result['id'] );
		}
		self::record( $name, $tool, $args, $result );
		return is_wp_error( $result ) ? $result : self::clean( $result );
	}

	/** Puts a request in the approval queue. Used by approval tools and by handlers that escalate. */
	public static function queue( $tool, $title, $reason, array $payload ) {
		return FBCC_Store::add_approval( $tool, $title, $reason, $payload );
	}

	private static function record( $name, array $tool, array $args, $result ) {
		if ( 'status' === $tool['kind'] ) {
			return; // progress pings are not logged.
		}
		if ( is_wp_error( $result ) ) {
			FBCC_Store::log( $name, $result->get_error_message(), 'error', '', self::$client_id );
			return;
		}
		if ( ! empty( $result['_ready'] ) ) {
			FBCC_Store::log( $name, $result['_summary'], 'ready', '', self::$client_id );
			return;
		}
		if ( ! empty( $result['_queued'] ) ) {
			FBCC_Store::log( $name, $result['_summary'] ?? 'Queued for approval', 'queued', '', self::$client_id );
			return;
		}
		if ( 'read' === $tool['kind'] ) {
			$what = isset( $args['id'] ) ? ' #' . (int) $args['id'] : '';
			FBCC_Store::log( $name, 'Read ' . strtolower( $tool['title'] ) . $what, 'read', '', self::$client_id );
			return;
		}
		FBCC_Store::log( $name, $result['_summary'] ?? $tool['title'], 'done', $result['_undo'] ?? '', self::$client_id );
	}

	private static function clean( array $r ) {
		foreach ( array_keys( $r ) as $k ) {
			if ( 0 === strpos( (string) $k, '_' ) ) {
				unset( $r[ $k ] );
			}
		}
		return $r;
	}

	/* ------------------------------------------------------------ */
	/* Approval decisions (run as the logged-in administrator)      */
	/* ------------------------------------------------------------ */

	public static function approve( $id ) {
		$row = FBCC_Store::approval( (int) $id );
		if ( ! $row || 'pending' !== $row->status ) {
			return new WP_Error( 'gone', 'This request was already decided.' );
		}
		$tool = FBCC_Tools::get( $row->tool );
		if ( ! $tool ) {
			return new WP_Error( 'unknown', 'Unknown tool.' );
		}
		$lock = self::lock_reason( array_merge( $tool, array( 'kind' => 'approval' ) ) );
		if ( $lock ) {
			return new WP_Error( 'locked', $lock );
		}
		if ( ! FBCC_Store::claim_approval( $row->id ) ) {
			return new WP_Error( 'gone', 'This request was already decided.' );
		}
		$payload = json_decode( $row->payload, true );
		$payload = is_array( $payload ) ? $payload : array();

		try {
			$res = self::execute_payload( $row->tool, $payload );
		} catch ( \Throwable $e ) {
			$res = new WP_Error( 'tool_error', $e->getMessage() );
		}

		if ( is_wp_error( $res ) ) {
			FBCC_Store::decide_approval( $row->id, 'failed', $res->get_error_message() );
			FBCC_Store::log( $row->tool, 'Approved #' . $row->id . ' but it failed: ' . $res->get_error_message(), 'error' );
			return $res;
		}
		$summary = $res['_summary'] ?? $row->title;
		FBCC_Store::decide_approval( $row->id, 'approved', $summary );
		FBCC_Store::log( $row->tool, 'Approved by ' . wp_get_current_user()->display_name . ': ' . $summary, 'done', $res['_undo'] ?? '' );
		return $res;
	}

	/** Carries out a prepared action (approval, build plan or launch). */
	public static function execute_payload( $tool_name, array $payload ) {
		$was                 = FBCC_Theme::$running;
		FBCC_Theme::$running = true;
		try {
			if ( 'content_update' === $tool_name ) {
				return FBCC_Tools::apply_update( (int) $payload['id'], array_diff_key( $payload, array( 'id' => 1 ) ) );
			}
			$tool = FBCC_Tools::get( $tool_name );
			if ( $tool && isset( $tool['execute'] ) ) {
				return call_user_func( $tool['execute'], $payload );
			}
			return new WP_Error( 'unsupported', 'Nothing to execute.' );
		} finally {
			FBCC_Theme::$running = $was;
		}
	}

	public static function reject( $id ) {
		$row = FBCC_Store::approval( (int) $id );
		if ( ! $row || ! FBCC_Store::claim_approval( $row->id ) ) {
			return new WP_Error( 'gone', 'This request was already decided.' );
		}
		FBCC_Store::decide_approval( $row->id, 'rejected', 'Rejected by ' . wp_get_current_user()->display_name );
		FBCC_Store::log( $row->tool, 'Rejected #' . $row->id . ': ' . $row->title, 'blocked' );
		return true;
	}
}
