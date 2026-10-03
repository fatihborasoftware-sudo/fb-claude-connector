<?php
/**
 * OAuth 2.1 for the MCP connector: discovery metadata, dynamic client
 * registration, authorization code + PKCE (S256), refresh-token rotation.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FBCC_OAuth {

	const ACCESS_TTL  = 3600;           // 1 hour
	const REFRESH_TTL = 2592000;        // 30 days
	const CODE_TTL    = 600;            // 10 minutes

	public static function init() {
		add_action( 'init', array( __CLASS__, 'route_front' ), 0 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/* ------------------------------------------------------------ */
	/* URLs                                                         */
	/* ------------------------------------------------------------ */

	public static function issuer() {
		return untrailingslashit( home_url() );
	}

	public static function authorize_url() {
		return home_url( '/fbcc-oauth/authorize' );
	}

	public static function mcp_url() {
		return rest_url( FBCC_NS . '/mcp' );
	}

	public static function resource_metadata_url() {
		return home_url( '/.well-known/oauth-protected-resource' );
	}

	/** Request path relative to the WordPress home path. */
	private static function request_path() {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( $home && '/' !== $home && 0 === strpos( $path, rtrim( $home, '/' ) ) ) {
			$path = substr( $path, strlen( rtrim( $home, '/' ) ) );
		}
		return '/' . ltrim( $path, '/' );
	}

	/* ------------------------------------------------------------ */
	/* Front-end routes: discovery + the consent screen             */
	/* ------------------------------------------------------------ */

	public static function route_front() {
		$path = rtrim( self::request_path(), '/' );

		if ( 0 === strpos( $path, '/.well-known/' ) && false !== strpos( $path, 'oauth' ) ) {
			FBCC_Store::trace( 'discovery', array( 'result' => $path ) );
		}
		// A path after the well-known name (RFC 8414 / 9728) names WHICH server is meant.
		// Answer only for ourselves, so a plugin on the main site never answers for one in a sub-folder.
		$mine = function ( $suffix, $own ) {
			$suffix = trim( (string) $suffix, '/' );
			return '' === $suffix || trim( (string) $own, '/' ) === $suffix;
		};
		$home_path = (string) wp_parse_url( home_url(), PHP_URL_PATH );
		$mcp_path  = (string) wp_parse_url( self::mcp_url(), PHP_URL_PATH );
		if ( preg_match( '#^/\.well-known/oauth-protected-resource(/.*)?$#', $path, $m ) && $mine( $m[1] ?? '', $mcp_path ) ) {
			self::json( self::resource_metadata() );
		}
		if ( preg_match( '#^/\.well-known/(oauth-authorization-server|openid-configuration)(/.*)?$#', $path, $m ) && $mine( $m[2] ?? '', $home_path ) ) {
			self::json( self::server_metadata() );
		}
		if ( '/fbcc-oauth/authorize' === $path ) {
			self::authorize_screen();
		}
	}

	private static function json( $data, $status = 200 ) {
		status_header( $status );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Access-Control-Allow-Origin: *' );
		echo wp_json_encode( $data, JSON_UNESCAPED_SLASHES );
		exit;
	}

	public static function resource_metadata() {
		return array(
			'resource'                 => self::mcp_url(),
			'authorization_servers'    => array( self::issuer() ),
			'scopes_supported'         => array( 'mcp' ),
			'bearer_methods_supported' => array( 'header' ),
			'resource_name'            => get_bloginfo( 'name' ) . ' – Claude Connector',
		);
	}

	public static function server_metadata() {
		return array(
			'issuer'                                => self::issuer(),
			'authorization_endpoint'                => self::authorize_url(),
			'token_endpoint'                        => rest_url( FBCC_NS . '/oauth/token' ),
			'registration_endpoint'                 => rest_url( FBCC_NS . '/oauth/register' ),
			'revocation_endpoint'                   => rest_url( FBCC_NS . '/oauth/revoke' ),
			'response_types_supported'              => array( 'code' ),
			'grant_types_supported'                 => array( 'authorization_code', 'refresh_token' ),
			'code_challenge_methods_supported'      => array( 'S256' ),
			'token_endpoint_auth_methods_supported' => array( 'none', 'client_secret_post', 'client_secret_basic' ),
			'scopes_supported'                      => array( 'mcp' ),
		);
	}

	/* ------------------------------------------------------------ */
	/* Redirect URI policy                                          */
	/* ------------------------------------------------------------ */

	public static function redirect_allowed( $uri ) {
		$p = wp_parse_url( $uri );
		if ( ! $p || empty( $p['scheme'] ) || empty( $p['host'] ) || isset( $p['fragment'] ) ) {
			return false;
		}
		$host = strtolower( $p['host'] );
		$ok   = false;
		if ( 'https' === $p['scheme'] && in_array( $host, array( 'claude.ai', 'claude.com', 'www.claude.ai', 'www.claude.com' ), true ) ) {
			$ok = true;
		}
		if ( 'http' === $p['scheme'] && in_array( $host, array( 'localhost', '127.0.0.1', '[::1]' ), true ) ) {
			$ok = true; // Claude Code / MCP Inspector on the user's own machine.
		}
		return (bool) apply_filters( 'fbcc_redirect_uri_allowed', $ok, $uri );
	}

	/* ------------------------------------------------------------ */
	/* REST: register, token, revoke                                */
	/* ------------------------------------------------------------ */

	public static function routes() {
		register_rest_route( FBCC_NS, '/oauth/register', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'register' ),
			'permission_callback' => '__return_true',
		) );
		register_rest_route( FBCC_NS, '/oauth/token', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'token' ),
			'permission_callback' => '__return_true',
		) );
		register_rest_route( FBCC_NS, '/oauth/revoke', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'revoke' ),
			'permission_callback' => '__return_true',
		) );
	}

	private static function error( $code, $desc, $status = 400 ) {
		FBCC_Store::trace( 'oauth', array( 'result' => $status . ' ' . $code . ': ' . $desc ) );
		$r = new WP_REST_Response( array( 'error' => $code, 'error_description' => $desc ), $status );
		$r->header( 'Cache-Control', 'no-store' );
		return $r;
	}

	private static function client_ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0';
	}

	public static function register( WP_REST_Request $req ) {
		$key = 'fbcc_reg_' . md5( self::client_ip() );
		$n   = (int) get_transient( $key );
		if ( $n >= 20 ) {
			return self::error( 'temporarily_unavailable', 'Too many registrations, try again later.', 429 );
		}
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );

		$body = $req->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = $req->get_body_params();
		}
		$uris = isset( $body['redirect_uris'] ) && is_array( $body['redirect_uris'] ) ? array_values( $body['redirect_uris'] ) : array();
		if ( ! $uris || count( $uris ) > 5 ) {
			return self::error( 'invalid_redirect_uri', 'redirect_uris is required (max 5).' );
		}
		foreach ( $uris as $u ) {
			if ( ! is_string( $u ) || ! self::redirect_allowed( $u ) ) {
				return self::error( 'invalid_redirect_uri', 'This redirect URI is not allowed on this site.' );
			}
		}

		$method = isset( $body['token_endpoint_auth_method'] ) ? (string) $body['token_endpoint_auth_method'] : 'none';
		if ( ! in_array( $method, array( 'none', 'client_secret_post', 'client_secret_basic' ), true ) ) {
			$method = 'none';
		}
		$name   = isset( $body['client_name'] ) ? sanitize_text_field( (string) $body['client_name'] ) : 'MCP client';
		$id     = 'fbcc_' . bin2hex( random_bytes( 12 ) );
		$secret = 'none' === $method ? '' : bin2hex( random_bytes( 24 ) );

		FBCC_Store::save_client( $id, array(
			'name'          => substr( $name, 0, 80 ),
			'redirect_uris' => $uris,
			'auth_method'   => $method,
			'secret_hash'   => $secret ? FBCC_Store::hash( $secret ) : '',
			'created'       => time(),
		) );

		$out = array(
			'client_id'                  => $id,
			'client_id_issued_at'        => time(),
			'client_name'                => $name,
			'redirect_uris'              => $uris,
			'grant_types'                => array( 'authorization_code', 'refresh_token' ),
			'response_types'             => array( 'code' ),
			'token_endpoint_auth_method' => $method,
		);
		FBCC_Store::trace( 'oauth', array( 'result' => '201 registered "' . $name . '" (' . $method . ')' ) );
		if ( $secret ) {
			$out['client_secret']            = $secret;
			$out['client_secret_expires_at'] = 0;
		}
		return new WP_REST_Response( $out, 201 );
	}

	/** Authenticates the client on the token endpoint. Returns client array or WP_REST_Response error. */
	private static function auth_client( WP_REST_Request $req, $params ) {
		$id     = isset( $params['client_id'] ) ? (string) $params['client_id'] : '';
		$secret = isset( $params['client_secret'] ) ? (string) $params['client_secret'] : '';
		$basic  = $req->get_header( 'authorization' );
		if ( $basic && 0 === stripos( $basic, 'basic ' ) ) {
			$dec = base64_decode( substr( $basic, 6 ), true );
			if ( $dec && false !== strpos( $dec, ':' ) ) {
				list( $bid, $bsec ) = explode( ':', $dec, 2 );
				$id     = rawurldecode( $bid );
				$secret = rawurldecode( $bsec );
			}
		}
		$client = $id ? FBCC_Store::client( $id ) : null;
		if ( ! $client ) {
			return self::error( 'invalid_client', 'Unknown client.', 401 );
		}
		if ( 'none' !== $client['auth_method'] && ! hash_equals( $client['secret_hash'], FBCC_Store::hash( $secret ) ) ) {
			return self::error( 'invalid_client', 'Client authentication failed.', 401 );
		}
		$client['id'] = $id;
		return $client;
	}

	public static function token( WP_REST_Request $req ) {
		$p = $req->get_body_params();
		if ( ! $p ) {
			$p = (array) $req->get_json_params();
		}
		if ( ! FBCC_Store::settings()['enabled'] ) {
			return self::error( 'access_denied', 'The Claude Connector is switched off on this site.', 403 );
		}
		$client = self::auth_client( $req, $p );
		if ( $client instanceof WP_REST_Response ) {
			return $client;
		}
		$grant = isset( $p['grant_type'] ) ? (string) $p['grant_type'] : '';

		if ( 'authorization_code' === $grant ) {
			$code = isset( $p['code'] ) ? (string) $p['code'] : '';
			$key  = 'fbcc_code_' . FBCC_Store::hash( $code );
			$data = $code ? get_transient( $key ) : false;
			if ( ! is_array( $data ) || $data['client_id'] !== $client['id'] ) {
				return self::error( 'invalid_grant', 'Code is invalid or expired.' );
			}
			delete_transient( $key ); // single use, once the right client presents it
			if ( isset( $p['redirect_uri'] ) && (string) $p['redirect_uri'] !== $data['redirect_uri'] ) {
				return self::error( 'invalid_grant', 'redirect_uri mismatch.' );
			}
			$verifier = isset( $p['code_verifier'] ) ? (string) $p['code_verifier'] : '';
			if ( ! self::pkce_ok( $verifier, $data['challenge'] ) ) {
				return self::error( 'invalid_grant', 'PKCE verification failed.' );
			}
			return self::token_response( $client['id'], (int) $data['user_id'] );
		}

		if ( 'refresh_token' === $grant ) {
			$row = FBCC_Store::find_token( isset( $p['refresh_token'] ) ? (string) $p['refresh_token'] : '', 'refresh' );
			if ( ! $row || $row->client_id !== $client['id'] ) {
				return self::error( 'invalid_grant', 'Refresh token is invalid or expired.' );
			}
			$user = get_userdata( (int) $row->user_id );
			if ( ! $user || ! user_can( $user, 'manage_options' ) ) {
				FBCC_Store::revoke_token( $row->id );
				return self::error( 'invalid_grant', 'The approving administrator no longer has access.' );
			}
			FBCC_Store::revoke_token( $row->id ); // rotation
			return self::token_response( $client['id'], (int) $row->user_id );
		}

		return self::error( 'unsupported_grant_type', 'Use authorization_code or refresh_token.' );
	}

	public static function pkce_ok( $verifier, $challenge ) {
		if ( ! preg_match( '/^[A-Za-z0-9\-._~]{43,128}$/', $verifier ) ) {
			return false;
		}
		$calc = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
		return hash_equals( (string) $challenge, $calc );
	}

	private static function token_response( $client_id, $user_id ) {
		FBCC_Store::trace( 'oauth', array( 'result' => '200 tokens issued' ) );
		$r = new WP_REST_Response( array(
			'access_token'  => FBCC_Store::issue_token( 'access', $client_id, $user_id, self::ACCESS_TTL ),
			'token_type'    => 'Bearer',
			'expires_in'    => self::ACCESS_TTL,
			'refresh_token' => FBCC_Store::issue_token( 'refresh', $client_id, $user_id, self::REFRESH_TTL ),
			'scope'         => 'mcp',
		), 200 );
		$r->header( 'Cache-Control', 'no-store' );
		return $r;
	}

	public static function revoke( WP_REST_Request $req ) {
		$p   = $req->get_body_params();
		$raw = isset( $p['token'] ) ? (string) $p['token'] : '';
		foreach ( array( 'access', 'refresh' ) as $kind ) {
			$row = FBCC_Store::find_token( $raw, $kind );
			if ( $row ) {
				FBCC_Store::revoke_token( $row->id );
			}
		}
		return new WP_REST_Response( null, 200 );
	}

	/* ------------------------------------------------------------ */
	/* Consent screen                                               */
	/* ------------------------------------------------------------ */

	private static function authorize_screen() {
		nocache_headers();
		header( 'X-Frame-Options: DENY' );

		if ( ! is_user_logged_in() ) {
			auth_redirect(); // exits; returns here after login with the same query string
		}

		$src  = 'POST' === $_SERVER['REQUEST_METHOD'] ? $_POST : $_GET; // phpcs:ignore WordPress.Security.NonceVerification
		$args = array();
		foreach ( array( 'response_type', 'client_id', 'redirect_uri', 'code_challenge', 'code_challenge_method', 'state', 'scope', 'resource' ) as $k ) {
			$args[ $k ] = isset( $src[ $k ] ) ? sanitize_text_field( wp_unslash( $src[ $k ] ) ) : '';
		}
		// redirect_uri and state must round-trip exactly, so they are kept raw (always escaped on output).
		$args['redirect_uri'] = isset( $src['redirect_uri'] ) ? (string) wp_unslash( $src['redirect_uri'] ) : '';
		$args['state']        = isset( $src['state'] ) ? substr( (string) wp_unslash( $src['state'] ), 0, 1024 ) : '';

		$client = $args['client_id'] ? FBCC_Store::client( $args['client_id'] ) : null;

		// Errors we must NOT redirect for (unknown client / bad redirect).
		if ( ! $client || ! in_array( $args['redirect_uri'], $client['redirect_uris'], true ) ) {
			self::page( FBCC_I18n::t( 'Connection request not valid' ), '<p>' . esc_html( FBCC_I18n::t( 'This request did not come from a registered client, or its return address does not match. Nothing was shared.' ) ) . '</p>' );
		}

		$back = function ( array $q ) use ( $args ) {
			if ( '' !== $args['state'] ) {
				$q['state'] = $args['state'];
			}
			$q['iss'] = self::issuer();
			wp_redirect( add_query_arg( array_map( 'rawurlencode', $q ), $args['redirect_uri'] ) );
			exit;
		};

		if ( 'code' !== $args['response_type'] ) {
			$back( array( 'error' => 'unsupported_response_type' ) );
		}
		if ( 'S256' !== $args['code_challenge_method'] || strlen( $args['code_challenge'] ) < 43 ) {
			$back( array( 'error' => 'invalid_request', 'error_description' => 'PKCE S256 is required' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			self::page( FBCC_I18n::t( 'Administrator needed' ), '<p>' . esc_html( FBCC_I18n::t( 'Only a site administrator can connect Claude to this site.' ) ) . '</p>' );
		}
		$settings = FBCC_Store::settings();
		if ( empty( $settings['enabled'] ) ) {
			self::page( 'Connector is switched off', '<p>Turn the Claude Connector back on in <a href="' . esc_url( admin_url( 'admin.php?page=fbcc' ) ) . '">Claude Connection</a> first.</p>' );
		}

		if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			check_admin_referer( 'fbcc_authorize_' . $args['client_id'] );
			if ( empty( $_POST['fbcc_allow'] ) ) {
				FBCC_Store::log( 'oauth', 'Connection request from "' . $client['name'] . '" denied', 'blocked' );
				$back( array( 'error' => 'access_denied' ) );
			}
			$code = bin2hex( random_bytes( 32 ) );
			set_transient( 'fbcc_code_' . FBCC_Store::hash( $code ), array(
				'client_id'    => $args['client_id'],
				'redirect_uri' => $args['redirect_uri'],
				'challenge'    => $args['code_challenge'],
				'user_id'      => get_current_user_id(),
			), self::CODE_TTL );
			$client['last_auth'] = time();
			FBCC_Store::save_client( $args['client_id'], $client );
			FBCC_Store::log( 'oauth', 'Connected "' . $client['name'] . '" — approved by ' . wp_get_current_user()->display_name, 'info', '', $args['client_id'] );
			$back( array( 'code' => $code ) );
		}

		$tr     = 'tr' === FBCC_I18n::lang();
		$levels = $tr ? array(
			'read'       => 'Salt okunur — sayfaları, yazıları, eklentileri ve site sağlığını okur. Hiçbir şeyi değiştirmez.',
			'editor'     => 'İçerik editörü — taslakları düzenleyebilir ve sayfa oluşturabilir. Yayınlama onayınızı bekler.',
			'maintainer' => 'Site yöneticisi — eklenti kurulumu ve güncellemesi de isteyebilir. Her biri onayınızı bekler.',
		) : array(
			'read'       => 'Read-only — reads pages, posts, plugins and site health. Changes nothing.',
			'editor'     => 'Content editor — can edit drafts and create pages. Publishing waits for your approval.',
			'maintainer' => 'Site maintainer — can also request plugin installs and updates. Each one waits for your approval.',
		);
		$host   = wp_parse_url( $args['redirect_uri'], PHP_URL_HOST );
		$hidden = '';
		foreach ( $args as $k => $v ) {
			$hidden .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
		}
		$nonce = wp_nonce_field( 'fbcc_authorize_' . $args['client_id'], '_wpnonce', true, false );
		if ( $tr ) {
			$body  = '<p><strong>' . esc_html( $client['name'] ) . '</strong> (dönüş adresi <code>' . esc_html( $host ) . '</code>) <strong>' . esc_html( get_bloginfo( 'name' ) ) . '</strong> sitesine bağlanmak istiyor.</p>';
			$body .= '<ul><li>WordPress kullanıcısı <strong>claude-agent</strong> (Editör rolü) olarak çalışır, asla sizin adınıza değil.</li>';
			$body .= '<li>Şu anki izin seviyesi: <strong>' . esc_html( $levels[ $settings['level'] ] ?? $settings['level'] ) . '</strong></li>';
			$body .= '<li>Her işlem etkinlik kaydına yazılır. Bağlantıyı istediğiniz zaman Claude Bağlantısı ekranından kesebilirsiniz.</li></ul>';
			$body .= '<form method="post" action="' . esc_url( self::authorize_url() ) . '">' . $hidden . $nonce;
			$body .= '<div class="row"><button type="submit" name="fbcc_deny" value="1" class="ghost">Reddet</button><button type="submit" name="fbcc_allow" value="1" class="primary">Claude’a izin ver</button></div></form>';
			self::page( 'Claude bu siteye bağlansın mı?', $body );
		}
		$body  = '<p><strong>' . esc_html( $client['name'] ) . '</strong> (returns to <code>' . esc_html( $host ) . '</code>) wants to connect to <strong>' . esc_html( get_bloginfo( 'name' ) ) . '</strong>.</p>';
		$body .= '<ul><li>It will act as the WordPress user <strong>claude-agent</strong> (Editor role), never as you.</li>';
		$body .= '<li>Current permission level: <strong>' . esc_html( $levels[ $settings['level'] ] ?? $settings['level'] ) . '</strong></li>';
		$body .= '<li>Every action is written to the activity log. You can disconnect at any time from Claude Connection.</li></ul>';
		$body .= '<form method="post" action="' . esc_url( self::authorize_url() ) . '">' . $hidden . $nonce;
		$body .= '<div class="row"><button type="submit" name="fbcc_deny" value="1" class="ghost">Deny</button><button type="submit" name="fbcc_allow" value="1" class="primary">Allow Claude</button></div></form>';
		self::page( 'Connect Claude to this site?', $body );
	}

	private static function page( $title, $html ) {
		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );
		?><!doctype html>
<html lang="<?php echo 'tr' === FBCC_I18n::lang() ? 'tr' : 'en'; ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title><?php echo esc_html( $title ); ?></title>
<style>
body{margin:0;background:#f0f0f1;font:15px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:#1d2327;display:flex;min-height:100vh;align-items:center;justify-content:center;padding:16px;box-sizing:border-box}
.card{background:#fff;border:1px solid #c3c4c7;max-width:520px;width:100%;padding:28px;box-sizing:border-box}
.mark{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#6C5CE7,#FF2D75);color:#fff;font-weight:800;display:flex;align-items:center;justify-content:center;margin-bottom:14px}
h1{font-size:20px;margin:0 0 12px}ul{padding-left:18px}li{margin:6px 0}code{background:#f6f7f7;padding:1px 5px}
.row{display:flex;gap:10px;justify-content:flex-end;margin-top:20px}
button{font:inherit;font-weight:600;padding:10px 18px;min-height:44px;border-radius:4px;cursor:pointer}
.primary{background:#4f3fd0;color:#fff;border:0}.ghost{background:#fff;border:1px solid #c3c4c7;color:#1d2327}
a{color:#4f3fd0}
</style></head><body><main class="card"><div class="mark">FB</div><h1><?php echo esc_html( $title ); ?></h1><?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput ?></main></body></html>
		<?php
		exit;
	}
}
