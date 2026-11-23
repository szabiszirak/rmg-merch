<?php
/**
 * Plugin Name: RMG Merch
 * Description: A Szegedi Radnóti pulóverrendelése: Google-belépés, „Hova jársz?” profil, rendelési időszak, WooCommerce-rendelések, osztályonkénti és gyártási összesítő.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Requires Plugins: woocommerce
 * Author: RMG Merch
 * Text Domain: rmg-merch
 */
defined('ABSPATH') || exit;
define('RMG_VERSION', '1.0.0');
define('RMG_FILE', __FILE__);
define('RMG_DIR', __DIR__);
require_once RMG_DIR . '/includes/config.php';
require_once RMG_DIR . '/includes/google.php';
require_once RMG_DIR . '/includes/users.php';
require_once RMG_DIR . '/includes/woo.php';
require_once RMG_DIR . '/includes/rest.php';
require_once RMG_DIR . '/includes/admin.php';
require_once RMG_DIR . '/includes/site.php';
register_activation_hook(__FILE__, 'rmg_activate');
function rmg_activate() {
	if (!get_option('rmg_campaign')) add_option('rmg_campaign', null);
	add_option('rmg_contact_email', 'radnotimerch@gmail.com');
}
