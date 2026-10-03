<?php
/**
 * Approved build plans.
 *
 * The owner approves a whole website plan once (the mockups they already agreed with Claude).
 * While that plan is active, everything INSIDE it runs without asking again:
 *   publishing the plan's pages, editing them, the plan's menu and site settings.
 * Anything outside the plan still goes to the normal approval queue.
 *
 * Go-live per plan:  auto   = pages go live as they are finished
 *                    button = finished work waits for ONE "Launch" click.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FBCC_Plan {

	const OPT    = 'fbcc_plans';
	const ACTIVE = 'fbcc_active_plan';
	const DAYS   = 14;

	public static function init() {
		add_filter( 'fbcc_tools', array( __CLASS__, 'tools' ), 4 );
		add_action( 'admin_post_fbcc_launch', array( __CLASS__, 'action_launch' ) );
		add_action( 'admin_post_fbcc_plan_end', array( __CLASS__, 'action_end' ) );
		add_action( 'rest_api_init', function () {
			register_rest_route( FBCC_NS, '/claude/launch', array(
				'methods'             => 'POST',
				'callback'            => function () {
					$r = FBCC_Plan::launch();
					return new WP_REST_Response( is_wp_error( $r ) ? array( 'ok' => false, 'message' => $r->get_error_message() ) : array( 'ok' => true, 'message' => $r ) );
				},
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			) );
		} );
	}

	/* ------------------------------------------------------------ */
	/* Storage                                                      */
	/* ------------------------------------------------------------ */

	public static function all() {
		$p = get_option( self::OPT, array() );
		return is_array( $p ) ? $p : array();
	}

	public static function save( array $plan ) {
		$all                = self::all();
		$all[ $plan['id'] ] = $plan;
		if ( count( $all ) > 20 ) {
			$all = array_slice( $all, -20, null, true );
		}
		update_option( self::OPT, $all, false );
	}

	/** The active plan, or null. Expired plans end themselves. */
	public static function active() {
		$id  = (int) get_option( self::ACTIVE );
		$all = self::all();
		if ( ! $id || empty( $all[ $id ] ) ) {
			return null;
		}
		$p = $all[ $id ];
		if ( ! in_array( $p['status'], array( 'active', 'launched' ), true ) ) {
			return null;
		}
		if ( time() > (int) $p['expires'] ) {
			$p['status'] = 'ended';
			self::save( $p );
			delete_option( self::ACTIVE );
			FBCC_Store::log( 'build_plan', 'Plan #' . $p['id'] . ' “' . $p['title'] . '” expired', 'info' );
			return null;
		}
		return $p;
	}

	private static function norm( $t ) {
		$t = html_entity_decode( wp_strip_all_tags( (string) $t ), ENT_QUOTES, 'UTF-8' );
		return trim( function_exists( 'mb_strtolower' ) ? mb_strtolower( $t ) : strtolower( $t ) );
	}

	/* ------------------------------------------------------------ */
	/* Tools                                                        */
	/* ------------------------------------------------------------ */

	public static function tools( $t ) {
		$t['build_plan_submit'] = array(
			'title'       => 'Submit a build plan',
			'description' => 'After the owner has approved the website mockups in chat, send the whole plan here. The owner confirms it ONCE on the site; from then on, publishing and editing the plan\'s pages, its menu and its site settings run without further approvals. launch = "auto" (pages go live as they are finished) or "button" (everything waits for one Launch click). Anything outside the plan still needs approval.',
			'level'       => 'editor',
			'kind'        => 'approval',
			'schema'      => array(
				'type'                 => 'object',
				'properties'           => (object) array(
					'title'    => array( 'type' => 'string', 'description' => 'e.g. "Vites Oto Servis website"' ),
					'summary'  => array( 'type' => 'string', 'description' => 'What was approved in chat (mockups, style)' ),
					'pages'    => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => (object) array(
								'title'  => array( 'type' => 'string' ),
								'type'   => array( 'type' => 'string', 'enum' => array( 'page', 'post' ), 'default' => 'page', 'description' => 'post = a blog post' ),
								'parent' => array( 'type' => 'string', 'description' => 'Title of the parent page in this plan' ),
								'notes'  => array( 'type' => 'string' ),
							),
						),
					),
					'menu'     => array( 'type' => 'boolean', 'description' => 'The plan includes the main menu' ),
					'settings' => array( 'type' => 'boolean', 'description' => 'The plan includes homepage / site title / tagline' ),
					'theme'    => array( 'type' => 'boolean', 'description' => 'The plan includes the theme look: header, footer, colours, fonts (theme_settings_set, widgets_set)' ),
					'plugins'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'WordPress.org plugin slugs the plan needs, e.g. contact-form-7. On a fresh site list wpvivid-backuprestore first (for the backup).' ),
					'themes'   => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'WordPress.org theme slugs to install and activate with theme_install, e.g. kadence' ),
					'trash'    => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ), 'description' => 'Ids of existing pages/posts to move to the Trash with content_trash, e.g. WordPress\'s "Hello world!" post and "Sample Page"' ),
					'launch'   => array( 'type' => 'string', 'enum' => array( 'auto', 'button' ), 'default' => 'button' ),
					'mockups'  => array( 'type' => 'string', 'description' => 'Link to the approved mockups' ),
					'mindmap'  => array( 'type' => 'integer', 'description' => 'FB Mind Map id of the plan, if any' ),
					'reason'   => array( 'type' => 'string' ),
				),
				'required'             => array( 'title', 'pages', 'reason' ),
				'additionalProperties' => false,
			),
			'prepare'     => array( __CLASS__, 'prepare' ),
			'execute'     => array( __CLASS__, 'execute' ),
		);
		$t['build_plan_status'] = array(
			'title'       => 'Build plan status',
			'description' => 'The active build plan: its pages and whether each is built and live, what is waiting for Launch, and the go-live mode.',
			'level'       => 'read',
			'kind'        => 'read',
			'schema'      => array( 'type' => 'object', 'properties' => (object) array(), 'additionalProperties' => false ),
			'run'         => array( __CLASS__, 'status_tool' ),
		);
		return $t;
	}

	public static function prepare( $a ) {
		$pages = array();
		foreach ( array_slice( (array) $a['pages'], 0, 60 ) as $pg ) {
			$title = sanitize_text_field( (string) ( $pg['title'] ?? '' ) );
			if ( '' === $title ) {
				continue;
			}
			$pages[] = array(
				'title'  => $title,
				'type'   => ( $pg['type'] ?? 'page' ) === 'post' ? 'post' : 'page',
				'parent' => sanitize_text_field( (string) ( $pg['parent'] ?? '' ) ),
				'notes'  => sanitize_textarea_field( (string) ( $pg['notes'] ?? '' ) ),
			);
		}
		if ( ! $pages ) {
			return new WP_Error( 'no_pages', 'A plan needs at least one page.' );
		}
		$launch = ( $a['launch'] ?? 'button' ) === 'auto' ? 'auto' : 'button';
		$extra  = array();
		if ( ! empty( $a['menu'] ) ) {
			$extra[] = 'menu';
		}
		if ( ! empty( $a['settings'] ) ) {
			$extra[] = 'homepage & site title';
		}
		if ( ! empty( $a['theme'] ) ) {
			$extra[] = 'header, footer, colours & fonts';
		}
		$plugins = array_values( array_filter( array_map( 'sanitize_key', (array) ( $a['plugins'] ?? array() ) ) ) );
		if ( $plugins ) {
			$extra[] = 'plugins: ' . implode( ', ', $plugins );
		}
		$themes = array_values( array_filter( array_map( 'sanitize_key', (array) ( $a['themes'] ?? array() ) ) ) );
		if ( $themes ) {
			$extra[] = 'theme: ' . implode( ', ', $themes );
		}
		$trash = array();
		foreach ( array_slice( (array) ( $a['trash'] ?? array() ), 0, 20 ) as $tid ) {
			$tp = get_post( (int) $tid );
			if ( $tp && in_array( $tp->post_type, array( 'page', 'post' ), true ) && 'trash' !== $tp->post_status ) {
				$trash[] = (int) $tid;
			}
		}
		if ( $trash ) {
			$extra[] = 'trash ' . count( $trash ) . ' default item' . ( 1 === count( $trash ) ? '' : 's' );
		}
		$np = count( array_filter( $pages, function ( $x ) { return 'page' === $x['type']; } ) );
		$nb = count( $pages ) - $np;
		$title = sanitize_text_field( (string) $a['title'] );
		return array(
			'title'   => 'Build plan “' . $title . '”: ' . $np . ' pages' . ( $nb ? ' + ' . $nb . ' blog posts' : '' ) . ( $extra ? ' + ' . implode( ' + ', $extra ) : '' ) . ' · go live ' . ( 'auto' === $launch ? 'automatically' : 'with one Launch click' ),
			'payload' => array(
				'title'    => $title,
				'summary'  => sanitize_textarea_field( (string) ( $a['summary'] ?? '' ) ),
				'pages'    => $pages,
				'menu'     => ! empty( $a['menu'] ),
				'settings' => ! empty( $a['settings'] ),
				'theme'    => ! empty( $a['theme'] ),
				'plugins'  => $plugins,
				'themes'   => $themes,
				'trash'    => $trash,
				'launch'   => $launch,
				'mockups'  => esc_url_raw( (string) ( $a['mockups'] ?? '' ) ),
				'mindmap'  => (int) ( $a['mindmap'] ?? 0 ),
			),
		);
	}

	/** Runs when the owner approves the plan. */
	public static function execute( $p ) {
		$old = self::active();
		if ( $old ) {
			$old['status'] = 'ended';
			self::save( $old );
		}
		$id   = (int) get_option( 'fbcc_plan_seq', 0 ) + 1;
		update_option( 'fbcc_plan_seq', $id, false );
		$plan = array_merge( $p, array(
			'id'        => $id,
			'status'    => 'active',
			'approver'  => get_current_user_id(),
			'confirmed' => time(),
			'expires'   => time() + self::DAYS * DAY_IN_SECONDS,
			'built'     => array(),   // norm(title) => post id
			'queue'     => array(),   // waiting for Launch
		) );
		// Pages that already exist with a planned title count as part of the plan.
		foreach ( $plan['pages'] as $pg ) {
			$found = get_posts( array( 'post_type' => $pg['type'] ?? 'page', 'title' => $pg['title'], 'post_status' => array( 'draft', 'pending', 'publish' ), 'numberposts' => 1, 'fields' => 'ids' ) );
			if ( $found ) {
				$plan['built'][ self::norm( $pg['title'] ) ] = (int) $found[0];
			}
		}
		self::save( $plan );
		update_option( self::ACTIVE, $id, false );
		return array(
			'_summary' => 'Build plan #' . $id . ' “' . $plan['title'] . '” is active — Claude can build it without asking again',
			'_undo'    => admin_url( 'admin.php?page=fbcc' ),
		);
	}

	public static function status_tool() {
		$p = self::active();
		if ( ! $p ) {
			return array( 'active' => false, 'message' => 'No build plan is active. Submit one with build_plan_submit after the owner approves the mockups.' );
		}
		$pages = array();
		foreach ( $p['pages'] as $pg ) {
			$id      = $p['built'][ self::norm( $pg['title'] ) ] ?? 0;
			$pages[] = array(
				'title'  => $pg['title'],
				'type'   => $pg['type'] ?? 'page',
				'parent' => $pg['parent'],
				'id'     => $id ? (int) $id : null,
				'status' => $id ? get_post_status( $id ) : 'not built',
			);
		}
		return array(
			'active'      => true,
			'id'          => $p['id'],
			'title'       => $p['title'],
			'status'      => $p['status'],
			'launch'      => $p['launch'],
			'pages'       => $pages,
			'menu'        => $p['menu'],
			'settings'    => $p['settings'],
			'theme'       => ! empty( $p['theme'] ),
			'plugins'     => (array) ( $p['plugins'] ?? array() ),
			'themes'      => (array) ( $p['themes'] ?? array() ),
			'trash'       => (array) ( $p['trash'] ?? array() ),
			'waiting_for_launch' => count( $p['queue'] ),
			'expires'     => gmdate( 'Y-m-d', (int) $p['expires'] ),
		);
	}

	/** A draft was created: if its title is in the plan, remember it. */
	public static function note_built( $title, $id ) {
		$p = self::active();
		if ( ! $p ) {
			return;
		}
		$n = self::norm( $title );
		foreach ( $p['pages'] as $pg ) {
			if ( self::norm( $pg['title'] ) === $n ) {
				$p['built'][ $n ] = (int) $id;
				self::save( $p );
				return;
			}
		}
	}

	private static function has_post( array &$p, $id ) {
		$id = (int) $id;
		if ( in_array( $id, array_map( 'intval', $p['built'] ), true ) ) {
			return true;
		}
		$post = get_post( $id );
		if ( ! $post || ! in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
			return false;
		}
		$n = self::norm( $post->post_title );
		foreach ( $p['pages'] as $pg ) {
			if ( self::norm( $pg['title'] ) === $n ) {
				$p['built'][ $n ] = $id;
				self::save( $p );
				return true;
			}
		}
		return false;
	}

	public static function covers( $tool, array $payload, array &$p ) {
		switch ( $tool ) {
			case 'content_publish':
			case 'content_update':
			case 'post_settings_set':
				return self::has_post( $p, $payload['id'] ?? 0 );
			case 'menu_set':
				return ! empty( $p['menu'] );
			case 'site_settings':
				return ! empty( $p['settings'] );
			case 'theme_settings_set':
			case 'widgets_set':
			case 'custom_css_set':
				return ! empty( $p['theme'] );
			case 'plugins_install':
				return in_array( (string) ( $payload['slug'] ?? '' ), (array) ( $p['plugins'] ?? array() ), true );
			case 'theme_install':
				return in_array( (string) ( $payload['slug'] ?? '' ), (array) ( $p['themes'] ?? array() ), true );
			case 'content_trash':
				return in_array( (int) ( $payload['id'] ?? 0 ), array_map( 'intval', (array) ( $p['trash'] ?? array() ) ), true );
		}
		return false;
	}

	/**
	 * Called instead of queueing an approval. Returns null when the plan does not cover it
	 * (→ normal approval), or a result array when the plan handled it.
	 */
	/** The owner's approved plan lists this install, so the connector's own lab lock lets it through. */
	public static function allows_site( $tool, array $args ) {
		if ( ! in_array( $tool, array( 'plugins_install', 'theme_install' ), true ) ) {
			return false;
		}
		$p = self::active();
		if ( ! $p ) {
			return false;
		}
		$slug = sanitize_key( (string) ( $args['slug'] ?? '' ) );
		return self::covers( $tool, array( 'slug' => $slug ), $p );
	}

	public static function intercept( $tool, $title, array $payload ) {
		$p = self::active();
		if ( ! $p || ! self::covers( $tool, $payload, $p ) ) {
			return null;
		}
		$deferred = in_array( $tool, array( 'content_publish', 'menu_set', 'site_settings', 'theme_settings_set', 'widgets_set', 'custom_css_set' ), true );
		if ( 'button' === $p['launch'] && 'active' === $p['status'] && $deferred ) {
			$p['queue'][] = array( 'tool' => $tool, 'title' => $title, 'payload' => $payload );
			self::save( $p );
			return array(
				'_ready'   => true,
				'_summary' => 'Plan #' . $p['id'] . ': ready to launch — ' . $title,
				'ready_to_launch' => true,
				'message'  => 'Covered by the approved plan. It goes live when the owner clicks Launch (' . count( $p['queue'] ) . ' item(s) ready).',
			);
		}
		$res = self::run_as_approver( $p, $tool, $payload );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$res['_summary']    = 'Plan #' . $p['id'] . ': ' . ( $res['_summary'] ?? $title );
		$res['done_by_plan'] = true;
		return $res;
	}

	private static function run_as_approver( array $p, $tool, array $payload ) {
		$approver = get_userdata( (int) $p['approver'] );
		if ( ! $approver || ! user_can( $approver, 'manage_options' ) ) {
			return new WP_Error( 'plan_owner', 'The administrator who approved this plan no longer has access. Approve the plan again.' );
		}
		$prev = get_current_user_id();
		wp_set_current_user( $approver->ID );
		try {
			$res = FBCC_Gateway::execute_payload( $tool, $payload );
		} catch ( \Throwable $e ) {
			$res = new WP_Error( 'tool_error', $e->getMessage() );
		}
		wp_set_current_user( $prev );
		return $res;
	}

	/* ------------------------------------------------------------ */
	/* Launch                                                       */
	/* ------------------------------------------------------------ */

	public static function launch() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'forbidden', 'Administrators only.' );
		}
		$p = self::active();
		if ( ! $p || ! $p['queue'] ) {
			return new WP_Error( 'nothing', 'Nothing is waiting for launch.' );
		}
		$lock = FBCC_Store::emergency_lock();
		if ( $lock ) {
			return new WP_Error( 'locked', 'AI Engine emergency write lock is ON.' );
		}
		$order = array( 'post_settings_set' => 0, 'content_publish' => 1, 'theme_settings_set' => 2, 'custom_css_set' => 3, 'widgets_set' => 4, 'menu_set' => 5, 'site_settings' => 6 );
		$queue = $p['queue'];
		usort( $queue, function ( $a, $b ) use ( $order ) {
			return ( $order[ $a['tool'] ] ?? 9 ) <=> ( $order[ $b['tool'] ] ?? 9 );
		} );
		$ok   = 0;
		$fail = array();
		foreach ( $queue as $item ) {
			try {
				$res = FBCC_Gateway::execute_payload( $item['tool'], $item['payload'] );
			} catch ( \Throwable $e ) {
				$res = new WP_Error( 'tool_error', $e->getMessage() );
			}
			if ( is_wp_error( $res ) ) {
				$fail[] = $item['title'] . ': ' . $res->get_error_message();
				FBCC_Store::log( $item['tool'], 'Launch failed — ' . $item['title'] . ': ' . $res->get_error_message(), 'error' );
			} else {
				$ok++;
				FBCC_Store::log( $item['tool'], 'Launched by ' . wp_get_current_user()->display_name . ': ' . ( $res['_summary'] ?? $item['title'] ), 'done', $res['_undo'] ?? '' );
			}
		}
		$p['queue']  = array();
		$p['status'] = 'launched';
		self::save( $p );
		return 'Launched ' . $ok . ' item(s)' . ( $fail ? ' — ' . count( $fail ) . ' failed: ' . implode( '; ', $fail ) : '' ) . '.';
	}

	public static function action_launch() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		check_admin_referer( 'fbcc_launch' );
		$r = self::launch();
		wp_safe_redirect( admin_url( 'admin.php?page=fbcc&fbcc_t=' . ( is_wp_error( $r ) ? 'error' : 'success' ) . '&fbcc_msg=' . rawurlencode( is_wp_error( $r ) ? $r->get_error_message() : $r ) ) );
		exit;
	}

	public static function action_end() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		check_admin_referer( 'fbcc_plan_end' );
		$p = self::active();
		if ( $p ) {
			$p['status'] = 'ended';
			self::save( $p );
			FBCC_Store::log( 'build_plan', 'Plan #' . $p['id'] . ' ended by ' . wp_get_current_user()->display_name, 'info' );
		}
		delete_option( self::ACTIVE );
		wp_safe_redirect( admin_url( 'admin.php?page=fbcc&fbcc_msg=' . rawurlencode( 'Build plan ended. Claude asks for approval again.' ) ) );
		exit;
	}

	/** Overview card. */
	public static function card() {
		$p = self::active();
		if ( ! $p ) {
			return;
		}
		echo '<section class="fbcc-card fbcc-plan"><div class="fbcc-head"><h2>Build plan #' . (int) $p['id'] . ': ' . esc_html( $p['title'] ) . '</h2>';
		echo '<span class="fbcc-pill fbcc-green">' . ( 'launched' === $p['status'] ? 'Launched' : 'Active' ) . '</span>';
		echo '<span class="fbcc-muted">Go live: ' . ( 'auto' === $p['launch'] ? 'automatically' : 'with one Launch click' ) . ' · until ' . esc_html( wp_date( 'j M', (int) $p['expires'] ) ) . '</span></div>';
		if ( $p['summary'] ) {
			echo '<p class="fbcc-muted">' . esc_html( $p['summary'] ) . '</p>';
		}
		echo '<ul class="fbcc-plan-list">';
		foreach ( $p['pages'] as $pg ) {
			$id = $p['built'][ self::norm( $pg['title'] ) ] ?? 0;
			$st = $id ? get_post_status( $id ) : '';
			$cl = 'publish' === $st ? 'live' : ( $st ? 'built' : '' );
			$lb = 'publish' === $st ? 'live' : ( $st ? 'built · ' . $st : 'not built yet' );
			echo '<li class="' . esc_attr( $cl ) . '"><span>' . esc_html( ( 'post' === ( $pg['type'] ?? 'page' ) ? 'Blog: ' : '' ) . ( $pg['parent'] ? $pg['parent'] . ' › ' : '' ) . $pg['title'] ) . '</span><em>' . esc_html( $lb ) . '</em></li>';
		}
		if ( $p['menu'] ) {
			echo '<li><span>Main menu</span><em>in plan</em></li>';
		}
		if ( $p['settings'] ) {
			echo '<li><span>Homepage &amp; site title</span><em>in plan</em></li>';
		}
		if ( ! empty( $p['theme'] ) ) {
			echo '<li><span>Header, footer, colours &amp; fonts</span><em>in plan</em></li>';
		}
		foreach ( (array) ( $p['themes'] ?? array() ) as $th ) {
			echo '<li class="' . ( get_stylesheet() === $th ? 'live' : '' ) . '"><span>Theme: ' . esc_html( $th ) . '</span><em>' . ( get_stylesheet() === $th ? 'active' : ( wp_get_theme( $th )->exists() ? 'installed' : 'in plan' ) ) . '</em></li>';
		}
		foreach ( (array) ( $p['plugins'] ?? array() ) as $pl ) {
			echo '<li><span>Plugin: ' . esc_html( $pl ) . '</span><em>' . ( is_dir( WP_PLUGIN_DIR . '/' . $pl ) ? 'installed' : 'in plan' ) . '</em></li>';
		}
		foreach ( (array) ( $p['trash'] ?? array() ) as $tid ) {
			$tp = get_post( (int) $tid );
			echo '<li class="' . ( $tp && 'trash' === $tp->post_status ? 'live' : '' ) . '"><span>Move to Trash: ' . esc_html( $tp ? $tp->post_title : '#' . (int) $tid ) . '</span><em>' . ( $tp && 'trash' === $tp->post_status ? 'in Trash' : 'in plan' ) . '</em></li>';
		}
		echo '</ul><div class="fbcc-row">';
		if ( $p['queue'] ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline"><input type="hidden" name="action" value="fbcc_launch">';
			wp_nonce_field( 'fbcc_launch' );
			echo '<button class="button button-primary button-hero">🚀 Launch ' . count( $p['queue'] ) . ' item(s)</button></form>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline"><input type="hidden" name="action" value="fbcc_plan_end">';
		wp_nonce_field( 'fbcc_plan_end' );
		echo '<button class="button">End plan</button></form>';
		if ( $p['mockups'] ) {
			echo '<a class="button" href="' . esc_url( $p['mockups'] ) . '" target="_blank" rel="noopener">Approved mockups ↗</a>';
		}
		echo '</div></section>';
	}

	/** Small summary for Watch Me Live. */
	public static function live_info() {
		$p = self::active();
		if ( ! $p ) {
			return null;
		}
		$built = 0;
		$live  = 0;
		foreach ( $p['pages'] as $pg ) {
			$id = $p['built'][ self::norm( $pg['title'] ) ] ?? 0;
			if ( $id ) {
				$built++;
				if ( 'publish' === get_post_status( $id ) ) {
					$live++;
				}
			}
		}
		return array(
			'id'     => $p['id'],
			'title'  => $p['title'],
			'launch' => $p['launch'],
			'status' => $p['status'],
			'pages'  => count( $p['pages'] ),
			'built'  => $built,
			'live'   => $live,
			'ready'  => count( $p['queue'] ),
		);
	}
}
