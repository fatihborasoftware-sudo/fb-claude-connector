<?php
/**
 * FB Mind Map integration: read site plans from mind maps and write progress back.
 * Active only when the FB Mind Map plugin (0.3+) is installed on the same site.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FBCC_MindMap {

	const BACKUP_META = '_fbcc_map_backups';
	const DONE        = '✓ ';

	public static function init() {
		add_filter( 'fbcc_tools', array( __CLASS__, 'tools' ) );
		add_action( 'admin_post_fbcc_map_restore', array( __CLASS__, 'restore' ) );
	}

	public static function available() {
		return class_exists( 'FB_Mind_Map_Data' ) && defined( 'FB_MIND_MAP_POST_TYPE' );
	}

	public static function tools( $t ) {
		if ( ! self::available() ) {
			return $t;
		}
		$t['mindmap_list'] = array(
			'title'       => 'List mind maps',
			'description' => 'Mind maps on this site (FB Mind Map): id, title, node and note counts, last change. Site plans live here.',
			'level'       => 'read',
			'kind'        => 'read',
			'schema'      => array( 'type' => 'object', 'properties' => (object) array( 'search' => array( 'type' => 'string' ) ), 'additionalProperties' => false ),
			'run'         => array( __CLASS__, 'list_maps' ),
		);
		$t['mindmap_get'] = array(
			'title'       => 'Read a mind map',
			'description' => 'One mind map as a compact tree: {id, text, notes, done, children}. Notes are plain text (text, prompt and HTML blocks; images/files as [image: caption]). Use node + depth to read one branch at a time. Nodes whose text starts with "✓ " are done.',
			'level'       => 'read',
			'kind'        => 'read',
			'schema'      => array(
				'type'                 => 'object',
				'properties'           => (object) array(
					'id'    => array( 'type' => 'integer' ),
					'node'  => array( 'type' => 'string', 'description' => 'Start from this node id (default: root)' ),
					'depth' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 40, 'description' => 'Levels below the start node (default: all)' ),
					'notes' => array( 'type' => 'boolean', 'default' => true, 'description' => 'Include note text' ),
				),
				'required'             => array( 'id' ),
				'additionalProperties' => false,
			),
			'run'         => array( __CLASS__, 'get_map' ),
		);
		$t['mindmap_update'] = array(
			'title'       => 'Update a mind map',
			'description' => 'Write progress back to a plan. ops (max 50): mark_done / mark_todo {node}, set_text {node,text}, add_note {node,text}, add_child {node,text,note?}. The previous version is kept and can be restored from the activity log. Tell the owner to reload the map editor if it is open, or their next save overwrites yours.',
			'level'       => 'editor',
			'kind'        => 'write',
			'schema'      => array(
				'type'                 => 'object',
				'properties'           => (object) array(
					'id'     => array( 'type' => 'integer' ),
					'ops'    => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => (object) array(
								'op'   => array( 'type' => 'string', 'enum' => array( 'mark_done', 'mark_todo', 'set_text', 'add_note', 'add_child' ) ),
								'node' => array( 'type' => 'string' ),
								'text' => array( 'type' => 'string' ),
								'note' => array( 'type' => 'string' ),
							),
							'required'   => array( 'op', 'node' ),
						),
					),
					'reason' => array( 'type' => 'string' ),
				),
				'required'             => array( 'id', 'ops' ),
				'additionalProperties' => false,
			),
			'run'         => array( __CLASS__, 'update_map' ),
		);
		return $t;
	}

	/* ------------------------------------------------------------ */

	private static function map_or_error( $id, $cap = 'read_fb_mind_map' ) {
		$post = get_post( (int) $id );
		if ( ! $post || FB_MIND_MAP_POST_TYPE !== $post->post_type || 'trash' === $post->post_status ) {
			return new WP_Error( 'not_found', 'No mind map with id ' . (int) $id . '.' );
		}
		if ( ! current_user_can( $cap, $post->ID ) ) {
			return new WP_Error( 'forbidden', 'claude-agent may not access this mind map.' );
		}
		return $post;
	}

	public static function list_maps( $a ) {
		$q   = new WP_Query( array(
			'post_type'      => FB_MIND_MAP_POST_TYPE,
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'posts_per_page' => 50,
			's'              => sanitize_text_field( (string) ( $a['search'] ?? '' ) ),
			'orderby'        => 'modified',
			'order'          => 'DESC',
		) );
		$out = array();
		foreach ( $q->posts as $p ) {
			if ( ! current_user_can( 'read_fb_mind_map', $p->ID ) ) {
				continue;
			}
			$doc   = FB_Mind_Map_Data::load( $p->ID, false );
			$out[] = array(
				'id'       => $p->ID,
				'title'    => get_the_title( $p ),
				'nodes'    => FB_Mind_Map_Data::count_nodes( $doc['root'] ),
				'notes'    => FB_Mind_Map_Data::count_notes( $doc['root'] ),
				'modified' => $p->post_modified_gmt . ' UTC',
			);
		}
		return array( 'maps' => $out );
	}

	/** Returns a reference to the node with $id, or null. */
	private static function &find_ref( &$node, $id ) {
		$null = null;
		if ( (string) $node['id'] === (string) $id ) {
			return $node;
		}
		if ( ! empty( $node['children'] ) ) {
			foreach ( $node['children'] as $k => &$c ) {
				$hit = &self::find_ref( $c, $id );
				if ( null !== $hit ) {
					return $hit;
				}
			}
		}
		return $null;
	}

	private static function notes_text( array $node ) {
		$parts = array();
		foreach ( (array) ( $node['blocks'] ?? array() ) as $b ) {
			$type = $b['type'] ?? '';
			if ( in_array( $type, array( 'text', 'prompt' ), true ) ) {
				$parts[] = ( 'prompt' === $type ? '[prompt] ' : '' ) . (string) ( $b['text'] ?? '' );
			} elseif ( 'html' === $type ) {
				$parts[] = trim( wp_strip_all_tags( (string) ( $b['text'] ?? '' ) ) );
			} elseif ( in_array( $type, array( 'image', 'file' ), true ) ) {
				$parts[] = '[' . $type . ': ' . ( $b['caption'] ?? '' ) . ']';
			} elseif ( 'video' === $type ) {
				$parts[] = '[video: ' . ( $b['url'] ?? '' ) . ']';
			}
		}
		return trim( implode( "\n\n", array_filter( $parts ) ) );
	}

	private static function compact( array $node, $depth, $notes ) {
		$text = (string) ( $node['text'] ?? '' );
		$out  = array( 'id' => $node['id'], 'text' => $text );
		if ( 0 === strpos( $text, self::DONE ) ) {
			$out['done'] = true;
		}
		if ( $notes ) {
			$n = self::notes_text( $node );
			if ( '' !== $n ) {
				$out['notes'] = $n;
			}
		}
		$kids = (array) ( $node['children'] ?? array() );
		if ( $kids ) {
			if ( $depth > 0 ) {
				$out['children'] = array();
				foreach ( $kids as $c ) {
					$out['children'][] = self::compact( $c, $depth - 1, $notes );
				}
			} else {
				$out['more'] = count( $kids ) . ' children not shown';
			}
		}
		return $out;
	}

	public static function get_map( $a ) {
		$post = self::map_or_error( $a['id'] ?? 0 );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$doc   = FB_Mind_Map_Data::load( $post->ID, false );
		$start = $doc['root'];
		if ( ! empty( $a['node'] ) ) {
			$hit = &self::find_ref( $doc['root'], (string) $a['node'] );
			if ( null === $hit ) {
				return new WP_Error( 'no_node', 'No node "' . sanitize_text_field( $a['node'] ) . '" in this map.' );
			}
			$start = $hit;
		}
		return array(
			'id'    => $post->ID,
			'title' => get_the_title( $post ),
			'nodes' => FB_Mind_Map_Data::count_nodes( $doc['root'] ),
			'tree'  => self::compact( $start, isset( $a['depth'] ) ? max( 1, (int) $a['depth'] ) : 99, ! isset( $a['notes'] ) || $a['notes'] ),
		);
	}

	public static function update_map( $a ) {
		$post = self::map_or_error( $a['id'] ?? 0, 'edit_fb_mind_map' );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$ops = array_slice( (array) ( $a['ops'] ?? array() ), 0, 50 );
		if ( ! $ops ) {
			return new WP_Error( 'no_ops', 'Give at least one op.' );
		}
		$raw = get_post_meta( $post->ID, FB_MIND_MAP_META_KEY, true );
		$doc = FB_Mind_Map_Data::load( $post->ID, false );

		$done = array();
		foreach ( $ops as $op ) {
			$kind = $op['op'] ?? '';
			$text = sanitize_textarea_field( (string) ( $op['text'] ?? '' ) );
			$node = &self::find_ref( $doc['root'], (string) ( $op['node'] ?? '' ) );
			if ( null === $node ) {
				return new WP_Error( 'no_node', 'No node "' . sanitize_text_field( (string) ( $op['node'] ?? '' ) ) . '" — nothing was changed.' );
			}
			switch ( $kind ) {
				case 'mark_done':
					if ( 0 !== strpos( $node['text'], self::DONE ) ) {
						$node['text'] = self::DONE . $node['text'];
					}
					$node['blocks'][] = array( 'type' => 'text', 'text' => 'Done ' . wp_date( 'j M Y H:i' ) . ' (Claude)' . ( $text ? ': ' . $text : '' ) );
					break;
				case 'mark_todo':
					if ( 0 === strpos( $node['text'], self::DONE ) ) {
						$node['text'] = substr( $node['text'], strlen( self::DONE ) );
					}
					break;
				case 'set_text':
					if ( '' === $text ) {
						return new WP_Error( 'empty', 'set_text needs text.' );
					}
					$node['text'] = $text;
					break;
				case 'add_note':
					if ( '' === $text ) {
						return new WP_Error( 'empty', 'add_note needs text.' );
					}
					$node['blocks'][] = array( 'type' => 'text', 'text' => $text );
					break;
				case 'add_child':
					if ( '' === $text ) {
						return new WP_Error( 'empty', 'add_child needs text.' );
					}
					$child = array( 'id' => 'c' . wp_generate_password( 9, false, false ), 'text' => $text, 'children' => array(), 'blocks' => array() );
					if ( ! empty( $op['note'] ) ) {
						$child['blocks'][] = array( 'type' => 'text', 'text' => sanitize_textarea_field( (string) $op['note'] ) );
					}
					$node['children'][] = $child;
					break;
				default:
					return new WP_Error( 'bad_op', 'Unknown op "' . sanitize_key( $kind ) . '" — nothing was changed.' );
			}
			unset( $node );
			$done[] = $kind;
		}

		// Keep the last 5 versions for undo.
		$backups = get_post_meta( $post->ID, self::BACKUP_META, true );
		$backups = is_array( $backups ) ? $backups : array();
		$backups[] = array( 'time' => time(), 'json' => (string) $raw );
		update_post_meta( $post->ID, self::BACKUP_META, wp_slash( array_slice( $backups, -5 ) ) );

		FB_Mind_Map_Data::save( $post->ID, $doc );
		wp_update_post( array( 'ID' => $post->ID ) ); // bump "modified"

		$counts = array_count_values( $done );
		$what   = array();
		foreach ( $counts as $k => $n ) {
			$what[] = $n . '× ' . $k;
		}
		return array(
			'_summary' => 'Mind map “' . get_the_title( $post ) . '”: ' . implode( ', ', $what ),
			'_undo'    => wp_nonce_url( admin_url( 'admin-post.php?action=fbcc_map_restore&map=' . $post->ID ), 'fbcc_map_restore_' . $post->ID ),
			'id'       => $post->ID,
			'applied'  => count( $done ),
			'note'     => 'If the owner has this map open in the editor, ask them to reload it before editing.',
		);
	}

	/** Undo: put back the version saved before Claude's last change. */
	public static function restore() {
		$id = isset( $_GET['map'] ) ? (int) $_GET['map'] : 0;
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		check_admin_referer( 'fbcc_map_restore_' . $id );
		$backups = get_post_meta( $id, self::BACKUP_META, true );
		if ( ! is_array( $backups ) || ! $backups ) {
			wp_die( 'No earlier version is stored for this map.' );
		}
		$last = array_pop( $backups );
		$doc  = json_decode( (string) $last['json'], true );
		if ( ! is_array( $doc ) ) {
			wp_die( 'The stored version could not be read.' );
		}
		FB_Mind_Map_Data::save( $id, $doc );
		update_post_meta( $id, self::BACKUP_META, wp_slash( $backups ) );
		FBCC_Store::log( 'mindmap_update', 'Restored mind map “' . get_the_title( $id ) . '” to the version of ' . wp_date( 'j M H:i', (int) $last['time'] ), 'done' );
		wp_safe_redirect( admin_url( 'admin.php?page=fbcc&tab=activity&fbcc_msg=' . rawurlencode( 'Mind map restored.' ) ) );
		exit;
	}
}
