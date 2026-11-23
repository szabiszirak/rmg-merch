<?php
defined('ABSPATH') || exit;

/** A Google-fiókhoz tartozó WordPress-felhasználó megkeresése vagy létrehozása. */
function rmg_user_from_google(array $g): WP_User {
	$sub = (string) $g['sub'];
	$email = strtolower((string) $g['email']);
	$found = get_users(['meta_key' => 'rmg_google_sub', 'meta_value' => $sub, 'number' => 1, 'count_total' => false]);
	$user = $found[0] ?? null;
	if (!$user) {
		$user = get_user_by('email', $email) ?: null;
		if ($user) update_user_meta($user->ID, 'rmg_google_sub', $sub);
	}
	if (!$user) {
		$base = sanitize_user(strstr($email, '@', true) ?: 'vasarlo', true) ?: 'vasarlo';
		$login = $base; $i = 1;
		while (username_exists($login)) $login = $base . (++$i);
		$id = wp_insert_user([
			'user_login' => $login,
			'user_email' => $email,
			'user_pass' => wp_generate_password(32, true, true),
			'display_name' => sanitize_text_field($g['name'] ?? $login),
			'first_name' => sanitize_text_field($g['given_name'] ?? ''),
			'last_name' => sanitize_text_field($g['family_name'] ?? ''),
			'role' => get_role('customer') ? 'customer' : 'subscriber',
		]);
		if (is_wp_error($id)) throw new RMG_Error('A fiók létrehozása nem sikerült.', 500);
		update_user_meta($id, 'rmg_google_sub', $sub);
		$user = get_user_by('id', $id);
	}
	if (!empty($g['name']) && !get_user_meta($user->ID, 'rmg_name', true)) update_user_meta($user->ID, 'rmg_google_name', sanitize_text_field($g['name']));
	return $user;
}

/** Bejelentkeztetés úgy, hogy ugyanebben a kérésben már érvényes REST-nonce készülhessen. */
function rmg_login_user(WP_User $user): void {
	$capture = function ($cookie) { $_COOKIE[LOGGED_IN_COOKIE] = $cookie; };
	add_action('set_logged_in_cookie', $capture);
	wp_set_auth_cookie($user->ID, true, is_ssl());
	remove_action('set_logged_in_cookie', $capture);
	wp_set_current_user($user->ID);
	do_action('wp_login', $user->user_login, $user);
}

function rmg_profile(int $uid): array {
	$name = (string) get_user_meta($uid, 'rmg_name', true);
	$class = (string) get_user_meta($uid, 'rmg_class', true);
	if (!in_array($class, RMG_CLASSES, true)) $class = '';
	if ($name === '') {
		$u = get_userdata($uid);
		$name = (string) (get_user_meta($uid, 'rmg_google_name', true) ?: ($u ? $u->display_name : ''));
	}
	return ['name' => $name, 'schoolClass' => $class];
}

/** A felület által várt munkamenet-adat, vagy null. */
function rmg_session_user(): ?array {
	$u = wp_get_current_user();
	if (!$u || !$u->ID) return null;
	$p = rmg_profile($u->ID);
	return ['email' => $u->user_email, 'name' => $p['name'], 'schoolClass' => $p['schoolClass'], 'admin' => current_user_can('manage_woocommerce')];
}

function rmg_save_profile(int $uid, $name, $class): void {
	$name = trim(preg_replace('/\s+/u', ' ', (string) $name));
	$len = mb_strlen($name);
	if ($len < 3 || $len > 100) throw new RMG_Error('Add meg a teljes neved.');
	if (!in_array($class, RMG_CLASSES, true)) throw new RMG_Error('Válaszd ki, hova jársz.');
	update_user_meta($uid, 'rmg_name', sanitize_text_field($name));
	update_user_meta($uid, 'rmg_class', $class);
	[$last, $first] = rmg_split_name($name);
	wp_update_user(['ID' => $uid, 'display_name' => $name, 'first_name' => $first, 'last_name' => $last]);
	update_user_meta($uid, 'billing_first_name', $first);
	update_user_meta($uid, 'billing_last_name', $last);
}

/** Magyar névsorrend: „Kovács Anna” → ['Kovács', 'Anna']. */
function rmg_split_name(string $name): array {
	$parts = preg_split('/\s+/u', trim($name));
	if (count($parts) < 2) return [$name, ''];
	$last = array_shift($parts);
	return [$last, implode(' ', $parts)];
}

/** Profil oszlop a WP felhasználólistában. */
add_filter('manage_users_columns', function ($c) { $c['rmg_class'] = 'Osztály'; return $c; });
add_filter('manage_users_custom_column', function ($v, $col, $uid) { return $col === 'rmg_class' ? esc_html((string) get_user_meta($uid, 'rmg_class', true)) : $v; }, 10, 3);
