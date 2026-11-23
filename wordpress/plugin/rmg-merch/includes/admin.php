<?php
defined('ABSPATH') || exit;

/** Asset URL + verzió (fájlidő) a gyorsítótár frissítéséhez. */
function rmg_asset(string $file): array {
	$path = RMG_DIR . '/assets/' . $file;
	return [plugins_url('assets/' . $file, RMG_FILE), file_exists($path) ? (string) filemtime($path) : RMG_VERSION];
}

/** A React felület betöltése ('storefront' vagy 'admin'). */
function rmg_enqueue_app(string $entry): void {
	[$css, $vc] = rmg_asset('app/app.css');
	[$js, $vj] = rmg_asset('app/' . $entry . '.js');
	wp_enqueue_style('rmg-app', $css, [], $vc);
	wp_enqueue_script('rmg-' . $entry, $js, [], $vj, ['in_footer' => true]);
	$cfg = rmg_frontend_config() + ['assets' => plugins_url('assets/img/', RMG_FILE), 'ajax' => admin_url('admin-ajax.php'), 'adminUrl' => admin_url('admin.php?page=wc-orders'), 'loginUrl' => wp_login_url(home_url('/szervezo/'))];
	wp_add_inline_script('rmg-' . $entry, 'window.RMG=' . wp_json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';', 'before');
}
add_filter('script_loader_tag', function ($tag, $handle) { return in_array($handle, ['rmg-admin', 'rmg-storefront'], true) ? str_replace(' src=', ' type="module" src=', $tag) : $tag; }, 10, 2);

/** Szervezői felület: /szervezo/ */
add_action('init', function () { add_rewrite_rule('^szervezo/?$', 'index.php?rmg_page=szervezo', 'top'); });
add_filter('query_vars', function ($v) { $v[] = 'rmg_page'; return $v; });
add_filter('template_include', function ($t) {
	if (get_query_var('rmg_page') !== 'szervezo') return $t;
	nocache_headers();
	header('X-Robots-Tag: noindex, nofollow', true);
	rmg_enqueue_app('admin');
	return RMG_DIR . '/templates/szervezo.php';
});
add_filter('document_title_parts', function ($p) { if (get_query_var('rmg_page') === 'szervezo') $p['title'] = 'Szervezői felület'; return $p; });

add_action('admin_menu', function () {
	add_menu_page('RMG Merch', 'RMG Merch', 'manage_woocommerce', 'rmg-merch', 'rmg_settings_page', 'dashicons-store', 56);
	add_submenu_page('rmg-merch', 'RMG Merch beállítások', 'Beállítások', 'manage_woocommerce', 'rmg-merch', 'rmg_settings_page');
	add_submenu_page('rmg-merch', 'Szervezői felület', 'Rendelések és összesítők ↗', 'manage_woocommerce', 'rmg-merch-open', '__return_null');
});
add_action('admin_init', function () {
	if (($_GET['page'] ?? '') === 'rmg-merch-open' && current_user_can('manage_woocommerce')) { wp_safe_redirect(home_url('/szervezo/')); exit; }
});

function rmg_settings_page() {
	if (!current_user_can('manage_woocommerce')) return;
	if (isset($_POST['rmg_settings']) && check_admin_referer('rmg_settings') && current_user_can('manage_options')) {
		update_option('rmg_google_client_id', sanitize_text_field(wp_unslash($_POST['rmg_google_client_id'] ?? '')));
		$mail = sanitize_email(wp_unslash($_POST['rmg_contact_email'] ?? ''));
		if ($mail) update_option('rmg_contact_email', $mail);
		echo '<div class="notice notice-success"><p>Mentve.</p></div>';
	}
	$pid = rmg_product_id(false);
	$const = defined('RMG_GOOGLE_CLIENT_ID') && RMG_GOOGLE_CLIENT_ID;
	$c = rmg_campaign();
	?>
	<div class="wrap"><h1>RMG Merch</h1>
	<p><a class="button button-primary" href="<?php echo esc_url(home_url('/szervezo/')); ?>">Rendelések, összesítők és rendelési időszak megnyitása ↗</a></p>
	<p>Rendelési időszak: <strong><?php echo $c ? esc_html(wp_date('Y. m. d. H:i', strtotime($c['start'])) . ' – ' . wp_date('Y. m. d. H:i', strtotime($c['end']))) . (rmg_is_open() ? ' (nyitva)' : ' (zárva)') : 'nincs beállítva'; ?></strong></p>
	<?php if (current_user_can('manage_options')): ?>
	<form method="post"><?php wp_nonce_field('rmg_settings'); ?><input type="hidden" name="rmg_settings" value="1">
	<table class="form-table">
	<tr><th><label for="rmg_google_client_id">Google OAuth kliensazonosító</label></th><td>
	<input class="regular-text code" id="rmg_google_client_id" name="rmg_google_client_id" value="<?php echo esc_attr((string) get_option('rmg_google_client_id', '')); ?>" <?php disabled($const); ?>>
	<p class="description">„Webes alkalmazás” típusú kliens a Google Cloud Console-ban, engedélyezett JavaScript-forrás: <code><?php $u = wp_parse_url(home_url()); echo esc_html($u['scheme'] . '://' . $u['host'] . (isset($u['port']) ? ':' . $u['port'] : '')); ?></code>. Titkos kulcs nem kell.<?php if ($const) echo ' A wp-config.php-ban beállított érték van használatban.'; ?></p></td></tr>
	<tr><th><label for="rmg_contact_email">Kapcsolati e-mail</label></th><td><input class="regular-text" type="email" id="rmg_contact_email" name="rmg_contact_email" value="<?php echo esc_attr(rmg_contact_email()); ?>"></td></tr>
	<tr><th>Pulóver és ár</th><td><?php if ($pid): ?><a class="button" href="<?php echo esc_url(get_edit_post_link($pid)); ?>">Termék szerkesztése (most: <?php echo esc_html(number_format(rmg_price(), 0, ',', ' ')); ?> Ft)</a><p class="description">Az árat a termék változatainál lehet átírni; az oldal automatikusan átveszi.</p><?php else: ?>A termék a WooCommerce aktiválása után automatikusan létrejön.<?php endif; ?></td></tr>
	</table><?php submit_button('Mentés'); ?></form>
	<?php endif; ?></div>
	<?php
}
