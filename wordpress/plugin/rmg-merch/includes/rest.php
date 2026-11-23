<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function () {
	$ns = 'rmg/v1';
	$pub = '__return_true';
	$auth = fn() => is_user_logged_in();
	$admin = fn() => current_user_can('manage_woocommerce');
	register_rest_route($ns, '/session', [
		['methods' => 'GET', 'callback' => rmg_rest(fn() => rmg_session_response()), 'permission_callback' => $pub],
		['methods' => 'POST', 'callback' => rmg_rest('rmg_rest_login'), 'permission_callback' => $pub],
		['methods' => 'DELETE', 'callback' => rmg_rest('rmg_rest_logout'), 'permission_callback' => $pub],
	]);
	register_rest_route($ns, '/profile', ['methods' => 'PUT', 'callback' => rmg_rest(function (WP_REST_Request $r) {
		rmg_save_profile(get_current_user_id(), $r['name'] ?? '', $r['schoolClass'] ?? '');
		return rmg_session_user();
	}), 'permission_callback' => $auth]);
	register_rest_route($ns, '/campaign', [
		['methods' => 'GET', 'callback' => rmg_rest(fn() => rmg_campaign()), 'permission_callback' => $pub],
		['methods' => 'POST', 'callback' => rmg_rest('rmg_rest_save_campaign'), 'permission_callback' => $admin],
	]);
	register_rest_route($ns, '/my-orders', ['methods' => 'GET', 'callback' => rmg_rest(fn() => rmg_orders(['customer_id' => get_current_user_id()])), 'permission_callback' => $auth]);
	register_rest_route($ns, '/orders', [
		['methods' => 'GET', 'callback' => rmg_rest(fn() => rmg_orders()), 'permission_callback' => $admin],
		['methods' => 'POST', 'callback' => rmg_rest(function (WP_REST_Request $r) {
			return new WP_REST_Response(rmg_create_order(wp_get_current_user(), (array) $r->get_json_params()), 201);
		}), 'permission_callback' => $auth],
		['methods' => 'PATCH', 'callback' => rmg_rest(function (WP_REST_Request $r) {
			$o = rmg_order_from_label((string) ($r['id'] ?? ''));
			if (!$o) throw new RMG_Error('Nincs ilyen rendelés.', 404);
			if (!is_bool($r['value'])) throw new RMG_Error('Hibás állapot.');
			rmg_set_flag($o, (string) $r['field'], $r['value']);
			return ['ok' => true];
		}), 'permission_callback' => $admin],
	]);
});

/** Hibakezelő burkoló: RMG_Error → JSON {code, message}, megfelelő státusszal. */
function rmg_rest(callable $fn): callable {
	return function (WP_REST_Request $r) use ($fn) {
		try {
			$out = $fn($r);
			return $out instanceof WP_REST_Response ? $out : new WP_REST_Response($out, 200);
		} catch (RMG_Error $e) {
			return new WP_Error('rmg_error', $e->getMessage(), ['status' => $e->status]);
		} catch (InvalidArgumentException $e) {
			return new WP_Error('rmg_error', $e->getMessage(), ['status' => 400]);
		} catch (Throwable $e) {
			if (defined('WP_DEBUG') && WP_DEBUG) error_log('[rmg] ' . $e);
			return new WP_Error('rmg_error', 'Váratlan hiba történt. Próbáld újra.', ['status' => 500]);
		}
	};
}

function rmg_session_response(): array { return ['user' => rmg_session_user(), 'nonce' => wp_create_nonce('wp_rest')]; }

function rmg_rest_login(WP_REST_Request $r): array {
	$cred = (string) ($r['credential'] ?? '');
	if ($cred === '') throw new RMG_Error('Hiányzó belépési adat.', 400);
	$g = rmg_verify_google_id_token($cred);
	$user = rmg_user_from_google($g);
	rmg_login_user($user);
	return rmg_session_response();
}

function rmg_rest_logout(): array {
	if (is_user_logged_in()) wp_logout();
	wp_set_current_user(0);
	unset($_COOKIE[LOGGED_IN_COOKIE]);
	return rmg_session_response();
}

function rmg_rest_save_campaign(WP_REST_Request $r): array {
	$start = rmg_budapest_to_iso((string) $r['start']);
	$end = rmg_budapest_to_iso((string) $r['end']);
	if (strtotime($end) <= strtotime($start)) throw new RMG_Error('A zárásnak a kezdés után kell lennie.');
	$pickup = sanitize_textarea_field((string) ($r['pickup'] ?? ''));
	if (mb_strlen($pickup) > 500) throw new RMG_Error('Az átvételi tájékoztató legfeljebb 500 karakter lehet.');
	update_option('rmg_campaign', ['start' => $start, 'end' => $end, 'pickup' => $pickup], false);
	return ['ok' => true];
}
