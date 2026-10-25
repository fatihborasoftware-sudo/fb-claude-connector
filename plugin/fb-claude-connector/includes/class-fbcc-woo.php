<?php
/**
 * WooCommerce tools (1.4.0):
 *   woo_products_list   – the shop's products (read)
 *   woo_product_set     – create or update a simple product: name, prices, texts, images, categories
 *   woo_settings_set    – currency and price format, store address country, store pages, a shipping zone
 *                         with free shipping over an amount and an optional flat rate
 * Products and store settings change the live shop, so they go to approvals — unless an approved
 * build plan includes the shop (plan field "shop").
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FBCC_Woo {

	public static function init() {
		add_filter( 'fbcc_tools', array( __CLASS__, 'tools' ), 8 );
	}

	private static function obj( array $props, array $required = array() ) {
		$s = array( 'type' => 'object', 'properties' => (object) $props, 'additionalProperties' => false );
		if ( $required ) {
			$s['required'] = $required;
		}
		return $s;
	}

	private static function active() {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' );
	}

	private static function missing() {
		return new WP_Error( 'no_woocommerce', 'WooCommerce is not active. Install it with plugins_install slug "woocommerce", then call this again.' );
	}

	public static function tools( $t ) {
		$t['woo_products_list'] = array(
			'title'       => 'List products',
			'description' => 'WooCommerce products with id, name, status, prices, sku, image id, categories and link. Supports search.',
			'level'       => 'read',
			'kind'        => 'read',
			'schema'      => self::obj( array(
				'search'   => array( 'type' => 'string' ),
				'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20 ),
			) ),
			'run'         => array( __CLASS__, 'products_list' ),
		);
		$t['woo_product_set'] = array(
			'title'       => 'Create or update a product',
			'description' => 'Create a simple WooCommerce product, or update one (by id, or by an existing product with the same name). Prices are plain numbers like "1350" or "1199.90"; sale_price "" removes the sale. image and gallery are image ids from media_list / media_upload; categories are names (missing ones are created). Goes to approvals unless the build plan includes the shop.',
			'level'       => 'editor',
			'kind'        => 'approval',
			'schema'      => self::obj( array(
				'id'                => array( 'type' => 'integer', 'description' => 'Product id to update; leave out to create (or update the product with this name)' ),
				'name'              => array( 'type' => 'string' ),
				'regular_price'     => array( 'type' => 'string' ),
				'sale_price'        => array( 'type' => 'string', 'description' => '"" removes the sale price' ),
				'description'       => array( 'type' => 'string', 'description' => 'Long description (block markup or HTML)' ),
				'short_description' => array( 'type' => 'string' ),
				'image'             => array( 'type' => 'integer', 'description' => 'Main image id' ),
				'gallery'           => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
				'categories'        => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'sku'               => array( 'type' => 'string' ),
				'featured'          => array( 'type' => 'boolean' ),
				'status'            => array( 'type' => 'string', 'enum' => array( 'publish', 'draft' ), 'default' => 'publish' ),
				'reason'            => array( 'type' => 'string' ),
			), array( 'reason' ) ),
			'prepare'     => array( __CLASS__, 'product_prepare' ),
			'execute'     => array( __CLASS__, 'product_execute' ),
		);
		$t['woo_settings_set'] = array(
			'title'       => 'Shop settings and shipping',
			'description' => 'WooCommerce store settings: currency (e.g. "TRY") and price format, the store country (e.g. "TR" or "TR:TR34"), the store pages (page ids for shop, cart, checkout, myaccount, terms), and one shipping zone (zone_name, zone_countries) with free shipping from free_shipping_min and/or a flat_rate cost. Give only what should change. Goes to approvals unless the build plan includes the shop.',
			'level'       => 'editor',
			'kind'        => 'approval',
			'schema'      => self::obj( array(
				'currency'          => array( 'type' => 'string', 'description' => 'ISO code, e.g. TRY, EUR, USD' ),
				'currency_pos'      => array( 'type' => 'string', 'enum' => array( 'left', 'right', 'left_space', 'right_space' ) ),
				'thousand_sep'      => array( 'type' => 'string' ),
				'decimal_sep'       => array( 'type' => 'string' ),
				'decimals'          => array( 'type' => 'integer', 'minimum' => 0, 'maximum' => 4 ),
				'country'           => array( 'type' => 'string', 'description' => 'Store country or country:state, e.g. TR or TR:TR34' ),
				'pages'             => array(
					'type'                 => 'object',
					'properties'           => (object) array(
						'shop'      => array( 'type' => 'integer' ),
						'cart'      => array( 'type' => 'integer' ),
						'checkout'  => array( 'type' => 'integer' ),
						'myaccount' => array( 'type' => 'integer' ),
						'terms'     => array( 'type' => 'integer' ),
					),
					'additionalProperties' => false,
				),
				'zone_name'         => array( 'type' => 'string', 'description' => 'Shipping zone name, e.g. "Türkiye" (created or updated)' ),
				'zone_countries'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Country codes of the zone, e.g. ["TR"]; default: the store country' ),
				'free_shipping_min' => array( 'type' => 'number', 'description' => 'Free shipping from this order amount' ),
				'flat_rate'         => array( 'type' => 'number', 'description' => 'Shipping cost below the free shipping amount' ),
				'reason'            => array( 'type' => 'string' ),
			), array( 'reason' ) ),
			'prepare'     => array( __CLASS__, 'settings_prepare' ),
			'execute'     => array( __CLASS__, 'settings_execute' ),
		);
		return $t;
	}

	/* -- products ----------------------------------------------------- */

	private static function product_brief( $p ) {
		return array(
			'id'            => $p->get_id(),
			'name'          => $p->get_name(),
			'status'        => $p->get_status(),
			'type'          => $p->get_type(),
			'regular_price' => $p->get_regular_price(),
			'sale_price'    => $p->get_sale_price(),
			'sku'           => $p->get_sku(),
			'image'         => (int) $p->get_image_id(),
			'categories'    => wp_list_pluck( get_the_terms( $p->get_id(), 'product_cat' ) ?: array(), 'name' ),
			'link'          => get_permalink( $p->get_id() ),
		);
	}

	public static function products_list( $a ) {
		if ( ! self::active() ) {
			return self::missing();
		}
		$args = array(
			'limit'   => min( 50, max( 1, (int) ( $a['per_page'] ?? 20 ) ) ),
			'status'  => array( 'publish', 'draft', 'pending', 'private' ),
			'orderby' => 'title',
			'order'   => 'ASC',
		);
		if ( ! empty( $a['search'] ) ) {
			$args['s'] = sanitize_text_field( (string) $a['search'] );
		}
		return array( 'items' => array_map( array( __CLASS__, 'product_brief' ), wc_get_products( $args ) ) );
	}

	private static function by_name( $name ) {
		$found = get_posts( array( 'post_type' => 'product', 'title' => $name, 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'numberposts' => 1, 'fields' => 'ids' ) );
		return $found ? (int) $found[0] : 0;
	}

	private static function price( $v ) {
		$v = trim( str_replace( ',', '.', (string) $v ) );
		return '' === $v ? '' : wc_format_decimal( $v );
	}

	public static function product_prepare( $a ) {
		if ( ! self::active() ) {
			return self::missing();
		}
		$id = (int) ( $a['id'] ?? 0 );
		if ( $id && ! wc_get_product( $id ) ) {
			return new WP_Error( 'not_found', 'No product with id ' . $id . '.' );
		}
		$name = sanitize_text_field( (string) ( $a['name'] ?? '' ) );
		if ( ! $id && '' === $name ) {
			return new WP_Error( 'missing_argument', 'Give a name to create a product, or the id of one to update.' );
		}
		if ( ! $id ) {
			$id = self::by_name( $name );
		}
		$payload = array( 'id' => $id );
		if ( '' !== $name ) {
			$payload['name'] = $name;
		}
		foreach ( array( 'regular_price', 'sale_price' ) as $k ) {
			if ( isset( $a[ $k ] ) ) {
				$payload[ $k ] = self::price( $a[ $k ] );
				if ( '' !== $payload[ $k ] && ! is_numeric( $payload[ $k ] ) ) {
					return new WP_Error( 'bad_price', $k . ' must be a number like 1350 or 1199.90.' );
				}
			}
		}
		if ( isset( $payload['regular_price'], $payload['sale_price'] ) && '' !== $payload['sale_price'] && (float) $payload['sale_price'] >= (float) $payload['regular_price'] ) {
			return new WP_Error( 'bad_price', 'The sale price must be lower than the regular price.' );
		}
		foreach ( array( 'description', 'short_description' ) as $k ) {
			if ( isset( $a[ $k ] ) ) {
				$payload[ $k ] = wp_kses_post( (string) $a[ $k ] );
			}
		}
		$imgs = array();
		if ( isset( $a['image'] ) ) {
			$imgs[]           = (int) $a['image'];
			$payload['image'] = (int) $a['image'];
		}
		if ( isset( $a['gallery'] ) ) {
			$payload['gallery'] = array_values( array_filter( array_map( 'intval', array_slice( (array) $a['gallery'], 0, 12 ) ) ) );
			$imgs               = array_merge( $imgs, $payload['gallery'] );
		}
		foreach ( $imgs as $img ) {
			if ( $img && ! wp_attachment_is_image( $img ) ) {
				return new WP_Error( 'bad_image', 'Image #' . $img . ' is not an image in the media library (see media_list).' );
			}
		}
		if ( isset( $a['categories'] ) ) {
			$payload['categories'] = array_values( array_filter( array_map( 'sanitize_text_field', array_slice( (array) $a['categories'], 0, 10 ) ) ) );
		}
		if ( isset( $a['sku'] ) ) {
			$payload['sku'] = sanitize_text_field( (string) $a['sku'] );
		}
		if ( isset( $a['featured'] ) ) {
			$payload['featured'] = (bool) $a['featured'];
		}
		$payload['status'] = 'draft' === ( $a['status'] ?? 'publish' ) ? 'draft' : 'publish';
		$label             = '' !== $name ? $name : get_the_title( $id );
		$what              = array();
		if ( isset( $payload['regular_price'] ) ) {
			$what[] = 'price ' . $payload['regular_price'];
		}
		if ( isset( $payload['sale_price'] ) ) {
			$what[] = '' === $payload['sale_price'] ? 'no sale' : 'sale ' . $payload['sale_price'];
		}
		return array(
			'title'   => ( $id ? 'Update' : 'Create' ) . ' product “' . $label . '”' . ( $what ? ' (' . implode( ', ', $what ) . ')' : '' ),
			'payload' => $payload,
		);
	}

	public static function product_execute( $p ) {
		if ( ! self::active() ) {
			return self::missing();
		}
		$id      = (int) ( $p['id'] ?? 0 );
		$product = $id ? wc_get_product( $id ) : new WC_Product_Simple();
		if ( ! $product ) {
			return new WP_Error( 'not_found', 'The product no longer exists.' );
		}
		try {
			if ( isset( $p['name'] ) ) {
				$product->set_name( $p['name'] );
			}
			if ( isset( $p['regular_price'] ) ) {
				$product->set_regular_price( $p['regular_price'] );
			}
			if ( isset( $p['sale_price'] ) ) {
				$product->set_sale_price( $p['sale_price'] );
			}
			if ( isset( $p['description'] ) ) {
				$product->set_description( $p['description'] );
			}
			if ( isset( $p['short_description'] ) ) {
				$product->set_short_description( $p['short_description'] );
			}
			if ( isset( $p['image'] ) ) {
				$product->set_image_id( (int) $p['image'] );
			}
			if ( isset( $p['gallery'] ) ) {
				$product->set_gallery_image_ids( $p['gallery'] );
			}
			if ( isset( $p['sku'] ) ) {
				$product->set_sku( $p['sku'] );
			}
			if ( isset( $p['featured'] ) ) {
				$product->set_featured( (bool) $p['featured'] );
			}
			if ( isset( $p['categories'] ) ) {
				$ids = array();
				foreach ( $p['categories'] as $cat ) {
					$term = term_exists( $cat, 'product_cat' );
					if ( ! $term ) {
						$term = wp_insert_term( $cat, 'product_cat' );
					}
					if ( is_wp_error( $term ) ) {
						return $term;
					}
					$ids[] = (int) ( is_array( $term ) ? $term['term_id'] : $term );
				}
				$product->set_category_ids( $ids );
			}
			$product->set_status( $p['status'] ?? 'publish' );
			$saved = $product->save();
		} catch ( \Exception $e ) {
			return new WP_Error( 'product_error', $e->getMessage() );
		}
		return array(
			'_summary' => ( $id ? 'Updated' : 'Created' ) . ' product “' . $product->get_name() . '” (#' . $saved . ', ' . $product->get_status() . ')',
			'_undo'    => admin_url( 'post.php?post=' . (int) $saved . '&action=edit' ),
			'id'       => (int) $saved,
			'link'     => get_permalink( $saved ),
		);
	}

	/* -- store settings & shipping ------------------------------------- */

	const PAGE_OPTIONS = array(
		'shop'      => 'woocommerce_shop_page_id',
		'cart'      => 'woocommerce_cart_page_id',
		'checkout'  => 'woocommerce_checkout_page_id',
		'myaccount' => 'woocommerce_myaccount_page_id',
		'terms'     => 'woocommerce_terms_page_id',
	);

	public static function settings_prepare( $a ) {
		if ( ! self::active() ) {
			return self::missing();
		}
		$p    = array();
		$what = array();
		if ( ! empty( $a['currency'] ) ) {
			$cur = strtoupper( sanitize_key( (string) $a['currency'] ) );
			if ( ! array_key_exists( $cur, get_woocommerce_currencies() ) ) {
				return new WP_Error( 'bad_currency', 'Unknown currency “' . $cur . '”.' );
			}
			$p['currency'] = $cur;
			$what[]        = 'currency ' . $cur;
		}
		if ( ! empty( $a['currency_pos'] ) ) {
			$p['currency_pos'] = sanitize_key( (string) $a['currency_pos'] );
		}
		foreach ( array( 'thousand_sep', 'decimal_sep' ) as $k ) {
			if ( isset( $a[ $k ] ) ) {
				$p[ $k ] = substr( (string) $a[ $k ], 0, 3 );
			}
		}
		if ( isset( $a['decimals'] ) ) {
			$p['decimals'] = max( 0, min( 4, (int) $a['decimals'] ) );
		}
		if ( isset( $p['currency_pos'] ) || isset( $p['thousand_sep'] ) || isset( $p['decimal_sep'] ) || isset( $p['decimals'] ) ) {
			$what[] = 'price format';
		}
		if ( ! empty( $a['country'] ) ) {
			$p['country'] = strtoupper( preg_replace( '/[^A-Za-z0-9:]/', '', (string) $a['country'] ) );
			$what[]       = 'store country ' . $p['country'];
		}
		if ( ! empty( $a['pages'] ) && is_array( $a['pages'] ) ) {
			foreach ( self::PAGE_OPTIONS as $k => $opt ) {
				if ( isset( $a['pages'][ $k ] ) ) {
					$pid = (int) $a['pages'][ $k ];
					if ( $pid && 'page' !== get_post_type( $pid ) ) {
						return new WP_Error( 'bad_page', $k . ' must be a page id.' );
					}
					$p['pages'][ $k ] = $pid;
					$what[]           = $k . ' page → ' . ( $pid ? '“' . get_the_title( $pid ) . '”' : 'none' );
				}
			}
		}
		$shipping = isset( $a['free_shipping_min'] ) || isset( $a['flat_rate'] ) || ! empty( $a['zone_name'] );
		if ( $shipping ) {
			$p['zone_name'] = sanitize_text_field( (string) ( $a['zone_name'] ?? '' ) );
			if ( '' === $p['zone_name'] ) {
				$p['zone_name'] = 'Shipping';
			}
			$countries = isset( $a['zone_countries'] ) ? (array) $a['zone_countries'] : array( strtok( (string) ( $p['country'] ?? get_option( 'woocommerce_default_country', '' ) ), ':' ) );
			$p['zone_countries'] = array_values( array_filter( array_map( function ( $c ) {
				return strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $c ) );
			}, $countries ) ) );
			if ( isset( $a['free_shipping_min'] ) ) {
				$p['free_shipping_min'] = max( 0, (float) $a['free_shipping_min'] );
			}
			if ( isset( $a['flat_rate'] ) ) {
				$p['flat_rate'] = max( 0, (float) $a['flat_rate'] );
			}
			$what[] = 'shipping zone “' . $p['zone_name'] . '”' . ( isset( $p['free_shipping_min'] ) ? ', free over ' . $p['free_shipping_min'] : '' ) . ( isset( $p['flat_rate'] ) ? ', flat rate ' . $p['flat_rate'] : '' );
		}
		if ( ! $p ) {
			return new WP_Error( 'nothing', 'Give at least one setting to change.' );
		}
		return array( 'title' => 'Shop settings: ' . implode( ', ', $what ), 'payload' => $p );
	}

	public static function settings_execute( $p ) {
		if ( ! self::active() ) {
			return self::missing();
		}
		$done = array();
		$map  = array(
			'currency'     => 'woocommerce_currency',
			'currency_pos' => 'woocommerce_currency_pos',
			'thousand_sep' => 'woocommerce_price_thousand_sep',
			'decimal_sep'  => 'woocommerce_price_decimal_sep',
			'decimals'     => 'woocommerce_price_num_decimals',
			'country'      => 'woocommerce_default_country',
		);
		foreach ( $map as $k => $opt ) {
			if ( isset( $p[ $k ] ) ) {
				update_option( $opt, $p[ $k ] );
				$done[] = $k;
			}
		}
		foreach ( (array) ( $p['pages'] ?? array() ) as $k => $pid ) {
			if ( isset( self::PAGE_OPTIONS[ $k ] ) ) {
				update_option( self::PAGE_OPTIONS[ $k ], (int) $pid );
				$done[] = $k . ' page';
			}
		}
		if ( ! empty( $p['zone_name'] ) ) {
			$r = self::shipping( $p );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			$done[] = $r;
		}
		return array(
			'_summary' => 'Shop settings updated: ' . implode( ', ', $done ),
			'_undo'    => admin_url( 'admin.php?page=wc-settings' ),
			'updated'  => $done,
		);
	}

	/** Creates or updates one zone; its free shipping and flat rate methods are added or changed in place. */
	private static function shipping( array $p ) {
		if ( ! class_exists( 'WC_Shipping_Zone' ) || ! class_exists( 'WC_Shipping_Zones' ) ) {
			return new WP_Error( 'no_shipping', 'WooCommerce shipping zones are not available.' );
		}
		$zone = null;
		foreach ( WC_Shipping_Zones::get_zones() as $z ) {
			if ( $z['zone_name'] === $p['zone_name'] ) {
				$zone = new WC_Shipping_Zone( (int) $z['id'] );
				break;
			}
		}
		if ( ! $zone ) {
			$zone = new WC_Shipping_Zone();
			$zone->set_zone_name( $p['zone_name'] );
		}
		if ( ! empty( $p['zone_countries'] ) ) {
			$zone->clear_locations( array( 'country', 'state', 'continent' ) );
			foreach ( $p['zone_countries'] as $c ) {
				$zone->add_location( $c, 'country' );
			}
		}
		$zone->save();

		$have = array();
		foreach ( $zone->get_shipping_methods() as $m ) {
			$have[ $m->id ] = $m;
		}
		$set = function ( $type, array $settings ) use ( $zone, $have ) {
			$instance = isset( $have[ $type ] ) ? (int) $have[ $type ]->instance_id : (int) $zone->add_shipping_method( $type );
			if ( ! $instance ) {
				return false;
			}
			$key = 'woocommerce_' . $type . '_' . $instance . '_settings';
			update_option( $key, array_merge( (array) get_option( $key, array() ), $settings ) );
			return true;
		};
		$what = array();
		if ( isset( $p['free_shipping_min'] ) ) {
			$set( 'free_shipping', array(
				'title'            => __( 'Free shipping', 'woocommerce' ),
				'requires'         => 'min_amount',
				'min_amount'       => wc_format_decimal( $p['free_shipping_min'] ),
				'ignore_discounts' => 'no',
			) );
			$what[] = 'free over ' . $p['free_shipping_min'];
		}
		if ( isset( $p['flat_rate'] ) ) {
			$set( 'flat_rate', array(
				'title'      => __( 'Flat rate', 'woocommerce' ),
				'tax_status' => 'taxable',
				'cost'       => wc_format_decimal( $p['flat_rate'] ),
			) );
			$what[] = 'flat rate ' . $p['flat_rate'];
		}
		if ( class_exists( 'WC_Cache_Helper' ) ) {
			WC_Cache_Helper::get_transient_version( 'shipping', true );
		}
		return 'shipping zone “' . $p['zone_name'] . '”' . ( $what ? ' (' . implode( ', ', $what ) . ')' : '' );
	}
}
