<?php
defined('ABSPATH') || exit;

/** A React felületnek átadott beállítások (window.RMG). */
function rmg_frontend_config(): array {
	return [
		'rest' => esc_url_raw(rest_url('rmg/v1/')),
		'nonce' => wp_create_nonce('wp_rest'),
		'googleClientId' => rmg_google_client_id(),
		'price' => rmg_price(),
		'contactEmail' => rmg_contact_email(),
		'privacyUrl' => get_privacy_policy_url() ?: '',
		'home' => home_url('/'),
	];
}

/** A WooCommerce saját bolt-oldalai helyett a főoldali rendelőfelület működik; a fizetési (order-pay) és köszönő oldal marad. */
add_action('template_redirect', function () {
	if (!function_exists('is_woocommerce') || is_admin()) return;
	if (function_exists('is_checkout_pay_page') && (is_checkout_pay_page() || is_order_received_page())) return;
	if (is_shop() || is_product() || is_product_category() || is_product_tag() || is_cart() || is_checkout() || is_account_page()) {
		wp_safe_redirect(home_url('/'), 302);
		exit;
	}
});

/** A felhasználók és termékek ne kerüljenek a WordPress-oldaltérképbe. */
add_filter('wp_sitemaps_add_provider', function ($provider, $name) { return $name === 'users' ? false : $provider; }, 10, 2);
add_filter('wp_sitemaps_post_types', function ($types) { unset($types['product']); return $types; });

/** Vásárlók ne lássák a WP adminsávot, és ne a WP adminba kerüljenek. */
add_filter('show_admin_bar', function ($show) { return current_user_can('edit_posts') || current_user_can('manage_woocommerce') ? $show : false; });

/** Alapbeállítások a bővítmény első betöltésekor (WooCommerce szükséges). */
add_action('admin_init', function () {
	if (!class_exists('WooCommerce') || get_option('rmg_setup_done')) return;
	update_option('woocommerce_currency', 'HUF');
	update_option('woocommerce_price_num_decimals', 0);
	update_option('woocommerce_price_thousand_sep', ' ');
	update_option('woocommerce_currency_pos', 'right_space');
	update_option('woocommerce_default_country', 'HU');
	update_option('woocommerce_calc_taxes', 'no');
	update_option('woocommerce_enable_guest_checkout', 'no');
	update_option('woocommerce_coming_soon', 'no');
	$cod = (array) get_option('woocommerce_cod_settings', []);
	update_option('woocommerce_cod_settings', array_merge($cod, ['enabled' => 'yes', 'title' => 'Készpénz az iskolában', 'description' => 'A pulóver árát készpénzben, az iskolában fizeted.', 'instructions' => 'A pulóver árát készpénzben, az iskolában fizeted az átvételkor vagy a szervezőnél.']));
	rmg_product_id();
	update_option('rmg_setup_done', 1);
});
