<?php
/**
 * Plugin Name: FB AI Engine – Claude Connector
 * Description: Lets Claude connect to this site as a remote MCP connector and work on it on your behalf — with permission levels, an approval queue, an activity log, the Online Lab write lock and a kill switch. Companion to FB Software AI Engine.
 * Version: 1.2.2
 * Author: FB Software Solutions
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Plugin URI: https://github.com/fatihborasoftware-sudo/fb-claude-connector
 * Text Domain: fb-claude-connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FBCC_VERSION', '1.2.2' );
define( 'FBCC_FILE', __FILE__ );
define( 'FBCC_DIR', plugin_dir_path( __FILE__ ) );
define( 'FBCC_URL', plugin_dir_url( __FILE__ ) );
define( 'FBCC_NS', 'fbsa/v1' );

require_once FBCC_DIR . 'includes/class-fbcc-store.php';
require_once FBCC_DIR . 'includes/class-fbcc-oauth.php';
require_once FBCC_DIR . 'includes/class-fbcc-tools.php';
require_once FBCC_DIR . 'includes/class-fbcc-gateway.php';
require_once FBCC_DIR . 'includes/class-fbcc-mcp.php';
require_once FBCC_DIR . 'includes/class-fbcc-admin.php';
require_once FBCC_DIR . 'includes/class-fbcc-mindmap.php';
require_once FBCC_DIR . 'includes/class-fbcc-sitetools.php';
require_once FBCC_DIR . 'includes/class-fbcc-live.php';
require_once FBCC_DIR . 'includes/class-fbcc-plan.php';
require_once FBCC_DIR . 'includes/class-fbcc-theme.php';
require_once FBCC_DIR . 'includes/class-fbcc-buildtools.php';
require_once FBCC_DIR . 'includes/class-fbcc-browser.php';
require_once FBCC_DIR . 'includes/class-fbcc-sitecheck.php';
require_once FBCC_DIR . 'includes/class-fbcc-i18n.php';

register_activation_hook( __FILE__, array( 'FBCC_Store', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'FBCC_Store', 'htaccess_remove' ) );

add_action( 'plugins_loaded', function () {
	FBCC_Store::maybe_upgrade();
	FBCC_OAuth::init();
	FBCC_MCP::init();
	FBCC_Admin::init();
	FBCC_MindMap::init();
	FBCC_SiteTools::init();
	FBCC_Live::init();
	FBCC_Plan::init();
	FBCC_Theme::init();
	FBCC_BuildTools::init();
	FBCC_Browser::init();
	FBCC_SiteCheck::init();
	FBCC_I18n::init();
} );
