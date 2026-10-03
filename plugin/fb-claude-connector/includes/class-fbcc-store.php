<?php
/**
 * Storage: settings, DB tables, the agent user, locks, tokens, activity, approvals.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FBCC_Store {

	const DB_VERSION   = 1;
	const OPT_SETTINGS = 'fbcc_connector_settings';
	/** Before 1.2.2. Shared its name with other "fbcc" plugins (e.g. FB Cookie Consent) — read once to migrate, never written. */
	const OPT_SETTINGS_OLD = 'fbcc_settings';
	const OPT_CLIENTS  = 'fbcc_clients';
	const OPT_AGENT    = 'fbcc_agent_user';
	const OPT_DB       = 'fbcc_db_version';
	const OPT_TASK     = 'fbcc_task_status';

	/* ------------------------------------------------------------ */
	/* Install                                                      */
	/* ------------------------------------------------------------ */

	public static function activate() {
		self::create_tables();
		self::agent_user_id();
		if ( false === get_option( self::OPT_SETTINGS ) ) {
			add_option( self::OPT_SETTINGS, self::defaults(), '', false );
		}
		flush_rewrite_rules( false );
	}

	public static function maybe_upgrade() {
		if ( (int) get_option( self::OPT_DB ) < self::DB_VERSION ) {
			self::create_tables();
		}
		if ( get_option( 'fbcc_htaccess_ver' ) !== FBCC_VERSION ) {
			self::htaccess_fix();
			update_option( 'fbcc_htaccess_ver', FBCC_VERSION, false );
		}
	}

	/**
	 * Many hosts (Hostinger / LiteSpeed included) drop the Authorization header
	 * before PHP. Put a small pass-through block at the TOP of .htaccess.
	 * Returns 'ok', 'present', or an error string.
	 */
	public static function htaccess_fix() {
		if ( ! function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$file = trailingslashit( get_home_path() ) . '.htaccess';
		if ( ! file_exists( $file ) || ! is_writable( $file ) ) {
			return 'not writable';
		}
		$current = (string) file_get_contents( $file ); // phpcs:ignore
		if ( false !== strpos( $current, '# BEGIN FB Claude Connector' ) ) {
			return 'present';
		}
		$block = "# BEGIN FB Claude Connector\n"
			. "# Passes the Authorization header to WordPress (needed for Claude sign-in).\n"
			. "<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteCond %{HTTP:Authorization} .\nRewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]\n</IfModule>\n"
			. "<IfModule mod_setenvif.c>\nSetEnvIf Authorization \"(.+)\" HTTP_AUTHORIZATION=$1\n</IfModule>\n"
			. "# END FB Claude Connector\n\n";
		@copy( $file, $file . '.fbcc-backup' ); // phpcs:ignore
		$ok = file_put_contents( $file, $block . $current, LOCK_EX ); // phpcs:ignore
		return $ok ? 'ok' : 'write failed';
	}

	public static function htaccess_remove() {
		if ( ! function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$file = trailingslashit( get_home_path() ) . '.htaccess';
		if ( ! file_exists( $file ) || ! is_writable( $file ) ) {
			return;
		}
		$c   = (string) file_get_contents( $file ); // phpcs:ignore
		$new = preg_replace( '/# BEGIN FB Claude Connector.*?# END FB Claude Connector\n*/s', '', $c );
		if ( null !== $new && $new !== $c ) {
			file_put_contents( $file, $new, LOCK_EX ); // phpcs:ignore
		}
		delete_option( 'fbcc_htaccess_ver' );
	}

	private static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		$p = $wpdb->prefix;

		dbDelta( "CREATE TABLE {$p}fbcc_tokens (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			token_hash char(64) NOT NULL,
			kind varchar(10) NOT NULL,
			client_id varchar(64) NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			expires_at datetime NOT NULL,
			created_at datetime NOT NULL,
			last_used datetime NULL,
			revoked tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash),
			KEY client_id (client_id)
		) $c;" );

		dbDelta( "CREATE TABLE {$p}fbcc_activity (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			tool varchar(64) NOT NULL,
			summary text NOT NULL,
			result varchar(20) NOT NULL,
			undo_url text NULL,
			client_id varchar(64) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY created_at (created_at)
		) $c;" );

		dbDelta( "CREATE TABLE {$p}fbcc_approvals (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			tool varchar(64) NOT NULL,
			title text NOT NULL,
			reason text NULL,
			payload longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			decided_at datetime NULL,
			decided_by bigint(20) unsigned NULL,
			result text NULL,
			PRIMARY KEY  (id),
			KEY status (status)
		) $c;" );

		update_option( self::OPT_DB, self::DB_VERSION, false );
	}

	/* ------------------------------------------------------------ */
	/* Settings                                                     */
	/* ------------------------------------------------------------ */

	public static function defaults() {
		return array(
			'enabled'   => true,
			'level'     => 'read',   // read | editor | maintainer
			'tools_off' => array(),
			'lab_lock'  => true,
			'honor_emergency' => true, // obey AI Engine's FBSA_EMERGENCY_WRITE_LOCK
			'honor_lab'       => true, // obey AI Engine's FBSA_ONLINE_LAB_ENABLED
		);
	}

	public static function settings() {
		$s = get_option( self::OPT_SETTINGS, null );
		if ( ! is_array( $s ) ) {
			$s = self::migrate_settings();
		}
		return wp_parse_args( $s, self::defaults() );
	}

	/**
	 * 1.2.2: settings moved from "fbcc_settings" to "fbcc_connector_settings". The old row is
	 * only copied when it really is ours (it has a valid "level"); another plugin's
	 * "fbcc_settings" is left untouched.
	 */
	private static function migrate_settings() {
		$s   = array();
		$old = get_option( self::OPT_SETTINGS_OLD, null );
		if ( is_array( $old ) && isset( $old['level'] ) && isset( self::levels()[ $old['level'] ] ) ) {
			$s = array_intersect_key( $old, self::defaults() );
		}
		add_option( self::OPT_SETTINGS, wp_parse_args( $s, self::defaults() ), '', false );
		return $s;
	}

	public static function update_settings( array $changes ) {
		$s = array_merge( self::settings(), $changes );
		update_option( self::OPT_SETTINGS, $s, false );
		return $s;
	}

	public static function levels() {
		return array(
			'read'       => 1,
			'editor'     => 2,
			'maintainer' => 3,
		);
	}

	/* ------------------------------------------------------------ */
	/* Locks                                                        */
	/* ------------------------------------------------------------ */

	/** Every write is blocked: AI Engine emergency lock. */
	public static function emergency_lock_defined() {
		return defined( 'FBSA_EMERGENCY_WRITE_LOCK' ) && FBSA_EMERGENCY_WRITE_LOCK;
	}

	public static function emergency_lock() {
		return self::emergency_lock_defined() && ! empty( self::settings()['honor_emergency'] );
	}

	public static function lab_defined() {
		return defined( 'FBSA_ONLINE_LAB_ENABLED' ) && FBSA_ONLINE_LAB_ENABLED;
	}

	/** Lab lock is forced on when AI Engine runs as the Online Lab. */
	public static function lab_lock_forced() {
		return self::lab_defined() && ! empty( self::settings()['honor_lab'] );
	}

	/** Site-level actions (plugins, updates) are blocked. */
	public static function lab_lock() {
		return self::emergency_lock() || self::lab_lock_forced() || ! empty( self::settings()['lab_lock'] );
	}

	/* ------------------------------------------------------------ */
	/* Agent user — Claude's own WordPress identity                 */
	/* ------------------------------------------------------------ */

	public static function agent_user_id() {
		$is_editor_only = function ( $u ) {
			return $u && array( 'editor' ) === array_values( (array) $u->roles );
		};
		$id = (int) get_option( self::OPT_AGENT );
		if ( $id ) {
			// Never run tools with more than the Editor role.
			return $is_editor_only( get_userdata( $id ) ) ? $id : 0;
		}
		$login = 'claude-agent';
		$user  = get_user_by( 'login', $login );
		if ( $user ) {
			if ( ! $is_editor_only( $user ) ) {
				return 0; // an existing "claude-agent" with another role is never adopted.
			}
			$id = (int) $user->ID;
		} else {
			$host = wp_parse_url( home_url(), PHP_URL_HOST );
			$id   = wp_insert_user( array(
				'user_login'   => $login,
				'user_pass'    => wp_generate_password( 64, true, true ),
				'user_email'   => 'claude-agent@' . ( $host ? $host : 'localhost' ) . '.invalid',
				'display_name' => 'Claude (agent)',
				'role'         => 'editor',
				'description'  => 'Used by the Claude Connector. Cannot sign in.',
			) );
			if ( is_wp_error( $id ) ) {
				return 0;
			}
		}
		update_option( self::OPT_AGENT, (int) $id, false );
		return (int) $id;
	}

	/* ------------------------------------------------------------ */
	/* OAuth clients (dynamic client registration)                  */
	/* ------------------------------------------------------------ */

	public static function clients() {
		$c = get_option( self::OPT_CLIENTS, array() );
		return is_array( $c ) ? $c : array();
	}

	public static function client( $id ) {
		$c = self::clients();
		return isset( $c[ $id ] ) ? $c[ $id ] : null;
	}

	public static function save_client( $id, array $data ) {
		$c        = self::clients();
		$c[ $id ] = $data;
		// Keep the list small: drop the oldest unused registrations.
		if ( count( $c ) > 30 ) {
			uasort( $c, function ( $a, $b ) {
				return ( $a['last_auth'] ?? $a['created'] ) <=> ( $b['last_auth'] ?? $b['created'] );
			} );
			$c = array_slice( $c, -30, null, true );
		}
		update_option( self::OPT_CLIENTS, $c, false );
	}

	/* ------------------------------------------------------------ */
	/* Tokens                                                       */
	/* ------------------------------------------------------------ */

	public static function hash( $raw ) {
		return hash_hmac( 'sha256', $raw, wp_salt( 'auth' ) );
	}

	public static function issue_token( $kind, $client_id, $user_id, $ttl ) {
		global $wpdb;
		$raw = 'fbcc_' . ( 'access' === $kind ? 'at_' : 'rt_' ) . bin2hex( random_bytes( 32 ) );
		$wpdb->insert( $wpdb->prefix . 'fbcc_tokens', array(
			'token_hash' => self::hash( $raw ),
			'kind'       => $kind,
			'client_id'  => $client_id,
			'user_id'    => (int) $user_id,
			'expires_at' => gmdate( 'Y-m-d H:i:s', time() + $ttl ),
			'created_at' => current_time( 'mysql', true ),
		) );
		return $raw;
	}

	/** Returns the token row when valid, else null. */
	public static function find_token( $raw, $kind ) {
		global $wpdb;
		if ( ! is_string( $raw ) || strlen( $raw ) < 20 ) {
			return null;
		}
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}fbcc_tokens WHERE token_hash = %s AND kind = %s AND revoked = 0",
			self::hash( $raw ),
			$kind
		) );
		if ( ! $row || strtotime( $row->expires_at . ' UTC' ) < time() ) {
			return null;
		}
		return $row;
	}

	public static function touch_token( $id ) {
		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'fbcc_tokens', array( 'last_used' => current_time( 'mysql', true ) ), array( 'id' => (int) $id ) );
	}

	public static function revoke_token( $id ) {
		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'fbcc_tokens', array( 'revoked' => 1 ), array( 'id' => (int) $id ) );
	}

	public static function revoke_all() {
		global $wpdb;
		$wpdb->query( "UPDATE {$wpdb->prefix}fbcc_tokens SET revoked = 1 WHERE revoked = 0" );
	}

	/** The most recently used live access token (for the status card). */
	public static function connection_info() {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}fbcc_tokens WHERE revoked = 0 AND kind = 'refresh' AND expires_at > %s ORDER BY COALESCE(last_used, created_at) DESC LIMIT 1",
			$now
		) );
		$last = $wpdb->get_var( "SELECT MAX(last_used) FROM {$wpdb->prefix}fbcc_tokens WHERE kind = 'access'" );
		return array(
			'session'   => $row,
			'last_call' => $last,
		);
	}

	/* ------------------------------------------------------------ */
	/* Diagnostics: last 40 raw requests to the OAuth + MCP endpoints */
	/* (never stores tokens, codes or secrets)                       */
	/* ------------------------------------------------------------ */

	public static function trace( $endpoint, array $info ) {
		$log   = get_option( 'fbcc_trace', array() );
		$log   = is_array( $log ) ? $log : array();
		$ua    = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 60 ) : '';
		$log[] = array_merge( array(
			't'  => gmdate( 'H:i:s' ),
			'ep' => $endpoint,
			'm'  => isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( $_SERVER['REQUEST_METHOD'] ) : '',
			'ua' => $ua,
		), $info );
		update_option( 'fbcc_trace', array_slice( $log, -40 ), false );
	}

	/** Rough token estimate: bytes in + out of every MCP call, per day (÷4 ≈ tokens). */
	public static function add_bytes( $n ) {
		$k = 'fbcc_bytes_' . gmdate( 'Ymd' );
		set_transient( $k, (int) get_transient( $k ) + (int) $n, 2 * DAY_IN_SECONDS );
	}

	public static function tokens_today() {
		return (int) round( (int) get_transient( 'fbcc_bytes_' . gmdate( 'Ymd' ) ) / 4 );
	}

	/* ------------------------------------------------------------ */
	/* Activity log                                                 */
	/* ------------------------------------------------------------ */

	public static function log( $tool, $summary, $result, $undo_url = '', $client_id = '' ) {
		global $wpdb;
		$wpdb->insert( $wpdb->prefix . 'fbcc_activity', array(
			'created_at' => current_time( 'mysql', true ),
			'tool'       => substr( (string) $tool, 0, 64 ),
			'summary'    => wp_strip_all_tags( (string) $summary ),
			'result'     => substr( (string) $result, 0, 20 ),
			'undo_url'   => $undo_url ? esc_url_raw( $undo_url ) : null,
			'client_id'  => (string) $client_id,
		) );
		return (int) $wpdb->insert_id;
	}

	public static function activity( $limit = 50, $offset = 0 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}fbcc_activity ORDER BY id DESC LIMIT %d OFFSET %d",
			$limit,
			$offset
		) );
	}

	public static function today_counts() {
		global $wpdb;
		$since = gmdate( 'Y-m-d 00:00:00' );
		$rows  = $wpdb->get_results( $wpdb->prepare(
			"SELECT result, COUNT(*) n FROM {$wpdb->prefix}fbcc_activity WHERE created_at >= %s GROUP BY result",
			$since
		), OBJECT_K );
		$n = function ( $k ) use ( $rows ) {
			return isset( $rows[ $k ] ) ? (int) $rows[ $k ]->n : 0;
		};
		return array(
			'reads'   => $n( 'read' ),
			'changes' => $n( 'done' ),
			'queued'  => self::pending_count(),
			'blocked' => $n( 'blocked' ),
		);
	}

	/* ------------------------------------------------------------ */
	/* Approvals                                                    */
	/* ------------------------------------------------------------ */

	public static function add_approval( $tool, $title, $reason, array $payload ) {
		global $wpdb;
		$wpdb->insert( $wpdb->prefix . 'fbcc_approvals', array(
			'created_at' => current_time( 'mysql', true ),
			'tool'       => $tool,
			'title'      => wp_strip_all_tags( $title ),
			'reason'     => wp_strip_all_tags( (string) $reason ),
			'payload'    => wp_json_encode( $payload ),
			'status'     => 'pending',
		) );
		return (int) $wpdb->insert_id;
	}

	public static function approval( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}fbcc_approvals WHERE id = %d", $id ) );
	}

	public static function approvals( $status = 'pending', $limit = 50 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}fbcc_approvals WHERE status = %s ORDER BY id " . ( 'pending' === $status ? 'ASC' : 'DESC' ) . ' LIMIT %d',
			$status,
			$limit
		) );
	}

	public static function pending_count() {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}fbcc_approvals WHERE status = 'pending'" );
	}

	/** Atomically moves a pending request to "running". True only for the one caller that wins. */
	public static function claim_approval( $id ) {
		global $wpdb;
		return 1 === (int) $wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->prefix}fbcc_approvals SET status = 'running' WHERE id = %d AND status = 'pending'",
			$id
		) );
	}

	public static function decide_approval( $id, $status, $result = '' ) {
		global $wpdb;
		return $wpdb->update( $wpdb->prefix . 'fbcc_approvals', array(
			'status'     => $status,
			'decided_at' => current_time( 'mysql', true ),
			'decided_by' => get_current_user_id(),
			'result'     => $result,
		), array( 'id' => (int) $id ) );
	}

	/* ------------------------------------------------------------ */
	/* Live task status (shown in the admin bar while Claude works) */
	/* ------------------------------------------------------------ */

	public static function set_task( array $t ) {
		$t['updated'] = time();
		update_option( self::OPT_TASK, $t, false );
	}

	/** A task that finished in the last 3 minutes (for the spoken finish brief). */
	public static function finished_task() {
		$t = get_option( self::OPT_TASK );
		if ( ! is_array( $t ) || empty( $t['done'] ) || time() - (int) ( $t['updated'] ?? 0 ) > 180 ) {
			return null;
		}
		return $t;
	}

	public static function task() {
		$t = get_option( self::OPT_TASK );
		if ( ! is_array( $t ) || empty( $t['updated'] ) ) {
			return null;
		}
		// A task with no update for 10 minutes is considered finished.
		if ( ! empty( $t['done'] ) || time() - (int) $t['updated'] > 600 ) {
			return null;
		}
		return $t;
	}
}
