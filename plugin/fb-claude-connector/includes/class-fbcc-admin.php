<?php
/**
 * wp-admin: the Claude Connection screen, approval actions, the admin-bar
 * live badge, and the claude-agent sign-in block.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FBCC_Admin {

	const SLUG = 'fbcc';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 99 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		foreach ( array( 'save', 'kill', 'enable', 'decide', 'revoke', 'htaccess', 'selftest', 'permalinks' ) as $a ) {
			add_action( 'admin_post_fbcc_' . $a, array( __CLASS__, 'action_' . $a ) );
		}
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 90 );
		add_action( 'admin_footer', array( __CLASS__, 'bar_script' ) );
		add_action( 'wp_footer', array( __CLASS__, 'bar_script' ) );

		// claude-agent can never sign in with a password or application password.
		add_filter( 'authenticate', array( __CLASS__, 'block_agent_login' ), 99 );
		add_filter( 'wp_is_application_passwords_available_for_user', function ( $ok, $user ) {
			return ( $user && (int) $user->ID === (int) get_option( FBCC_Store::OPT_AGENT ) ) ? false : $ok;
		}, 10, 2 );
	}

	public static function block_agent_login( $user ) {
		if ( $user instanceof WP_User && (int) $user->ID === (int) get_option( FBCC_Store::OPT_AGENT ) ) {
			return new WP_Error( 'fbcc_agent', 'This account is used by the Claude Connector and cannot sign in.' );
		}
		return $user;
	}

	/* ------------------------------------------------------------ */
	/* Menu + assets                                                */
	/* ------------------------------------------------------------ */

	public static function menu() {
		global $menu;
		$parent = apply_filters( 'fbcc_parent_menu', '' );
		if ( ! $parent && is_array( $menu ) ) {
			foreach ( $menu as $item ) {
				$title = isset( $item[0] ) ? wp_strip_all_tags( $item[0] ) : '';
				$slug  = isset( $item[2] ) ? $item[2] : '';
				if ( false !== stripos( $title, 'AI Engine' ) || false !== stripos( $slug, 'fb-software-ai' ) ) {
					$parent = $slug;
					break;
				}
			}
		}
		$n     = FBCC_Store::pending_count();
		$label = 'Claude Connection' . ( $n ? ' <span class="awaiting-mod">' . (int) $n . '</span>' : '' );
		if ( $parent ) {
			add_submenu_page( $parent, 'Claude Connection', $label, 'manage_options', self::SLUG, array( __CLASS__, 'page' ) );
		} else {
			add_menu_page( 'Claude Connection', $label, 'manage_options', self::SLUG, array( __CLASS__, 'page' ), 'dashicons-admin-links', 81 );
		}
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'fbcc-admin', FBCC_URL . 'assets/admin.css', array(), FBCC_VERSION );
	}

	private static function url( $tab = 'overview', $extra = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG, 'tab' => $tab ), $extra ), admin_url( 'admin.php' ) );
	}

	private static function guard( $nonce ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		check_admin_referer( $nonce );
	}

	private static function back( $tab, $msg, $type = 'success' ) {
		wp_safe_redirect( self::url( $tab, array( 'fbcc_msg' => rawurlencode( $msg ), 'fbcc_t' => $type ) ) );
		exit;
	}

	/* ------------------------------------------------------------ */
	/* Actions                                                      */
	/* ------------------------------------------------------------ */

	public static function action_save() {
		self::guard( 'fbcc_save' );
		$tab     = isset( $_POST['tab'] ) ? sanitize_key( $_POST['tab'] ) : 'overview';
		$changes = array();
		if ( isset( $_POST['level'] ) ) {
			$lvl = sanitize_key( $_POST['level'] );
			if ( isset( FBCC_Store::levels()[ $lvl ] ) ) {
				$changes['level'] = $lvl;
			}
		}
		if ( isset( $_POST['tools_present'] ) ) {
			$on  = isset( $_POST['tools_on'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['tools_on'] ) ) : array();
			$off = array();
			foreach ( array_keys( FBCC_Tools::all() ) as $name ) {
				if ( ! in_array( $name, $on, true ) ) {
					$off[] = $name;
				}
			}
			$changes['tools_off'] = $off;
		}
		if ( isset( $_POST['locks_present'] ) ) {
			$changes['honor_emergency'] = ! empty( $_POST['honor_emergency'] );
			$changes['honor_lab']       = ! empty( $_POST['honor_lab'] );
		}
		if ( isset( $_POST['lab_lock_present'] ) ) {
			$changes['lab_lock'] = ! empty( $_POST['lab_lock'] );
		}
		$s = FBCC_Store::update_settings( $changes );
		FBCC_Store::log( 'settings', 'Settings changed by ' . wp_get_current_user()->display_name . ' (level: ' . $s['level'] . ', lab lock: ' . ( FBCC_Store::lab_lock() ? 'on' : 'off' ) . ')', 'done' );
		self::back( $tab, 'Saved.' );
	}

	public static function action_kill() {
		self::guard( 'fbcc_kill' );
		FBCC_Store::update_settings( array( 'enabled' => false ) );
		FBCC_Store::revoke_all();
		FBCC_Store::set_task( array( 'done' => true ) );
		FBCC_Store::log( 'kill_switch', 'Claude disconnected by ' . wp_get_current_user()->display_name . ' — all tokens revoked', 'done' );
		self::back( 'overview', 'Claude is disconnected. All sign-ins were revoked.', 'warning' );
	}

	public static function action_enable() {
		self::guard( 'fbcc_enable' );
		FBCC_Store::update_settings( array( 'enabled' => true ) );
		FBCC_Store::log( 'settings', 'Connector switched on by ' . wp_get_current_user()->display_name, 'done' );
		self::back( 'overview', 'Connector is on. Reconnect from Claude to sign in again.' );
	}

	public static function action_revoke() {
		self::guard( 'fbcc_revoke' );
		FBCC_Store::revoke_all();
		FBCC_Store::log( 'settings', 'All Claude sign-ins revoked by ' . wp_get_current_user()->display_name, 'done' );
		self::back( 'overview', 'All sign-ins revoked. Claude must sign in again.' );
	}

	public static function action_htaccess() {
		self::guard( 'fbcc_htaccess' );
		$r   = FBCC_Store::htaccess_fix();
		$msg = array(
			'ok'      => '.htaccess fix added. Run the server check again.',
			'present' => 'The .htaccess fix is already in place.',
		);
		self::back( 'setup', $msg[ $r ] ?? 'Could not change .htaccess (' . $r . '). Add the block by hand — see readme.txt.', isset( $msg[ $r ] ) ? 'success' : 'error' );
	}

	public static function action_permalinks() {
		self::guard( 'fbcc_permalinks' );
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		flush_rewrite_rules( false );
		FBCC_Store::log( 'settings', 'Links set to “Post name” (/%postname%/) so the connector address works', 'done' );
		self::back( 'setup', 'Links now use the post name. Run the server check.' );
	}

	/** Plain links (?p=123) break the /wp-json/ connector address. */
	private static function permalink_notice() {
		if ( '' !== (string) get_option( 'permalink_structure' ) ) {
			return;
		}
		echo '<section class="fbcc-card" style="border-color:#E9C88F;background:#FBF0DC"><h2>Fix your links first</h2><p>This site uses <strong>Plain</strong> links (<code>?p=123</code>), so the connector address <code>/wp-json/…</code> does not work and Claude cannot connect. Switch to <strong>Post name</strong> links — the usual setting for new sites.</p>';
		self::form_open( 'permalinks' );
		echo '<button class="button button-primary">Use post-name links</button></form></section>';
	}

	/** Full round trip through the public URL, exactly like Claude: token → initialize → tools/list. */
	public static function action_selftest() {
		self::guard( 'fbcc_selftest' );
		$tok  = FBCC_Store::issue_token( 'access', 'selftest', get_current_user_id(), 120 );
		$post = function ( $body ) use ( $tok ) {
			return wp_remote_post( FBCC_OAuth::mcp_url(), array(
				'timeout'   => 20,
				'sslverify' => false,
				'headers'   => array(
					'Authorization' => 'Bearer ' . $tok,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json, text/event-stream',
				),
				'body'      => wp_json_encode( $body ),
			) );
		};
		$out = array();
		$r1  = $post( array( 'jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => array( 'protocolVersion' => '2025-06-18', 'capabilities' => new stdClass(), 'clientInfo' => array( 'name' => 'selftest', 'version' => '1' ) ) ) );
		$out[] = 'initialize: ' . ( is_wp_error( $r1 ) ? 'ERROR ' . $r1->get_error_message() : wp_remote_retrieve_response_code( $r1 ) . ' ' . substr( wp_remote_retrieve_body( $r1 ), 0, 160 ) );
		$r2  = $post( array( 'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list' ) );
		if ( ! is_wp_error( $r2 ) ) {
			$j     = json_decode( wp_remote_retrieve_body( $r2 ), true );
			$out[] = 'tools/list: ' . wp_remote_retrieve_response_code( $r2 ) . ' — ' . ( isset( $j['result']['tools'] ) ? count( $j['result']['tools'] ) . ' tools' : substr( wp_remote_retrieve_body( $r2 ), 0, 160 ) );
		} else {
			$out[] = 'tools/list: ERROR ' . $r2->get_error_message();
		}
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}fbcc_tokens SET revoked = 1 WHERE token_hash = %s", FBCC_Store::hash( $tok ) ) );
		set_transient( 'fbcc_selftest_' . get_current_user_id(), $out, 600 );
		self::back( 'setup', 'Full test finished — see the result below.' );
	}

	public static function action_decide() {
		self::guard( 'fbcc_decide' );
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		if ( ! empty( $_POST['approve'] ) ) {
			$r = FBCC_Gateway::approve( $id );
			if ( is_wp_error( $r ) ) {
				self::back( 'approvals', 'Not done: ' . $r->get_error_message(), 'error' );
			}
			self::back( 'approvals', 'Approved and done: ' . ( $r['_summary'] ?? '#' . $id ) );
		}
		$r = FBCC_Gateway::reject( $id );
		self::back( 'approvals', is_wp_error( $r ) ? $r->get_error_message() : 'Rejected. Nothing was changed.', is_wp_error( $r ) ? 'error' : 'success' );
	}

	/* ------------------------------------------------------------ */
	/* Admin bar live badge                                         */
	/* ------------------------------------------------------------ */

	public static function admin_bar( WP_Admin_Bar $bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$text = self::bar_text( FBCC_Store::task(), FBCC_Store::pending_count() );
		$bar->add_node( array(
			'id'    => 'fbcc-live',
			'title' => '<span class="fbcc-dot"></span><span class="fbcc-dots" aria-hidden="true"><i></i><i></i><i></i></span><span class="fbcc-txt">' . esc_html( $text ) . '</span><span class="fbcc-clk"></span>',
			'href'  => FBCC_Store::task() ? FBCC_Live::url() : self::url( FBCC_Store::pending_count() ? 'approvals' : 'overview' ),
			'meta'  => array( 'class' => $text ? 'fbcc-on' : 'fbcc-off' ),
		) );
	}

	private static function bar_text( $task, $pending ) {
		if ( $task ) {
			$s = FBCC_I18n::t( 'Claude is working' );
			if ( ! empty( $task['total'] ) && $task['total'] > 1 ) {
				$s .= ' · ' . sprintf( FBCC_I18n::t( 'step %1$s of %2$s' ), (int) $task['step'], (int) $task['total'] );
			}
			return $s;
		}
		return $pending ? sprintf( FBCC_I18n::t( 'Claude · %s waiting for approval' ), (int) $pending ) : '';
	}

	public static function bar_script() {
		if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$api   = esc_url_raw( rest_url( FBCC_NS . '/claude/status' ) );
		$nonce = wp_create_nonce( 'wp_rest' );
		?>
<style>
#wpadminbar .fbcc-off{display:none}
#wpadminbar #wp-admin-bar-fbcc-live>.ab-item{background:#4f3fd0;color:#fff;border-radius:999px;margin:4px 6px;height:24px;line-height:24px;padding:0 10px;font-weight:600}
#wpadminbar #wp-admin-bar-fbcc-live .fbcc-dot{display:inline-block;width:8px;height:8px;border-radius:50%;background:#5ee39a;margin-right:7px;vertical-align:0}
#wpadminbar #wp-admin-bar-fbcc-live .fbcc-dots{display:none;margin-right:8px;vertical-align:1px}
#wpadminbar #wp-admin-bar-fbcc-live .fbcc-dots i{display:inline-block;width:5px;height:5px;border-radius:50%;background:#fff;margin-right:3px;animation:fbccDots 1.2s ease-in-out infinite}
#wpadminbar #wp-admin-bar-fbcc-live .fbcc-dots i:nth-child(2){animation-delay:.15s}#wpadminbar #wp-admin-bar-fbcc-live .fbcc-dots i:nth-child(3){animation-delay:.3s;margin-right:0}
#wpadminbar #wp-admin-bar-fbcc-live.fbcc-working .fbcc-dot{display:none}
#wpadminbar #wp-admin-bar-fbcc-live.fbcc-working .fbcc-dots{display:inline-block}
#wpadminbar #wp-admin-bar-fbcc-live .fbcc-clk{margin-left:6px;opacity:.85;font-variant-numeric:tabular-nums}
@keyframes fbccDots{0%,60%,100%{transform:translateY(0);opacity:.45}30%{transform:translateY(-3px);opacity:1}}
@keyframes fbccPulse{50%{opacity:.25}}
@media (prefers-reduced-motion:reduce){#wpadminbar #wp-admin-bar-fbcc-live .fbcc-dots i{animation:none;opacity:1}}
</style>
<script>
(function(){
	var li=document.getElementById('wp-admin-bar-fbcc-live'); if(!li||!window.fetch) return;
	var txt=li.querySelector('.fbcc-txt'), clk=li.querySelector('.fbcc-clk'), st=0, off=0;
	function dur(x){x=Math.max(0,Math.floor(x));var h=Math.floor(x/3600),m=Math.floor(x%3600/60),s=x%60;return h?h+'h '+(m<10?'0':'')+m+'m':(m?m+'m '+(s<10?'0':'')+s+'s':s+'s');}
	function tick(){ clk.textContent = st ? '· '+dur(Date.now()/1000+off-st) : ''; }
	setInterval(tick,1000);
	var W=<?php echo wp_json_encode( FBCC_I18n::t( 'Claude is working' ) ); ?>, S=<?php echo wp_json_encode( FBCC_I18n::t( 'step %1$s of %2$s' ) ); ?>, P=<?php echo wp_json_encode( FBCC_I18n::t( 'Claude · %s waiting for approval' ) ); ?>;
	function paint(d){
		var t='';
		if(d.task){ t=W; if(d.task.total>1){ t+=' · '+S.replace('%1$s',d.task.step).replace('%2$s',d.task.total); } if(d.task.note){ li.title=d.task.title+' — '+d.task.note; } }
		else if(d.pending){ t=P.replace('%s',d.pending); li.title=''; }
		if(d.now){ off=d.now-Date.now()/1000; }
		st=(d.task&&d.task.started)?d.task.started:0; tick();
		txt.textContent=t;
		li.className=(t?'fbcc-on':'fbcc-off')+(d.task?' fbcc-working':'');
	}
	function poll(){
		fetch(<?php echo wp_json_encode( $api ); ?>,{credentials:'same-origin',headers:{'X-WP-Nonce':<?php echo wp_json_encode( $nonce ); ?>}})
			.then(function(r){return r.ok?r.json():null}).then(function(d){ if(d){ paint(d); } }).catch(function(){});
	}
	poll(); setInterval(function(){ if(!document.hidden){ poll(); } },10000);
})();
</script>
		<?php
	}

	/* ------------------------------------------------------------ */
	/* The screen                                                   */
	/* ------------------------------------------------------------ */

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		FBCC_I18n::render( array( __CLASS__, 'render_page' ) );
	}

	public static function render_page() {
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'overview'; // phpcs:ignore
		$tabs = array(
			'overview'  => 'Overview',
			'tools'     => 'Permissions & tools',
			'approvals' => 'Approvals (' . FBCC_Store::pending_count() . ')',
			'activity'  => 'Activity log',
			'setup'     => 'Setup',
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'overview';
		}
		$s = FBCC_Store::settings();
		$c = FBCC_Store::connection_info();

		echo '<div class="wrap fbcc">';
		echo '<div class="fbcc-title"><h1>Claude Connection</h1>';
		if ( ! $s['enabled'] ) {
			echo '<span class="fbcc-pill fbcc-red">Switched off</span>';
		} elseif ( $c['session'] ) {
			echo '<span class="fbcc-pill fbcc-green">Connected</span>';
		} else {
			echo '<span class="fbcc-pill">Not connected</span>';
		}
		echo '<span class="fbcc-pill fbcc-purple">' . esc_html( self::level_name( $s['level'] ) ) . '</span>';
		echo '<a class="button button-primary fbcc-watch" href="' . esc_url( FBCC_Live::url() ) . '">▶ Watch Claude live</a>';
		echo '<span class="fbcc-ver">FB AI Engine · Claude Connector ' . esc_html( FBCC_VERSION ) . '</span>' . FBCC_I18n::switcher() . '</div>'; // phpcs:ignore

		if ( isset( $_GET['fbcc_msg'] ) ) { // phpcs:ignore
			$type = isset( $_GET['fbcc_t'] ) ? sanitize_key( $_GET['fbcc_t'] ) : 'success'; // phpcs:ignore
			echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( wp_unslash( rawurldecode( $_GET['fbcc_msg'] ) ) ) . '</p></div>'; // phpcs:ignore
		}

		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $k => $label ) {
			echo '<a class="nav-tab' . ( $k === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( self::url( $k ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';

		call_user_func( array( __CLASS__, 'tab_' . $tab ), $s, $c );
		echo '</div>';
	}

	private static function level_name( $l ) {
		$n = array( 'read' => 'Read-only', 'editor' => 'Content editor', 'maintainer' => 'Site maintainer' );
		return $n[ $l ] ?? $l;
	}

	private static function when( $gmt ) {
		if ( ! $gmt ) {
			return '—';
		}
		$ts = strtotime( $gmt . ' UTC' );
		return sprintf( '%s ago', human_time_diff( $ts ) );
	}

	private static function form_open( $action, $extra = '' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" ' . $extra . '>'; // phpcs:ignore
		echo '<input type="hidden" name="action" value="fbcc_' . esc_attr( $action ) . '">';
		wp_nonce_field( 'fbcc_' . $action );
	}

	/* -- Overview -------------------------------------------------- */

	private static function tab_overview( $s, $c ) {
		$counts = FBCC_Store::today_counts();
		$task   = FBCC_Store::task();
		$client = $c['session'] ? FBCC_Store::client( $c['session']->client_id ) : null;
		$by     = $c['session'] ? get_userdata( (int) $c['session']->user_id ) : null;

		self::permalink_notice();
		FBCC_Plan::card();
		echo '<div class="fbcc-grid2">';
		FBCC_BuildTools::card();
		FBCC_SiteCheck::card();
		echo '</div>';
		FBCC_Browser::card();

		if ( $task ) {
			echo '<section class="fbcc-card fbcc-live"><h2>Claude is working: ' . esc_html( $task['title'] ) . '</h2>';
			$pct = $task['total'] ? min( 100, round( 100 * $task['step'] / $task['total'] ) ) : 0;
			echo '<div class="fbcc-bar"><span style="width:' . (int) $pct . '%"></span></div>';
			if ( $task['steps'] ) {
				echo '<ol class="fbcc-steps">';
				foreach ( $task['steps'] as $i => $line ) {
					$n     = $i + 1;
					$state = $n < $task['step'] ? 'done' : ( $n === (int) $task['step'] ? 'now' : 'next' );
					echo '<li class="' . esc_attr( $state ) . '">' . esc_html( $line ) . '</li>';
				}
				echo '</ol>';
			}
			if ( $task['note'] ) {
				echo '<p class="fbcc-muted">' . esc_html( $task['note'] ) . '</p>';
			}
			echo '</section>';
		}

		echo '<div class="fbcc-grid2">';

		echo '<section class="fbcc-card"><h2>Connection</h2><dl class="fbcc-dl">';
		echo '<dt>Status</dt><dd>' . ( ! $s['enabled'] ? '<strong class="fbcc-t-red">Switched off</strong>' : ( $c['session'] ? '<strong class="fbcc-t-green">● Connected</strong> — last call ' . esc_html( self::when( $c['last_call'] ) ) : 'Not connected yet — see Setup' ) ) . '</dd>';
		echo '<dt>Client</dt><dd>' . ( $client ? esc_html( $client['name'] ) . ( $by ? ' — approved by ' . esc_html( $by->display_name ) : '' ) : '—' ) . '</dd>';
		echo '<dt>Endpoint</dt><dd><code>' . esc_html( FBCC_OAuth::mcp_url() ) . '</code></dd>';
		echo '<dt>Auth</dt><dd>OAuth sign-in' . ( $c['session'] ? ', expires ' . esc_html( human_time_diff( time(), strtotime( $c['session']->expires_at . ' UTC' ) ) ) . ' from now unless used' : '' ) . '</dd>';
		echo '<dt>Acts as</dt><dd>WordPress user <strong>claude-agent</strong> (Editor role, cannot sign in)</dd>';
		echo '<dt>Today</dt><dd>' . (int) $counts['reads'] . ' reads · ' . (int) $counts['changes'] . ' changes · ' . (int) $counts['queued'] . ' waiting · ' . (int) $counts['blocked'] . ' blocked</dd>';
		echo '</dl><div class="fbcc-row">';
		echo '<button type="button" class="button" onclick="navigator.clipboard&&navigator.clipboard.writeText(' . esc_attr( wp_json_encode( FBCC_OAuth::mcp_url() ) ) . ');this.textContent=\'Copied\'">Copy endpoint URL</button>';
		self::form_open( 'revoke', 'style="display:inline"' );
		echo '<button class="button">Revoke all sign-ins</button></form>';
		echo '</div></section>';

		echo '<section class="fbcc-card"><h2>Safety</h2><ul class="fbcc-safe">';
		echo '<li><span>AI Engine emergency write lock</span><strong class="' . ( FBCC_Store::emergency_lock() ? 'fbcc-t-red' : '' ) . '">' . ( FBCC_Store::emergency_lock() ? 'ON — no changes allowed' : ( FBCC_Store::emergency_lock_defined() ? 'Set, but ignored by your choice' : 'Off' ) ) . '</strong></li>';
		echo '<li><span>Lab lock (plugins &amp; site changes)</span><strong class="' . ( FBCC_Store::lab_lock() ? 'fbcc-t-red' : '' ) . '">' . ( FBCC_Store::lab_lock_forced() ? 'ON — Online Lab, always' : ( FBCC_Store::lab_lock() ? 'ON' : 'Off' ) ) . '</strong></li>';
		echo '<li><span>Live content, publishing, plugin updates</span><strong>Always need your approval</strong></li>';
		echo '<li><span>Undo</span><strong>Revision saved before every edit</strong></li>';
		echo '</ul>';
		if ( $s['enabled'] ) {
			self::form_open( 'kill', 'onsubmit="return confirm(\'Disconnect Claude now? All sign-ins are revoked.\')"' );
			echo '<button class="fbcc-kill">Disconnect Claude now (kill switch)</button></form>';
		} else {
			self::form_open( 'enable' );
			echo '<button class="button button-primary button-hero">Switch the connector back on</button></form>';
		}
		echo '</section></div>';

		self::level_form( $s, 'overview' );

		$pending = FBCC_Store::approvals( 'pending', 5 );
		if ( $pending ) {
			echo '<section class="fbcc-card"><h2>Waiting for your approval</h2>';
			self::approval_rows( $pending );
			echo '</section>';
		}

		echo '<section class="fbcc-card"><div class="fbcc-head"><h2>Recent activity</h2><a href="' . esc_url( self::url( 'activity' ) ) . '">Full log</a></div>';
		self::activity_table( FBCC_Store::activity( 8 ) );
		echo '</section>';
	}

	private static function level_form( $s, $tab ) {
		$levels = array(
			'read'       => array( 'Read-only', 'Read pages, posts, plugins and site health. Changes nothing.' ),
			'editor'     => array( 'Content editor', 'Plus: create drafts and edit drafts. Changes to live pages and publishing go to approvals.' ),
			'maintainer' => array( 'Site maintainer', 'Plus: request plugin updates. Every one goes to approvals. Blocked while the lab lock is on.' ),
		);
		echo '<section class="fbcc-card"><div class="fbcc-head"><h2>Permission level</h2><span class="fbcc-muted">Takes effect on Claude\'s next call.</span></div>';
		self::form_open( 'save' );
		echo '<input type="hidden" name="tab" value="' . esc_attr( $tab ) . '"><div class="fbcc-levels">';
		foreach ( $levels as $k => $l ) {
			echo '<label class="fbcc-level' . ( $s['level'] === $k ? ' is-on' : '' ) . '"><input type="radio" name="level" value="' . esc_attr( $k ) . '"' . checked( $s['level'], $k, false ) . '><span><strong>' . esc_html( $l[0] ) . '</strong><span>' . esc_html( $l[1] ) . '</span></span></label>';
		}
		echo '</div><p><button class="button button-primary">Save level</button></p></form></section>';
	}

	/* -- Tools ----------------------------------------------------- */

	private static function tab_tools( $s ) {
		self::level_form( $s, 'tools' );
		$levels = FBCC_Store::levels();
		$have   = $levels[ $s['level'] ];
		echo '<section class="fbcc-card"><div class="fbcc-head"><h2>Tools Claude can use</h2><span class="fbcc-muted">Switch single tools off without changing the level.</span></div>';
		self::form_open( 'save' );
		echo '<input type="hidden" name="tab" value="tools"><input type="hidden" name="tools_present" value="1"><div class="fbcc-tools">';
		foreach ( FBCC_Tools::all() as $name => $t ) {
			$avail = $levels[ $t['level'] ] <= $have;
			$kind  = 'approval' === $t['kind'] ? 'approval' : ( 'write' === $t['kind'] ? 'write' : 'read' );
			echo '<label class="' . ( $avail ? '' : 'is-dim' ) . '"><input type="checkbox" name="tools_on[]" value="' . esc_attr( $name ) . '"' . checked( ! in_array( $name, (array) $s['tools_off'], true ), true, false ) . '> <code>' . esc_html( $name ) . '</code> <span class="fbcc-k fbcc-k-' . esc_attr( $kind ) . '">' . esc_html( $kind ) . ( $avail ? '' : ' · needs ' . esc_html( self::level_name( $t['level'] ) ) ) . '</span><small>' . esc_html( $t['description'] ) . '</small></label>';
		}
		echo '</div><p><button class="button button-primary">Save tools</button></p></form></section>';

		if ( FBCC_Store::emergency_lock_defined() || FBCC_Store::lab_defined() ) {
			echo '<section class="fbcc-card"><h2>FB Software AI Engine locks</h2><p>This site sets AI Engine safety locks in <code>wp-config.php</code>. The connector obeys them unless you untick them here. Only an administrator can change this, never Claude.</p>';
			self::form_open( 'save' );
			echo '<input type="hidden" name="tab" value="tools"><input type="hidden" name="locks_present" value="1">';
			if ( FBCC_Store::emergency_lock_defined() ) {
				echo '<p><label><input type="checkbox" name="honor_emergency" value="1"' . checked( ! empty( $s['honor_emergency'] ), true, false ) . '> Obey the <strong>emergency write lock</strong> (FBSA_EMERGENCY_WRITE_LOCK) — when ticked, Claude cannot change anything</label></p>';
			} else {
				echo '<input type="hidden" name="honor_emergency" value="1">';
			}
			if ( FBCC_Store::lab_defined() ) {
				echo '<p><label><input type="checkbox" name="honor_lab" value="1"' . checked( ! empty( $s['honor_lab'] ), true, false ) . '> Obey the <strong>Online Lab</strong> lock (FBSA_ONLINE_LAB_ENABLED) — when ticked, plugin updates are always blocked</label></p>';
			} else {
				echo '<input type="hidden" name="honor_lab" value="1">';
			}
			echo '<p><button class="button">Save locks</button></p></form></section>';
		}

		echo '<section class="fbcc-card"><h2>Lab lock</h2>';
		if ( FBCC_Store::lab_lock_forced() ) {
			echo '<p>This site runs as the <strong>Online Lab</strong> (FBSA_ONLINE_LAB_ENABLED), so plugin and site changes are always locked here.</p>';
		} else {
			self::form_open( 'save' );
			echo '<input type="hidden" name="tab" value="tools"><input type="hidden" name="lab_lock_present" value="1">';
			echo '<label><input type="checkbox" name="lab_lock" value="1"' . checked( ! empty( $s['lab_lock'] ), true, false ) . '> Block plugin updates and other site-level changes, even with approval</label>';
			echo '<p><button class="button">Save</button></p></form>';
		}
		echo '</section>';
	}

	/* -- Approvals ------------------------------------------------- */

	private static function tab_approvals() {
		echo '<section class="fbcc-card"><div class="fbcc-head"><h2>Waiting for your approval</h2><span class="fbcc-muted">Claude prepared these. Nothing happens until you approve.</span></div>';
		$p = FBCC_Store::approvals( 'pending', 50 );
		if ( $p ) {
			self::approval_rows( $p, true );
		} else {
			echo '<p class="fbcc-muted">Nothing waiting.</p>';
		}
		echo '</section>';

		echo '<section class="fbcc-card"><h2>Decided</h2><div class="fbcc-scroll"><table class="widefat striped"><thead><tr><th>#</th><th>Request</th><th>Decision</th><th>When</th><th>Result</th></tr></thead><tbody>';
		$rows = array_merge( FBCC_Store::approvals( 'approved', 20 ), FBCC_Store::approvals( 'rejected', 20 ), FBCC_Store::approvals( 'failed', 20 ) );
		usort( $rows, function ( $a, $b ) {
			return strcmp( (string) $b->decided_at, (string) $a->decided_at );
		} );
		foreach ( array_slice( $rows, 0, 30 ) as $r ) {
			echo '<tr><td>' . (int) $r->id . '</td><td>' . esc_html( $r->title ) . '</td><td>' . esc_html( ucfirst( $r->status ) ) . '</td><td>' . esc_html( self::when( $r->decided_at ) ) . '</td><td>' . esc_html( $r->result ) . '</td></tr>';
		}
		if ( ! $rows ) {
			echo '<tr><td colspan="5">None yet.</td></tr>';
		}
		echo '</tbody></table></div></section>';
	}

	private static function approval_rows( $rows, $details = false ) {
		foreach ( $rows as $r ) {
			$tool   = FBCC_Tools::get( $r->tool );
			$lock   = $tool ? FBCC_Gateway::lock_reason( array_merge( $tool, array( 'kind' => 'approval' ) ) ) : '';
			$badges = array( 'content_publish' => 'PUBLISH', 'plugins_update' => 'PLUGIN', 'menu_set' => 'MENU', 'site_settings' => 'SITE', 'content_update' => 'LIVE EDIT', 'build_plan_submit' => 'BUILD PLAN', 'theme_settings_set' => 'THEME', 'widgets_set' => 'WIDGETS', 'plugins_install' => 'PLUGIN', 'theme_install' => 'THEME', 'content_trash' => 'TRASH' );
			$badge  = $badges[ $r->tool ] ?? strtoupper( $r->tool );
			$cls    = 'plugins_update' === $r->tool ? 'fbcc-b-red' : 'fbcc-b-amber';
			echo '<div class="fbcc-appr"><div class="fbcc-appr-main"><div><span class="fbcc-badge ' . esc_attr( $cls ) . '">' . esc_html( $badge ) . '</span> <strong>' . esc_html( $r->title ) . '</strong></div>';
			echo '<span class="fbcc-muted">' . ( $r->reason ? 'Reason: “' . esc_html( $r->reason ) . '” · ' : '' ) . '#' . (int) $r->id . ' · ' . esc_html( self::when( $r->created_at ) ) . '</span>';
			if ( $lock ) {
				echo '<span class="fbcc-t-red">' . esc_html( $lock ) . '</span>';
			}
			if ( $details && 'content_update' === $r->tool ) {
				$pl   = json_decode( $r->payload, true );
				$post = get_post( (int) ( $pl['id'] ?? 0 ) );
				if ( $post && isset( $pl['content'] ) && function_exists( 'wp_text_diff' ) ) {
					$diff = wp_text_diff( $post->post_content, $pl['content'], array( 'title_left' => 'Live now', 'title_right' => 'Claude\'s change' ) );
					echo '<details><summary>View diff</summary><div class="fbcc-diff">' . ( $diff ? wp_kses_post( $diff ) : '<p>No content difference.</p>' ) . '</div></details>';
				}
			}
			echo '</div><div class="fbcc-row">';
			self::form_open( 'decide', 'style="display:inline"' );
			echo '<input type="hidden" name="id" value="' . (int) $r->id . '"><button class="button" name="reject" value="1">Reject</button> ';
			echo '<button class="button button-primary" name="approve" value="1"' . ( $lock ? ' disabled' : '' ) . '>' . ( $lock ? 'Approve (locked)' : 'Approve' ) . '</button></form>';
			echo '</div></div>';
		}
	}

	/* -- Activity -------------------------------------------------- */

	private static function tab_activity() {
		$page = isset( $_GET['p'] ) ? max( 1, (int) $_GET['p'] ) : 1; // phpcs:ignore
		echo '<section class="fbcc-card"><h2>Activity log</h2>';
		$rows = FBCC_Store::activity( 50, ( $page - 1 ) * 50 );
		self::activity_table( $rows );
		echo '<p>';
		if ( $page > 1 ) {
			echo '<a class="button" href="' . esc_url( self::url( 'activity', array( 'p' => $page - 1 ) ) ) . '">Newer</a> ';
		}
		if ( count( $rows ) === 50 ) {
			echo '<a class="button" href="' . esc_url( self::url( 'activity', array( 'p' => $page + 1 ) ) ) . '">Older</a>';
		}
		echo '</p></section>';
	}

	private static function activity_table( $rows ) {
		$col = array( 'done' => 'fbcc-t-green', 'read' => '', 'info' => '', 'ready' => 'fbcc-t-amber', 'queued' => 'fbcc-t-amber', 'blocked' => 'fbcc-t-red', 'error' => 'fbcc-t-red' );
		$lab = array( 'done' => 'Done', 'read' => 'Read', 'info' => 'Info', 'ready' => 'Ready to launch', 'queued' => 'Waiting', 'blocked' => 'Blocked', 'error' => 'Error' );
		echo '<div class="fbcc-scroll"><table class="widefat striped"><thead><tr><th>Time</th><th>Tool</th><th>What happened</th><th>Result</th><th>Undo</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$ts = strtotime( $r->created_at . ' UTC' );
			echo '<tr><td>' . esc_html( wp_date( 'j M H:i', $ts ) ) . '</td><td><code>' . esc_html( $r->tool ) . '</code></td><td>' . esc_html( $r->summary ) . '</td>';
			echo '<td><strong class="' . esc_attr( $col[ $r->result ] ?? '' ) . '">' . esc_html( $lab[ $r->result ] ?? $r->result ) . '</strong></td>';
			echo '<td>' . ( $r->undo_url ? '<a href="' . esc_url( $r->undo_url ) . '">' . ( false !== strpos( $r->undo_url, 'revision.php' ) ? 'Restore revision' : 'Open' ) . '</a>' : '—' ) . '</td></tr>';
		}
		if ( ! $rows ) {
			echo '<tr><td colspan="5">No activity yet.</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	/* -- Setup ----------------------------------------------------- */

	private static function tab_setup() {
		$url = FBCC_OAuth::mcp_url();
		self::permalink_notice();
		echo '<section class="fbcc-card"><h2>Connect Claude to this site</h2><ol class="fbcc-setup">';
		echo '<li>Choose a permission level on the Overview tab. Start with <strong>Read-only</strong>.</li>';
		echo '<li>In Claude, open <strong>Settings → Connectors → Add custom connector</strong>.</li>';
		echo '<li>Name: <code>' . esc_html( get_bloginfo( 'name' ) ) . '</code> — URL: <code>' . esc_html( $url ) . '</code> <button type="button" class="button button-small" onclick="navigator.clipboard&&navigator.clipboard.writeText(' . esc_attr( wp_json_encode( $url ) ) . ');this.textContent=\'Copied\'">Copy</button>. Leave the OAuth fields empty.</li>';
		echo '<li>Click <strong>Connect</strong>. You land on a page of this site: sign in as an administrator and click <strong>Allow Claude</strong>.</li>';
		echo '<li>Back here the status shows <strong>Connected</strong>. Every call appears in the Activity log.</li></ol></section>';

		$check = rest_url( FBCC_NS . '/claude/auth-check' );
		echo '<section class="fbcc-card"><h2>Server check</h2><p>Claude signs every call with an <code>Authorization</code> header. Some hosts drop it before WordPress sees it; the plugin adds a small block to the top of <code>.htaccess</code> to fix that (a copy is kept as <code>.htaccess.fbcc-backup</code>).</p>';
		echo '<p><button type="button" class="button button-primary" id="fbcc-selftest">Run server check</button> <strong id="fbcc-selftest-out"></strong></p>';
		self::form_open( 'htaccess', 'style="display:inline"' );
		echo '<button class="button">Re-apply .htaccess fix</button></form>';
		echo '<script>document.getElementById("fbcc-selftest").addEventListener("click",function(){var o=document.getElementById("fbcc-selftest-out");o.textContent="Checking…";fetch(' . wp_json_encode( $check ) . ',{credentials:"omit",headers:{"Authorization":"Bearer fbcc_selftest"}}).then(function(r){return r.json()}).then(function(d){o.textContent=d.header_reaches_wordpress?"✓ Passed — Claude can connect.":"✗ The header is dropped. Click Re-apply, then check again. If it still fails, the host or CDN strips it.";o.style.color=d.header_reaches_wordpress?"#0f6b3a":"#8a2424";}).catch(function(){o.textContent="Could not run the check.";});});</script>';
		echo '</section>';

		echo '<section class="fbcc-card" id="fbcc-diag"><h2>Full connection test</h2><p>Signs in with a 2-minute test key and calls the endpoint through the public address, exactly like Claude does.</p>';
		self::form_open( 'selftest' );
		echo '<button class="button button-primary">Run full test</button></form>';
		$res = get_transient( 'fbcc_selftest_' . get_current_user_id() );
		if ( is_array( $res ) ) {
			echo '<pre class="fbcc-pre">' . esc_html( implode( "\n", $res ) ) . '</pre>';
		}
		echo '<h2 style="margin-top:18px">Recent requests from Claude</h2><p class="fbcc-muted">Last 40 requests to the sign-in and connector addresses (UTC). No keys are stored.</p>';
		$trace = array_reverse( (array) get_option( 'fbcc_trace', array() ) );
		echo '<div class="fbcc-scroll"><table class="widefat striped"><thead><tr><th>Time</th><th>Where</th><th>Method</th><th>Details</th><th>Client</th></tr></thead><tbody>';
		foreach ( $trace as $t ) {
			$d = $t;
			unset( $d['t'], $d['ep'], $d['m'], $d['ua'] );
			$txt = array();
			foreach ( $d as $k => $v ) {
				$txt[] = $k . ': ' . $v;
			}
			echo '<tr><td>' . esc_html( $t['t'] ) . '</td><td>' . esc_html( $t['ep'] ) . '</td><td>' . esc_html( strtoupper( $t['m'] ) ) . '</td><td><code>' . esc_html( implode( ' · ', $txt ) ) . '</code></td><td>' . esc_html( $t['ua'] ) . '</td></tr>';
		}
		if ( ! $trace ) {
			echo '<tr><td colspan="5">Nothing recorded yet. Reconnect from Claude, then reload this page.</td></tr>';
		}
		echo '</tbody></table></div></section>';

		echo '<section class="fbcc-card"><h2>Check the endpoints</h2><p>Open these in a browser — each should show JSON. If the first one shows a 404 page, your server does not pass <code>/.well-known/</code> to WordPress.</p><ul class="fbcc-links">';
		foreach ( array( FBCC_OAuth::resource_metadata_url(), home_url( '/.well-known/oauth-authorization-server' ) ) as $u ) {
			echo '<li><a href="' . esc_url( $u ) . '" target="_blank" rel="noopener">' . esc_html( $u ) . '</a></li>';
		}
		echo '</ul></section>';
	}
}
