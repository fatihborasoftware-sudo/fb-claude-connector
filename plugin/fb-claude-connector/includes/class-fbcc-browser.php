<?php
/**
 * Follow Claude's browser (0.7.0).
 *
 * When Claude has to use a real browser on this site (a screen the connector has no tool for),
 * it first opens Claude Connection → "Link Claude's browser" and clicks Link. That browser gets a
 * private cookie. Only a linked browser loads assets/agent.js, which reports to Watch Me Live:
 *   – each page it opens and each click / save (written to the activity log, source "browser")
 *   – a live mirror of the screen: a sanitised copy of the page (no scripts, no passwords, no nonces)
 * The link ends when Claude reports the task done, when the owner unlinks, or after 2 hours.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FBCC_Browser {

	const OPT     = 'fbcc_browser_link';
	const SCREEN  = 'fbcc_browser_screen';
	const NOW     = 'fbcc_browser_now';
	const VOICE   = 'fbcc_voice_lang';
	const COOKIE  = 'fbcc_agent';
	const TTL     = 7200;       // 2 hours
	const MAX_SNAP = 3000000;   // 3 MB per screen copy
	const PAGE    = 'fbcc-link';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 101 );
		add_action( 'admin_post_fbcc_browser_link', array( __CLASS__, 'action_link' ) );
		add_action( 'admin_post_fbcc_browser_unlink', array( __CLASS__, 'action_unlink' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'agent_assets' ), 50 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'agent_assets' ), 50 );
		add_action( 'customize_controls_enqueue_scripts', array( __CLASS__, 'agent_assets' ), 50 );
		add_filter( 'admin_title', function ( $t ) {
			return ( isset( $_GET['page'] ) && FBCC_Browser::PAGE === $_GET['page'] ) ? 'Link Claude’s browser ‹ ' . get_bloginfo( 'name' ) : $t; // phpcs:ignore
		} );
	}

	/* ------------------------------------------------------------ */
	/* Link state                                                   */
	/* ------------------------------------------------------------ */

	public static function link() {
		$l = get_option( self::OPT );
		if ( ! is_array( $l ) || empty( $l['hash'] ) ) {
			return null;
		}
		if ( time() > (int) $l['expires'] ) {
			self::end( 'expired after 2 hours' );
			return null;
		}
		return $l;
	}

	/** Is THIS request coming from the linked browser? */
	public static function is_agent_request() {
		$l = self::link();
		if ( ! $l || empty( $_COOKIE[ self::COOKIE ] ) ) {
			return false;
		}
		if ( ! current_user_can( 'manage_options' ) || (int) $l['user'] !== get_current_user_id() ) {
			return false;
		}
		$raw = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) );
		return hash_equals( (string) $l['hash'], hash( 'sha256', $raw ) );
	}

	/** Ends the link (task done, owner unlinked, expiry). Cookie is cleared on the next visit. */
	public static function end( $why = '' ) {
		if ( get_option( self::OPT ) ) {
			delete_option( self::OPT );
			FBCC_Store::log( 'browser', 'Claude’s browser unlinked' . ( $why ? ' — ' . $why : '' ), 'info' );
		}
		delete_option( self::SCREEN );
	}

	public static function now() {
		$n = get_option( self::NOW );
		return is_array( $n ) ? $n : null;
	}

	public static function voice_lang() {
		$l = get_option( self::VOICE, 'en' );
		return in_array( $l, array( 'en', 'tr' ), true ) ? $l : 'en';
	}

	/* ------------------------------------------------------------ */
	/* The link page                                                */
	/* ------------------------------------------------------------ */

	public static function url() {
		return admin_url( 'admin.php?page=' . self::PAGE );
	}

	public static function menu() {
		add_submenu_page( '', 'Link Claude’s browser', 'Link Claude’s browser', 'manage_options', self::PAGE, array( __CLASS__, 'page' ) );
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap fbcc"><div class="fbcc-title"><h1>Follow Claude’s browser</h1></div>';
		if ( isset( $_GET['fbcc_msg'] ) ) { // phpcs:ignore
			echo '<div class="notice notice-success"><p>' . esc_html( wp_unslash( rawurldecode( $_GET['fbcc_msg'] ) ) ) . '</p></div>'; // phpcs:ignore
		}
		self::card( true );
		echo '</div>';
	}

	/** Overview card (also the whole link page). */
	public static function card( $on_link_page = false ) {
		$l    = self::link();
		$mine = self::is_agent_request();
		echo '<section class="fbcc-card fbcc-browser"><div class="fbcc-head"><h2>Follow Claude’s browser</h2>';
		echo $l ? '<span class="fbcc-pill fbcc-green">Linked</span>' : '<span class="fbcc-pill">Not linked</span>';
		echo '</div>';
		if ( $l ) {
			$who = get_userdata( (int) $l['user'] );
			echo '<dl class="fbcc-dl">';
			echo '<dt>Linked since</dt><dd>' . esc_html( wp_date( 'j M Y, H:i', (int) $l['since'] ) ) . ( $mine ? ' — <strong>this is the linked browser</strong>' : '' ) . '</dd>';
			echo '<dt>Signed in as</dt><dd>' . esc_html( $who ? $who->user_login : '—' ) . '</dd>';
			echo '<dt>Mirroring</dt><dd>Pages and clicks · live screen</dd>';
			echo '<dt>Ends</dt><dd>When the task finishes, or at ' . esc_html( wp_date( 'H:i', (int) $l['expires'] ) ) . '</dd>';
			echo '</dl><div class="fbcc-row">';
			echo '<a class="button button-primary" href="' . esc_url( FBCC_Live::url() ) . '">▶ Watch Claude live</a>';
			self::form( 'unlink', 'Unlink now', 'button' );
			echo '</div>';
		} else {
			echo '<p class="fbcc-muted">Claude opens this page in its own browser and clicks the button below before it works on a screen the connector has no tool for. From then on Watch Me Live shows that browser: the page it is on, each click and save, and a live copy of the screen.</p>';
			if ( $on_link_page ) {
				echo '<div class="fbcc-row">';
				self::form( 'link', 'Link this browser as Claude’s', 'button button-primary button-hero' );
				echo '</div>';
			} else {
				echo '<p><a class="button" href="' . esc_url( self::url() ) . '">Open the link page</a> <span class="fbcc-muted">— this is the page Claude opens: <code>' . esc_html( self::url() ) . '</code></span></p>';
			}
		}
		echo '<ul class="fbcc-safe">';
		echo '<li><span>Only the linked browser reports</span><strong>Your own browser is never mirrored</strong></li>';
		echo '<li><span>Only pages on this site</span><strong>Other sites show as “outside the site”</strong></li>';
		echo '<li><span>Private by design</span><strong>Passwords, hidden fields and scripts are never copied</strong></li>';
		echo '</ul></section>';
	}

	private static function form( $what, $label, $class ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
		echo '<input type="hidden" name="action" value="fbcc_browser_' . esc_attr( $what ) . '">';
		wp_nonce_field( 'fbcc_browser_' . $what );
		echo '<button class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button></form>';
	}

	public static function action_link() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		check_admin_referer( 'fbcc_browser_link' );
		$raw = 'fbcc_br_' . wp_generate_password( 40, false, false );
		update_option( self::OPT, array(
			'hash'    => hash( 'sha256', $raw ),
			'user'    => get_current_user_id(),
			'since'   => time(),
			'expires' => time() + self::TTL,
		), false );
		setcookie( self::COOKIE, $raw, array(
			'expires'  => time() + self::TTL,
			'path'     => COOKIEPATH ? COOKIEPATH : '/',
			'domain'   => (string) COOKIE_DOMAIN,
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		) );
		$_COOKIE[ self::COOKIE ] = $raw;
		FBCC_Store::log( 'browser', 'Claude’s browser linked — Watch Me Live now follows it', 'info' );
		wp_safe_redirect( add_query_arg( 'fbcc_msg', rawurlencode( 'This browser is now linked as Claude’s. Watch Me Live follows it.' ), self::url() ) );
		exit;
	}

	public static function action_unlink() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		check_admin_referer( 'fbcc_browser_unlink' );
		self::end( 'by ' . wp_get_current_user()->display_name );
		setcookie( self::COOKIE, '', time() - 3600, COOKIEPATH ? COOKIEPATH : '/', (string) COOKIE_DOMAIN, is_ssl(), true );
		wp_safe_redirect( admin_url( 'admin.php?page=fbcc&fbcc_msg=' . rawurlencode( 'Claude’s browser is unlinked.' ) ) );
		exit;
	}

	/* ------------------------------------------------------------ */
	/* The agent script (linked browser only)                       */
	/* ------------------------------------------------------------ */

	public static function agent_assets() {
		static $done = false;
		if ( $done || ! is_user_logged_in() || ! self::is_agent_request() ) {
			return;
		}
		// Never record Watch Me Live itself.
		if ( is_admin() && isset( $_GET['page'] ) && FBCC_Live::SLUG === $_GET['page'] ) { // phpcs:ignore
			return;
		}
		$done = true;
		wp_enqueue_script( 'fbcc-agent', FBCC_URL . 'assets/agent.js', array(), FBCC_VERSION, true );
		wp_localize_script( 'fbcc-agent', 'FBCC_AGENT', array(
			'event'  => esc_url_raw( rest_url( FBCC_NS . '/claude/browser' ) ),
			'screen' => esc_url_raw( rest_url( FBCC_NS . '/claude/screen' ) ),
			'nonce'  => wp_create_nonce( 'wp_rest' ),
			'admin'  => is_admin(),
		) );
	}

	/* ------------------------------------------------------------ */
	/* REST                                                         */
	/* ------------------------------------------------------------ */

	public static function routes() {
		$admin = function () {
			return current_user_can( 'manage_options' );
		};
		$agent = function () {
			return FBCC_Browser::is_agent_request();
		};
		register_rest_route( FBCC_NS, '/claude/browser', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'event' ),
			'permission_callback' => $agent,
		) );
		register_rest_route( FBCC_NS, '/claude/screen', array(
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'screen_put' ),
				'permission_callback' => $agent,
			),
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'screen_get' ),
				'permission_callback' => $admin,
			),
		) );
		register_rest_route( FBCC_NS, '/claude/voice', array(
			'methods'             => 'POST',
			'callback'            => function ( WP_REST_Request $r ) {
				$l = (string) $r->get_param( 'lang' );
				update_option( FBCC_Browser::VOICE, in_array( $l, array( 'en', 'tr' ), true ) ? $l : 'en', false );
				return new WP_REST_Response( array( 'ok' => true, 'lang' => FBCC_Browser::voice_lang() ) );
			},
			'permission_callback' => $admin,
		) );
	}

	private static function clip( $s, $n ) {
		$s = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $s ) ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $s, 0, $n ) : substr( $s, 0, $n );
	}

	/** Same-site URL, or '' for anything else. */
	private static function local_url( $u ) {
		$u    = esc_url_raw( (string) $u );
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		return ( $u && wp_parse_url( $u, PHP_URL_HOST ) === $host ) ? $u : '';
	}

	/** A page a person would recognise: "WPvivid Backup (admin)". */
	private static function place( $title, $url ) {
		$title = self::clip( $title, 80 );
		$title = preg_replace( '/\s*[‹<|–—-]\s*' . preg_quote( get_bloginfo( 'name' ), '/' ) . '.*$/u', '', $title );
		$title = preg_replace( '/\s*—\s*WordPress$/u', '', $title );
		return ( '' !== trim( $title ) ? trim( $title ) : wp_parse_url( $url, PHP_URL_PATH ) ) . ( false !== strpos( $url, '/wp-admin/' ) ? ' (admin)' : '' );
	}

	public static function event( WP_REST_Request $r ) {
		$type  = (string) $r->get_param( 'type' );
		$url   = self::local_url( $r->get_param( 'url' ) );
		$title = self::clip( $r->get_param( 'title' ), 120 );
		$label = self::clip( $r->get_param( 'label' ), 80 );
		if ( ! $url ) {
			return new WP_REST_Response( array( 'ok' => false ), 200 );
		}
		$place = self::place( $title, $url );
		$now   = array( 'url' => $url, 'title' => $place, 'time' => time(), 'type' => $type, 'label' => $label );

		// Throttle clicks: at most one log line per 400 ms.
		if ( 'click' === $type || 'submit' === $type ) {
			$k = 'fbcc_br_click';
			if ( get_transient( $k ) && (float) get_transient( $k ) > microtime( true ) - 0.4 ) {
				update_option( self::NOW, $now, false );
				return new WP_REST_Response( array( 'ok' => true, 'throttled' => true ), 200 );
			}
			set_transient( $k, (string) microtime( true ), 60 );
		}

		switch ( $type ) {
			case 'page':
				$summary = 'Opened ' . $place;
				break;
			case 'click':
				$summary = 'Clicked “' . ( '' !== $label ? $label : 'an item' ) . '” on ' . $place;
				break;
			case 'submit':
				$summary = 'Saved a form' . ( '' !== $label ? ' (“' . $label . '”)' : '' ) . ' on ' . $place;
				break;
			default:
				return new WP_REST_Response( array( 'ok' => false ), 200 );
		}
		update_option( self::NOW, $now, false );
		FBCC_Store::log( 'browser', $summary, 'browser' );
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * The linked browser sends a sanitised copy of the page it shows. Only the latest copy is kept.
	 * It is shown to administrators only, inside a sandboxed frame with scripts off.
	 */
	public static function screen_put( WP_REST_Request $r ) {
		$html = (string) $r->get_param( 'html' );
		if ( '' === $html || strlen( $html ) > self::MAX_SNAP ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'Empty or too large.' ), 200 );
		}
		$url = self::local_url( $r->get_param( 'url' ) );
		if ( ! $url ) {
			return new WP_REST_Response( array( 'ok' => false ), 200 );
		}
		// Belt and braces: the agent already removes these; never keep them server-side either.
		$html = preg_replace( '#<script\b[^>]*>.*?</script>#is', '', $html );
		$html = preg_replace( '#<script\b[^>]*/?>#i', '', $html );
		$html = preg_replace( '#\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html );
		$html = preg_replace( '#(<input\b[^>]*type=["\']?password["\']?[^>]*?)\svalue=("[^"]*"|\'[^\']*\')#i', '$1', $html );
		$html = preg_replace( '#(name=["\']?[^"\'>]*nonce[^"\'>]*["\']?[^>]*?)\svalue=("[^"]*"|\'[^\']*\')#i', '$1', $html );

		$prev = get_option( self::SCREEN );
		$seq  = ( is_array( $prev ) ? (int) $prev['seq'] : 0 ) + 1;
		update_option( self::SCREEN, array(
			'seq'    => $seq,
			'time'   => time(),
			'url'    => $url,
			'title'  => self::place( $r->get_param( 'title' ), $url ),
			'w'      => max( 320, min( 3000, (int) $r->get_param( 'w' ) ) ),
			'h'      => max( 240, min( 3000, (int) $r->get_param( 'h' ) ) ),
			'sx'     => max( 0, (int) $r->get_param( 'sx' ) ),
			'sy'     => max( 0, (int) $r->get_param( 'sy' ) ),
			'html'   => $html,
		), false );
		return new WP_REST_Response( array( 'ok' => true, 'seq' => $seq ), 200 );
	}

	public static function screen_get( WP_REST_Request $r ) {
		$s     = get_option( self::SCREEN );
		$since = (int) $r->get_param( 'since' );
		if ( ! is_array( $s ) || ! self::link() ) {
			return new WP_REST_Response( array( 'seq' => 0 ), 200 );
		}
		if ( $since && $since >= (int) $s['seq'] ) {
			return new WP_REST_Response( array( 'seq' => (int) $s['seq'], 'same' => true ), 200 );
		}
		return new WP_REST_Response( $s, 200 );
	}

	/** Small summary for Watch Me Live's poll. */
	public static function live_info() {
		$l = self::link();
		$n = self::now();
		$s = get_option( self::SCREEN );
		return array(
			'linked' => (bool) $l,
			'url'    => $l && $n ? $n['url'] : '',
			'title'  => $l && $n ? $n['title'] : '',
			'time'   => $l && $n ? (int) $n['time'] : 0,
			'screen' => $l && is_array( $s ) ? (int) $s['seq'] : 0,
			'lang'   => self::voice_lang(),
		);
	}
}
