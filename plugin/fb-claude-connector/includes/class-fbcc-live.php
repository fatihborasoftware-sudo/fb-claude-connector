<?php
/**
 * Watch Me Live: a full-screen wp-admin view that animates every connector call.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FBCC_Live {

	const SLUG = 'fbcc-live';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 100 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'admin_title', function ( $t ) {
			return ( isset( $_GET['page'] ) && self::SLUG === $_GET['page'] ) ? 'Watch Claude live ‹ ' . get_bloginfo( 'name' ) : $t; // phpcs:ignore
		} );
	}

	public static function url() {
		return admin_url( 'admin.php?page=' . self::SLUG );
	}

	public static function menu() {
		add_submenu_page( '', 'Watch Claude live', 'Watch Claude live', 'manage_options', self::SLUG, array( __CLASS__, 'page' ) );
	}

	public static function routes() {
		register_rest_route( FBCC_NS, '/claude/live', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'feed' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		) );
		register_rest_route( FBCC_NS, '/claude/decide', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'decide' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'args'                => array(
				'id'       => array( 'required' => true, 'sanitize_callback' => 'absint' ),
				'decision' => array( 'required' => true, 'enum' => array( 'approve', 'reject' ) ),
			),
		) );
	}

	/** Approve or reject from the live screen (administrators, REST nonce). */
	public static function decide( WP_REST_Request $req ) {
		$id = (int) $req->get_param( 'id' );
		if ( 'approve' === $req->get_param( 'decision' ) ) {
			$r = FBCC_Gateway::approve( $id );
			if ( is_wp_error( $r ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'message' => $r->get_error_message() ), 200 );
			}
			return new WP_REST_Response( array( 'ok' => true, 'message' => 'Approved: ' . ( $r['_summary'] ?? '#' . $id ) ), 200 );
		}
		$r = FBCC_Gateway::reject( $id );
		return new WP_REST_Response( array( 'ok' => ! is_wp_error( $r ), 'message' => is_wp_error( $r ) ? $r->get_error_message() : 'Rejected. Nothing was changed.' ), 200 );
	}

	/** Which stage window a tool belongs to. */
	public static function window_for( $tool, $result ) {
		if ( 'browser' === $tool ) {
			return 'admin';
		}
		if ( 'queued' === $result || 'ready' === $result || 0 === strpos( $tool, 'build_plan' ) ) {
			return 'approvals';
		}
		if ( 0 === strpos( $tool, 'mindmap' ) ) {
			return 'mind';
		}
		if ( 0 === strpos( $tool, 'content' ) || in_array( $tool, array( 'form_create', 'post_settings_set' ), true ) ) {
			return 'pages';
		}
		if ( 0 === strpos( $tool, 'media' ) || 0 === strpos( $tool, 'backup_' ) ) {
			return 'media';
		}
		if ( in_array( $tool, array( 'menu_set', 'site_settings', 'theme_settings_set', 'theme_settings_get', 'widgets_set', 'plugins_install', 'custom_css_set', 'site_check' ), true ) ) {
			return 'site';
		}
		if ( in_array( $tool, array( 'approvals_list' ), true ) ) {
			return 'approvals';
		}
		return 'plugins'; // site_health, plugins_*, settings, oauth …
	}

	public static function feed( WP_REST_Request $req ) {
		global $wpdb;
		$since = max( 0, (int) $req->get_param( 'since' ) );
		$rows  = $since
			? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}fbcc_activity WHERE id > %d ORDER BY id ASC LIMIT 50", $since ) )
			: array_reverse( $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}fbcc_activity ORDER BY id DESC LIMIT 12" ) );

		$events = array();
		foreach ( $rows as $r ) {
			$events[] = array(
				'id'      => (int) $r->id,
				'time'    => wp_date( 'H:i:s', strtotime( $r->created_at . ' UTC' ) ),
				'ts'      => strtotime( $r->created_at . ' UTC' ),
				'tool'    => $r->tool,
				'summary' => $r->summary,
				'result'  => $r->result,
				'win'     => self::window_for( $r->tool, $r->result ),
			);
		}
		$last = $events ? end( $events )['id'] : $since;

		$agent   = FBCC_Store::agent_user_id();
		$preview = null;
		$drafts  = 0;
		// The page that changed most recently, whoever saved it (Claude's draft or your approval).
		$q = get_posts( array(
			'post_type'        => array( 'page', 'post' ),
			'post_status'      => array( 'draft', 'pending', 'publish', 'private' ),
			'posts_per_page'   => 1,
			'orderby'          => 'modified',
			'order'            => 'DESC',
			'suppress_filters' => false,
		) );
		if ( $q ) {
			$preview = array(
				'id'     => $q[0]->ID,
				'title'  => html_entity_decode( get_the_title( $q[0] ), ENT_QUOTES ),
				'status' => $q[0]->post_status,
				'url'    => 'publish' === $q[0]->post_status ? get_permalink( $q[0] ) : get_preview_post_link( $q[0] ),
				'stamp'  => $q[0]->post_modified_gmt,
			);
		}
		if ( $agent ) {
			$drafts = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author = %d AND post_type IN ('page','post') AND post_status = 'draft'", $agent ) );
		}

		$pending = array();
		foreach ( FBCC_Store::approvals( 'pending', 20 ) as $a ) {
			$tool      = FBCC_Tools::get( $a->tool );
			$pending[] = array(
				'id'     => (int) $a->id,
				'title'  => $a->title,
				'reason' => $a->reason,
				'tool'   => $a->tool,
				'lock'   => $tool ? FBCC_Gateway::lock_reason( array_merge( $tool, array( 'kind' => 'approval' ) ) ) : '',
				'diff'   => 'content_update' === $a->tool,
			);
		}

		$counts = FBCC_Store::today_counts();
		return new WP_REST_Response( array(
			'task'    => FBCC_Store::task(),
			'finished' => FBCC_Store::finished_task(),
			'browser' => FBCC_Browser::live_info(),
			'now'     => time(),
			'events'  => $events,
			'last'    => $last,
			'preview' => $preview,
			'stats'   => array(
				'calls'   => $counts['reads'] + $counts['changes'] + $counts['queued'] + $counts['blocked'],
				'tokens'  => FBCC_Store::tokens_today(),
				'drafts'  => $drafts,
				'waiting' => FBCC_Store::pending_count(),
			),
			'pending' => $pending,
			'plan'    => FBCC_Plan::live_info(),
			'level'   => FBCC_Store::settings()['level'],
			'locks'   => array( 'emergency' => FBCC_Store::emergency_lock(), 'lab' => FBCC_Store::lab_lock() ),
			'live'    => (bool) FBCC_Store::task(),
		) );
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'fbcc-live', FBCC_URL . 'assets/live.css', array(), FBCC_VERSION );
		wp_enqueue_script( 'fbcc-live', FBCC_URL . 'assets/live.js', array(), FBCC_VERSION, true );
		wp_localize_script( 'fbcc-live', 'FBCC_LIVE', array(
			'api'      => esc_url_raw( rest_url( FBCC_NS . '/claude/live' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'site'     => wp_parse_url( home_url(), PHP_URL_HOST ),
			'back'     => admin_url( 'admin.php?page=fbcc' ),
			'approve'  => admin_url( 'admin.php?page=fbcc&tab=approvals' ),
			'home'     => home_url( '/' ),
			'decide'   => esc_url_raw( rest_url( FBCC_NS . '/claude/decide' ) ),
			'launch'   => esc_url_raw( rest_url( FBCC_NS . '/claude/launch' ) ),
			'screen'   => esc_url_raw( rest_url( FBCC_NS . '/claude/screen' ) ),
			'voiceApi' => esc_url_raw( rest_url( FBCC_NS . '/claude/voice' ) ),
			'linkPage' => FBCC_Browser::url(),
			'name'     => wp_get_current_user()->first_name ? wp_get_current_user()->first_name : wp_get_current_user()->display_name,
			'ui'       => FBCC_I18n::lang(),
			'i18n'     => FBCC_I18n::js(),
		) );
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		FBCC_I18n::render( array( __CLASS__, 'render_page' ) );
	}

	public static function render_page() {
		$dock = array( 'mind' => 'Mind Map', 'pages' => 'Pages', 'media' => 'Media', 'approvals' => 'Approvals', 'site' => 'Site', 'admin' => 'Admin' );
		?>
<div id="wl" class="wl" aria-live="polite">
	<header class="wl-top">
		<span class="wl-live"><i class="wl-dot"></i><b id="wl-state">IDLE</b></span>
		<div class="wl-now">
			<div class="wl-now-line"><span class="wl-step" id="wl-step"></span><strong id="wl-title">Nothing is running. Start a task in Claude and watch it here.</strong></div>
			<div class="wl-bar"><i id="wl-bar"></i></div>
		</div>
		<div class="wl-chips">
			<span><b id="s-calls">0</b> calls</span>
			<span><b id="s-tokens">0</b> tok</span>
			<a href="#" class="wl-chip-wait" id="s-wait-chip"><b id="s-wait">0</b> waiting</a>
		</div>
		<div class="wl-vwrap">
			<button type="button" class="wl-voicebtn" id="wl-voice" aria-pressed="false" aria-expanded="false" aria-controls="wl-vpop"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5L6 9H2v6h4l5 4V5z"/><path d="M15.5 8.5a5 5 0 010 7"/><path d="M18.5 5.5a9 9 0 010 13"/></svg><span id="wl-voice-t">Voice off</span></button>
			<div class="wl-vpop" id="wl-vpop" role="dialog" aria-label="Voice briefs" hidden>
				<div class="wl-vhead"><strong>Voice briefs</strong><label class="wl-vsw"><input type="checkbox" id="v-on"> On</label></div>
				<p class="wl-muted">Your browser reads Claude’s briefs aloud while you watch. Off by default; remembered on this computer.</p>
				<fieldset class="wl-vset"><legend class="wl-k">WHAT TO READ</legend>
					<label><input type="checkbox" data-read="start" checked> <span><b>Start brief</b> — what Claude is about to do</span></label>
					<label><input type="checkbox" data-read="step" checked> <span><b>Each step</b> — backup, pages, blog, theme, menu</span></label>
					<label><input type="checkbox" data-read="wait" checked> <span><b>Waiting for you</b> — an approval</span></label>
					<label><input type="checkbox" data-read="finish" checked> <span><b>Finish brief</b> — what was done, what is left</span></label>
					<label><input type="checkbox" data-read="click"> <span><b>Every click</b> — browser actions too (chatty)</span></label>
				</fieldset>
				<div class="wl-vgrid">
					<label>Language<select id="v-lang"><option value="en">English</option><option value="tr">Türkçe</option></select></label>
					<label>Voice<select id="v-voice"><option value="">Browser default</option></select></label>
				</div>
				<label class="wl-vrate">Speed · <span id="v-rate-t">1.0×</span><input type="range" id="v-rate" min="0.7" max="1.4" step="0.1" value="1"></label>
				<p class="wl-muted" id="v-langnote">Claude writes its briefs in this language from the next task on.</p>
				<div class="wl-vrow"><button type="button" class="wl-btn wl-all" id="v-test">Test the voice</button><button type="button" class="wl-btn ghost" id="v-done">Done</button></div>
			</div>
		</div>
		<a class="wl-exit" href="<?php echo esc_url( admin_url( 'admin.php?page=fbcc' ) ); ?>">Exit</a>
	</header>

	<div class="wl-body">
		<main class="wl-site">
			<div class="wl-nav">
				<button type="button" class="wl-icon" id="wl-back" aria-label="Back">←</button>
				<button type="button" class="wl-icon" id="wl-fwd" aria-label="Forward">→</button>
				<button type="button" class="wl-icon" id="wl-reload" aria-label="Reload">↻</button>
				<div class="wl-addr"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/></svg><span id="wl-host"></span><b id="wl-path">/</b></div>
				<span class="wl-src" id="wl-src" hidden><i></i>Claude’s browser · mirrored live</span>
				<button type="button" class="wl-follow is-on" id="wl-follow" aria-pressed="true"><i></i><span>Following Claude</span></button>
				<a class="wl-newtab" id="wl-newtab" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener">New tab ↗</a>
			</div>
			<div class="wl-frame">
				<iframe id="wl-frame" title="Your website — click around while Claude works"></iframe>
				<div class="wl-screen" id="wl-screen" hidden><iframe id="wl-screen-f" title="Claude’s browser — live copy (view only)" sandbox="allow-same-origin" tabindex="-1"></iframe></div>
				<div class="wl-toast" id="wl-toast" hidden><i></i><span id="wl-toast-t"></span><button type="button" id="wl-toast-go">Show me</button><button type="button" class="wl-x" id="wl-toast-x" aria-label="Dismiss">✕</button></div>
				<div class="wl-dock" aria-label="Where Claude is working">
					<span class="wl-dock-k">CLAUDE IS IN</span>
					<?php foreach ( $dock as $k => $label ) : ?>
						<span class="wl-dock-i" data-win="<?php echo esc_attr( $k ); ?>"><i></i><?php echo esc_html( $label ); ?></span>
					<?php endforeach; ?>
				</div>
			</div>
		</main>

		<aside class="wl-rail">
			<div class="wl-tabs" role="tablist">
				<button type="button" role="tab" data-tab="now" aria-selected="true">Now</button>
				<button type="button" role="tab" data-tab="approve" aria-selected="false">Approve (<span id="wl-tab-n">0</span>)</button>
				<button type="button" role="tab" data-tab="feed" aria-selected="false">Feed</button>
			</div>
			<div class="wl-panel" data-panel="now">
				<div class="wl-speak" id="wl-speak" hidden>
					<div class="wl-speak-h"><span class="wl-k" id="wl-speak-k">SPEAKING NOW</span><span class="wl-wave" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span></div>
					<p id="wl-speak-t"></p>
					<div class="wl-vrow"><button type="button" class="wl-btn ghost" id="wl-repeat">Repeat</button><button type="button" class="wl-btn ghost" id="wl-skip">Skip</button></div>
				</div>
				<span class="wl-k">THE PLAN</span>
				<strong class="wl-plan-t" id="wl-plan-t">Waiting for a task</strong>
				<ol class="wl-plan" id="wl-plan"></ol>
				<span class="wl-k">LAST ACTIONS</span>
				<ul class="wl-mini" id="wl-mini"></ul>
			</div>
			<div class="wl-panel" data-panel="approve" hidden>
				<div class="wl-planbox" id="wl-planbox" hidden></div>
				<div class="wl-pend" id="wl-pend"></div>
				<p class="wl-muted" id="wl-pend-empty">Nothing is waiting for you.</p>
				<p class="wl-muted">Approving reloads the site on the left and highlights what changed.</p>
			</div>
			<div class="wl-panel" data-panel="feed" hidden><ol class="wl-feed" id="wl-feed"></ol></div>
			<footer class="wl-foot"><span id="wl-level">—</span><a href="<?php echo esc_url( admin_url( 'admin.php?page=fbcc' ) ); ?>">Kill switch</a></footer>
		</aside>
	</div>
</div>
		<?php
	}
}
