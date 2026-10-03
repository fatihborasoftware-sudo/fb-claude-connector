<?php
/**
 * Tool registry. Every tool declares:
 *   level  read | editor | maintainer  — minimum permission level
 *   kind   read | status | write | approval
 *   site   true when it changes the site itself (blocked by the lab lock)
 * Handlers never check locks themselves: FBCC_Gateway is the only door.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FBCC_Tools {

	private static $tools = null;

	public static function all() {
		if ( null !== self::$tools ) {
			return self::$tools;
		}
		$t = array();

		$t['site_health'] = array(
			'title'       => 'Site health',
			'description' => 'Overview of this WordPress site: versions, active theme, pending updates, security flags, the connector permission level and locks.',
			'level'       => 'read',
			'kind'        => 'read',
			'schema'      => self::schema( array() ),
			'run'         => array( __CLASS__, 'site_health' ),
		);

		$t['content_list'] = array(
			'title'       => 'List pages and posts',
			'description' => 'List pages or posts with id, title, status, last modified and link. Supports search and paging.',
			'level'       => 'read',
			'kind'        => 'read',
			'schema'      => self::schema( array(
				'post_type' => array( 'type' => 'string', 'enum' => array( 'page', 'post' ), 'default' => 'page' ),
				'status'    => array( 'type' => 'string', 'enum' => array( 'any', 'publish', 'draft', 'pending', 'private' ), 'default' => 'any' ),
				'search'    => array( 'type' => 'string' ),
				'per_page'  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20 ),
				'page'      => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1 ),
			) ),
			'run'         => array( __CLASS__, 'content_list' ),
		);

		$t['content_get'] = array(
			'title'       => 'Read a page or post',
			'description' => 'Full raw content (block markup) of one page or post, with title, status, excerpt and link.',
			'level'       => 'read',
			'kind'        => 'read',
			'schema'      => self::schema( array( 'id' => array( 'type' => 'integer' ) ), array( 'id' ) ),
			'run'         => array( __CLASS__, 'content_get' ),
		);

		$t['plugins_list'] = array(
			'title'       => 'List plugins',
			'description' => 'Installed plugins with version, active state and any available update.',
			'level'       => 'read',
			'kind'        => 'read',
			'schema'      => self::schema( array() ),
			'run'         => array( __CLASS__, 'plugins_list' ),
		);

		$t['activity_recent'] = array(
			'title'       => 'Recent activity',
			'description' => 'The connector activity log (newest first): every tool call with its result.',
			'level'       => 'read',
			'kind'        => 'read',
			'schema'      => self::schema( array( 'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ) ) ),
			'run'         => array( __CLASS__, 'activity_recent' ),
		);

		$t['approvals_list'] = array(
			'title'       => 'Approval queue',
			'description' => 'Requests waiting for the site owner, or recently approved / rejected ones. Use it to check whether a queued change was approved.',
			'level'       => 'read',
			'kind'        => 'read',
			'schema'      => self::schema( array( 'status' => array( 'type' => 'string', 'enum' => array( 'pending', 'approved', 'rejected', 'failed' ), 'default' => 'pending' ) ) ),
			'run'         => array( __CLASS__, 'approvals_list' ),
		);

		$t['task_status'] = array(
			'title'       => 'Report task progress',
			'description' => 'Tell the site owner what you are doing. Shown live in the WordPress admin bar and the AI Engine widget. Call it at the start of a task, at each step, and with done=true at the end.',
			'level'       => 'read',
			'kind'        => 'status',
			'schema'      => self::schema( array(
				'title' => array( 'type' => 'string', 'description' => 'Short task name, e.g. "Update the Hizmetler page"' ),
				'step'  => array( 'type' => 'integer', 'minimum' => 0 ),
				'total' => array( 'type' => 'integer', 'minimum' => 1 ),
				'note'  => array( 'type' => 'string', 'description' => 'What is happening right now' ),
				'steps' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Optional full plan, one line per step' ),
				'brief' => array( 'type' => 'string', 'description' => 'Optional spoken brief (1–3 short sentences) read aloud on Watch Me Live when the owner has the voice on. Send one at the start of a task (what you will do), at important steps, and with done=true (what was done and what is left for the owner). Write it in the brief language named in the server instructions.' ),
				'done'  => array( 'type' => 'boolean', 'default' => false ),
			), array( 'title' ) ),
			'run'         => array( __CLASS__, 'task_status' ),
		);

		$t['content_create_draft'] = array(
			'title'       => 'Create a draft',
			'description' => 'Create a new page or post as a DRAFT. It is not published.',
			'level'       => 'editor',
			'kind'        => 'write',
			'schema'      => self::schema( array(
				'post_type' => array( 'type' => 'string', 'enum' => array( 'page', 'post' ), 'default' => 'page' ),
				'title'     => array( 'type' => 'string' ),
				'content'   => array( 'type' => 'string', 'description' => 'Block markup or HTML' ),
				'excerpt'   => array( 'type' => 'string' ),
				'parent'    => array( 'type' => 'integer', 'description' => 'Parent page id' ),
			), array( 'title' ) ),
			'run'         => array( __CLASS__, 'content_create_draft' ),
		);

		$t['content_update'] = array(
			'title'       => 'Edit a page or post',
			'description' => 'Change title, content or excerpt. Drafts are updated at once. A PUBLISHED item is not changed: the edit is sent to the owner for approval. Always give a reason.',
			'level'       => 'editor',
			'kind'        => 'write',
			'schema'      => self::schema( array(
				'id'      => array( 'type' => 'integer' ),
				'title'   => array( 'type' => 'string' ),
				'content' => array( 'type' => 'string' ),
				'excerpt' => array( 'type' => 'string' ),
				'reason'  => array( 'type' => 'string', 'description' => 'Why, in one sentence — shown to the owner' ),
			), array( 'id', 'reason' ) ),
			'run'         => array( __CLASS__, 'content_update' ),
		);

		$t['content_publish'] = array(
			'title'       => 'Publish',
			'description' => 'Ask the owner to publish a draft. Always goes to the approval queue.',
			'level'       => 'editor',
			'kind'        => 'approval',
			'schema'      => self::schema( array(
				'id'     => array( 'type' => 'integer' ),
				'reason' => array( 'type' => 'string' ),
			), array( 'id', 'reason' ) ),
			'prepare'     => array( __CLASS__, 'publish_prepare' ),
			'execute'     => array( __CLASS__, 'publish_execute' ),
		);

		$t['plugins_update'] = array(
			'title'       => 'Update plugins',
			'description' => 'Ask the owner to update plugins. Always goes to the approval queue and is blocked while the lab lock is on.',
			'level'       => 'maintainer',
			'kind'        => 'approval',
			'site'        => true,
			'schema'      => self::schema( array(
				'plugins' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Plugin files from plugins_list (e.g. "contact-form-7/wp-contact-form-7.php"); empty = all with updates' ),
				'reason'  => array( 'type' => 'string' ),
			), array( 'reason' ) ),
			'prepare'     => array( __CLASS__, 'plugins_update_prepare' ),
			'execute'     => array( __CLASS__, 'plugins_update_execute' ),
		);

		self::$tools = apply_filters( 'fbcc_tools', $t );
		return self::$tools;
	}

	public static function get( $name ) {
		$all = self::all();
		return isset( $all[ $name ] ) ? $all[ $name ] : null;
	}

	private static function schema( array $props, array $required = array() ) {
		$s = array(
			'type'                 => 'object',
			'properties'           => (object) $props,
			'additionalProperties' => false,
		);
		if ( $required ) {
			$s['required'] = $required;
		}
		return $s;
	}

	/* ------------------------------------------------------------ */
	/* Helpers                                                      */
	/* ------------------------------------------------------------ */

	private static function post_or_error( $id ) {
		$post = get_post( (int) $id );
		if ( ! $post || ! in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
			return new WP_Error( 'not_found', 'No page or post with id ' . (int) $id . '.' );
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error( 'forbidden', 'claude-agent may not access this item.' );
		}
		return $post;
	}

	private static function brief( WP_Post $p ) {
		return array(
			'id'       => $p->ID,
			'type'     => $p->post_type,
			'title'    => get_the_title( $p ),
			'status'   => $p->post_status,
			'modified' => $p->post_modified_gmt . ' UTC',
			'link'     => get_permalink( $p ),
		);
	}

	/* ------------------------------------------------------------ */
	/* Read tools                                                   */
	/* ------------------------------------------------------------ */

	public static function site_health() {
		global $wp_version;
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$core    = get_site_transient( 'update_core' );
		$plugins = get_site_transient( 'update_plugins' );
		$themes  = get_site_transient( 'update_themes' );
		$core_up = '';
		if ( $core && ! empty( $core->updates ) ) {
			foreach ( $core->updates as $u ) {
				if ( 'upgrade' === $u->response ) {
					$core_up = $u->current;
					break;
				}
			}
		}
		$s     = FBCC_Store::settings();
		$theme = wp_get_theme();
		return array(
			'site'               => get_bloginfo( 'name' ),
			'url'                => home_url(),
			'wordpress'          => $wp_version,
			'wordpress_update'   => $core_up ? $core_up : null,
			'php'                => PHP_VERSION,
			'theme'              => $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ),
			'active_plugins'     => count( (array) get_option( 'active_plugins', array() ) ),
			'plugin_updates'     => $plugins && ! empty( $plugins->response ) ? count( $plugins->response ) : 0,
			'theme_updates'      => $themes && ! empty( $themes->response ) ? count( $themes->response ) : 0,
			'https'              => 0 === strpos( home_url(), 'https://' ),
			'debug_mode'         => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'file_editor_off'    => defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT,
			'search_engines'     => (bool) get_option( 'blog_public' ),
			'connector_level'    => $s['level'],
			'lab_lock'           => FBCC_Store::lab_lock(),
			'emergency_lock'     => FBCC_Store::emergency_lock(),
			'pending_approvals'  => FBCC_Store::pending_count(),
		);
	}

	public static function content_list( $a ) {
		$type   = in_array( $a['post_type'] ?? 'page', array( 'page', 'post' ), true ) ? ( $a['post_type'] ?? 'page' ) : 'page';
		$status = in_array( $a['status'] ?? 'any', array( 'publish', 'draft', 'pending', 'private' ), true ) ? $a['status'] : array( 'publish', 'draft', 'pending', 'private', 'future' );
		$q      = new WP_Query( array(
			'post_type'      => $type,
			'post_status'    => $status,
			's'              => sanitize_text_field( (string) ( $a['search'] ?? '' ) ),
			'posts_per_page' => min( 50, max( 1, (int) ( $a['per_page'] ?? 20 ) ) ),
			'paged'          => max( 1, (int) ( $a['page'] ?? 1 ) ),
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'no_found_rows'  => false,
		) );
		return array(
			'total' => (int) $q->found_posts,
			'pages' => (int) $q->max_num_pages,
			'items' => array_map( array( __CLASS__, 'brief' ), $q->posts ),
		);
	}

	public static function content_get( $a ) {
		$p = self::post_or_error( $a['id'] ?? 0 );
		if ( is_wp_error( $p ) ) {
			return $p;
		}
		return array_merge( self::brief( $p ), array(
			'excerpt'   => $p->post_excerpt,
			'parent'    => $p->post_parent,
			'revisions' => count( wp_get_post_revisions( $p->ID, array( 'fields' => 'ids' ) ) ),
			'content'   => $p->post_content,
		) );
	}

	public static function plugins_list() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$upd = get_site_transient( 'update_plugins' );
		$out = array();
		foreach ( get_plugins() as $file => $d ) {
			$out[] = array(
				'file'    => $file,
				'name'    => $d['Name'],
				'version' => $d['Version'],
				'active'  => is_plugin_active( $file ),
				'update'  => isset( $upd->response[ $file ] ) ? $upd->response[ $file ]->new_version : null,
			);
		}
		return array( 'plugins' => $out );
	}

	public static function activity_recent( $a ) {
		$rows = FBCC_Store::activity( min( 100, max( 1, (int) ( $a['limit'] ?? 20 ) ) ) );
		return array( 'items' => array_map( function ( $r ) {
			return array(
				'time'    => $r->created_at . ' UTC',
				'tool'    => $r->tool,
				'summary' => $r->summary,
				'result'  => $r->result,
			);
		}, $rows ) );
	}

	public static function approvals_list( $a ) {
		$rows = FBCC_Store::approvals( $a['status'] ?? 'pending', 30 );
		return array( 'items' => array_map( function ( $r ) {
			return array(
				'id'      => (int) $r->id,
				'tool'    => $r->tool,
				'title'   => $r->title,
				'status'  => $r->status,
				'created' => $r->created_at . ' UTC',
				'decided' => $r->decided_at ? $r->decided_at . ' UTC' : null,
				'result'  => $r->result,
			);
		}, $rows ) );
	}

	public static function task_status( $a ) {
		$steps = isset( $a['steps'] ) && is_array( $a['steps'] ) ? array_slice( array_map( 'sanitize_text_field', $a['steps'] ), 0, 20 ) : array();
		$t     = array(
			'title' => sanitize_text_field( $a['title'] ?? '' ),
			'step'  => max( 0, (int) ( $a['step'] ?? 0 ) ),
			'total' => max( 1, (int) ( $a['total'] ?? max( 1, count( $steps ) ) ) ),
			'note'  => sanitize_text_field( $a['note'] ?? '' ),
			'steps' => $steps,
			'brief' => sanitize_textarea_field( $a['brief'] ?? '' ),
			'done'  => ! empty( $a['done'] ),
		);
		if ( ! $t['steps'] && ! $t['done'] ) {
			$prev = get_option( FBCC_Store::OPT_TASK );
			if ( is_array( $prev ) && ( $prev['title'] ?? '' ) === $t['title'] && ! empty( $prev['steps'] ) ) {
				$t['steps'] = $prev['steps']; // keep the plan visible between steps
			}
		}
		FBCC_Store::set_task( $t );
		if ( $t['done'] && class_exists( 'FBCC_Browser' ) ) {
			FBCC_Browser::end( 'task finished' );
		}
		return array( 'shown' => true );
	}

	/* ------------------------------------------------------------ */
	/* Write tools (run as claude-agent)                            */
	/* ------------------------------------------------------------ */

	public static function content_create_draft( $a ) {
		$type = in_array( $a['post_type'] ?? 'page', array( 'page', 'post' ), true ) ? ( $a['post_type'] ?? 'page' ) : 'page';
		if ( ! current_user_can( 'page' === $type ? 'edit_pages' : 'edit_posts' ) ) {
			return new WP_Error( 'forbidden', 'claude-agent may not create this type.' );
		}
		$id   = wp_insert_post( wp_slash( array(
			'post_type'    => $type,
			'post_status'  => 'draft',
			'post_title'   => sanitize_text_field( $a['title'] ),
			'post_content' => wp_kses_post( $a['content'] ?? '' ),
			'post_excerpt' => sanitize_textarea_field( $a['excerpt'] ?? '' ),
			'post_parent'  => 'page' === $type ? (int) ( $a['parent'] ?? 0 ) : 0,
			'post_author'  => get_current_user_id(),
		) ), true );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		return array(
			'_summary' => 'Created draft ' . $type . ' “' . sanitize_text_field( $a['title'] ) . '” (#' . $id . ')',
			'_undo'    => get_edit_post_link( $id, 'raw' ),
			'id'       => $id,
			'status'   => 'draft',
			'edit'     => get_edit_post_link( $id, 'raw' ),
		);
	}

	public static function content_update( $a ) {
		$p = self::post_or_error( $a['id'] ?? 0 );
		if ( is_wp_error( $p ) ) {
			return $p;
		}
		$changes = array_intersect_key( $a, array_flip( array( 'title', 'content', 'excerpt' ) ) );
		if ( ! $changes ) {
			return new WP_Error( 'nothing_to_change', 'Give at least one of title, content or excerpt.' );
		}
		if ( in_array( $p->post_status, array( 'publish', 'private', 'future' ), true ) ) {
			$by_plan = FBCC_Plan::intercept( 'content_update', 'Update “' . get_the_title( $p ) . '”', array_merge( array( 'id' => $p->ID ), $changes ) );
			if ( null !== $by_plan ) {
				return $by_plan;
			}
			$id = FBCC_Gateway::queue(
				'content_update',
				'Update ' . ( 'page' === $p->post_type ? 'page' : 'post' ) . ' “' . get_the_title( $p ) . '” (live)',
				$a['reason'] ?? '',
				array_merge( array( 'id' => $p->ID ), $changes )
			);
			return array(
				'_queued'  => $id,
				'_summary' => 'Asked to update live “' . get_the_title( $p ) . '” — waiting for approval #' . $id,
				'queued'   => true,
				'approval' => $id,
				'message'  => 'This item is published, so the change is waiting for the owner to approve it. Check approvals_list later.',
			);
		}
		return self::apply_update( $p->ID, $changes );
	}

	/** Shared by content_update (drafts) and approval execution (live items). */
	public static function apply_update( $id, array $changes ) {
		$pre = wp_save_post_revision( $id ); // snapshot the current state
		if ( ! $pre ) {
			$revs = wp_get_post_revisions( $id, array( 'numberposts' => 1, 'fields' => 'ids' ) );
			$pre  = $revs ? reset( $revs ) : 0;
		}
		$data = array( 'ID' => (int) $id );
		if ( isset( $changes['title'] ) ) {
			$data['post_title'] = sanitize_text_field( $changes['title'] );
		}
		if ( isset( $changes['content'] ) ) {
			$data['post_content'] = wp_kses_post( $changes['content'] );
		}
		if ( isset( $changes['excerpt'] ) ) {
			$data['post_excerpt'] = sanitize_textarea_field( $changes['excerpt'] );
		}
		$r = wp_update_post( wp_slash( $data ), true );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return array(
			'_summary' => 'Updated “' . get_the_title( $id ) . '” (' . implode( ', ', array_keys( $changes ) ) . ')',
			'_undo'    => $pre ? admin_url( 'revision.php?revision=' . (int) $pre ) : '',
			'id'       => (int) $id,
			'updated'  => array_keys( $changes ),
		);
	}

	/* ------------------------------------------------------------ */
	/* Approval tools                                               */
	/* ------------------------------------------------------------ */

	public static function publish_prepare( $a ) {
		$p = self::post_or_error( $a['id'] ?? 0 );
		if ( is_wp_error( $p ) ) {
			return $p;
		}
		if ( 'publish' === $p->post_status ) {
			return new WP_Error( 'already_published', 'It is already published.' );
		}
		return array(
			'title'   => 'Publish ' . $p->post_type . ' “' . get_the_title( $p ) . '”',
			'payload' => array( 'id' => $p->ID ),
		);
	}

	public static function publish_execute( $payload ) {
		$id = (int) $payload['id'];
		$r  = wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ), true );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return array(
			'_summary' => 'Published “' . get_the_title( $id ) . '”',
			'_undo'    => get_edit_post_link( $id, 'raw' ),
		);
	}

	public static function plugins_update_prepare( $a ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$upd       = get_site_transient( 'update_plugins' );
		$available = $upd && ! empty( $upd->response ) ? array_keys( $upd->response ) : array();
		$want      = ! empty( $a['plugins'] ) ? array_values( array_intersect( (array) $a['plugins'], $available ) ) : $available;
		if ( ! $want ) {
			return new WP_Error( 'no_updates', 'No matching plugins have an update.' );
		}
		$all   = get_plugins();
		$names = array_map( function ( $f ) use ( $all ) {
			return isset( $all[ $f ] ) ? $all[ $f ]['Name'] : $f;
		}, $want );
		return array(
			'title'   => 'Update ' . count( $want ) . ' plugin' . ( count( $want ) > 1 ? 's' : '' ) . ' (' . implode( ', ', $names ) . ')',
			'payload' => array( 'plugins' => $want ),
		);
	}

	public static function plugins_update_execute( $payload ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		wp_update_plugins();
		$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
		$res      = $upgrader->bulk_upgrade( (array) $payload['plugins'] );
		if ( ! is_array( $res ) ) {
			return new WP_Error( 'update_failed', 'The updater could not run (file permissions?).' );
		}
		$ok = array();
		$ko = array();
		foreach ( $res as $file => $r ) {
			if ( is_wp_error( $r ) || false === $r || null === $r ) {
				$ko[] = $file;
			} else {
				$ok[] = $file;
			}
		}
		if ( $ko && ! $ok ) {
			return new WP_Error( 'update_failed', 'Failed: ' . implode( ', ', $ko ) );
		}
		return array(
			'_summary' => 'Updated plugins: ' . implode( ', ', $ok ) . ( $ko ? ' — failed: ' . implode( ', ', $ko ) : '' ),
		);
	}
}
