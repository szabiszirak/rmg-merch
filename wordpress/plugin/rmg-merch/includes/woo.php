<?php
defined('ABSPATH') || exit;

/** A pulóver WooCommerce-terméke (változatos: Szín × Méret). Ha még nincs, létrehozza. */
function rmg_product_id(bool $create = true): int {
	$id = (int) get_option('rmg_product_id', 0);
	if ($id && get_post_status($id) && function_exists('wc_get_product') && ($p = wc_get_product($id)) && $p->is_type('variable')) return $id;
	if (!$create || !class_exists('WC_Product_Variable')) return 0;
	return rmg_create_product();
}

function rmg_create_product(int $price = 10000): int {
	$p = new WC_Product_Variable();
	$p->set_name('RMG kapucnis pulóver');
	$p->set_slug('rmg-kapucnis-pulover');
	$p->set_status('publish');
	$p->set_catalog_visibility('hidden');
	$p->set_description('Kapucnis, kenguruzsebes pulóver. Elöl kis RMG-pajzs a mellkason, hátul nagy RMG-minta (RMG, SZEGED, 18/98, RADNÓTI MIKLÓS GIMNÁZIUM).');
	$attrs = [];
	foreach (['Szín' => RMG_COLORS, 'Méret' => RMG_SIZES] as $label => $opts) {
		$a = new WC_Product_Attribute();
		$a->set_name($label);
		$a->set_options($opts);
		$a->set_visible(true);
		$a->set_variation(true);
		$attrs[] = $a;
	}
	$p->set_attributes($attrs);
	$pid = $p->save();
	foreach (RMG_COLORS as $c) foreach (RMG_SIZES as $s) {
		$v = new WC_Product_Variation();
		$v->set_parent_id($pid);
		$v->set_attributes([sanitize_title('Szín') => $c, sanitize_title('Méret') => $s]);
		$v->set_regular_price((string) $price);
		$v->set_virtual(true);
		$v->set_manage_stock(false);
		$v->set_stock_status('instock');
		$v->set_sku('RMG-' . ($c === 'Fekete' ? 'B' : 'W') . '-' . $s);
		$v->save();
	}
	WC_Product_Variable::sync($pid);
	update_option('rmg_product_id', $pid);
	return $pid;
}

/** szín|méret → WC_Product_Variation */
function rmg_variations(): array {
	$pid = rmg_product_id();
	$out = [];
	if (!$pid) return $out;
	$p = wc_get_product($pid);
	foreach ($p->get_children() as $vid) {
		$v = wc_get_product($vid);
		if (!$v) continue;
		$a = $v->get_attributes();
		$c = $a[sanitize_title('Szín')] ?? ''; $s = $a[sanitize_title('Méret')] ?? '';
		if ($c && $s) $out[$c . '|' . $s] = $v;
	}
	return $out;
}

/** Egységár forintban (a legalacsonyabb változatár; alapértelmezés 10 000). */
function rmg_price(): int {
	$pid = rmg_product_id(false);
	if ($pid && ($p = wc_get_product($pid))) {
		$min = (float) $p->get_variation_price('min');
		if ($min > 0) return (int) round($min);
	}
	return 10000;
}

function rmg_validate_items($items): array {
	if (!is_array($items) || !$items || count($items) > 20) throw new RMG_Error('Hibás rendelés.');
	$q = [];
	foreach ($items as $i) {
		$c = $i['color'] ?? ''; $s = $i['size'] ?? ''; $n = $i['qty'] ?? 0;
		if (!in_array($c, RMG_COLORS, true) || !in_array($s, RMG_SIZES, true) || !is_int($n) || $n < 1 || $n > RMG_MAX_PER_LINE) throw new RMG_Error('Hibás tétel.');
		$q[$c . '|' . $s] = ($q[$c . '|' . $s] ?? 0) + $n;
	}
	if (max($q) > RMG_MAX_PER_LINE || array_sum($q) > RMG_MAX_PER_ORDER) throw new RMG_Error('Túl sok pulóver egy rendelésben.');
	return $q;
}

/** Rendelés létrehozása WooCommerce-ben. Ugyanazzal a tokennel nem jön létre kétszer. */
function rmg_create_order(WP_User $user, array $body): array {
	if (!rmg_is_open()) throw new RMG_Error('A rendelési időszak nincs nyitva.', 403);
	$profile = rmg_profile($user->ID);
	if ($profile['schoolClass'] === '' || mb_strlen($profile['name']) < 3) throw new RMG_Error('Előbb add meg a Fiókban, hova jársz.');
	if (($body['consent'] ?? false) !== true) throw new RMG_Error('Fogadd el az adatkezelési tájékoztatót.');
	$token = strtolower((string) ($body['token'] ?? ''));
	if (!preg_match('/^[-a-f0-9]{36}$/', $token)) throw new RMG_Error('Hibás rendelés.');
	$q = rmg_validate_items($body['items'] ?? null);

	$lock = 'rmg_order_' . md5($token);
	if (get_transient($lock)) throw new RMG_Error('A rendelés feldolgozása folyamatban van, várj egy pillanatot.', 409);
	set_transient($lock, 1, 30);
	try {
		$existing = wc_get_orders(['limit' => 1, 'meta_key' => '_rmg_token', 'meta_value' => $token, 'return' => 'objects']);
		if ($existing) {
			$o = $existing[0];
			if ((int) $o->get_customer_id() !== $user->ID) throw new RMG_Error('Hibás rendelés.');
			return ['id' => rmg_order_label($o), 'total' => (int) round((float) $o->get_total())];
		}
		$vars = rmg_variations();
		$order = wc_create_order(['customer_id' => $user->ID, 'created_via' => 'rmg-merch']);
		if (is_wp_error($order)) throw new RMG_Error('A rendelés mentése nem sikerült.', 500);
		foreach ($q as $key => $n) {
			if (empty($vars[$key])) throw new RMG_Error('A termék nincs beállítva. Szólj a szervezőnek.', 500);
			$order->add_product($vars[$key], $n);
		}
		[$last, $first] = rmg_split_name($profile['name']);
		$order->set_billing_first_name($first);
		$order->set_billing_last_name($last);
		$order->set_billing_email($user->user_email);
		$order->set_payment_method('cod');
		$order->set_payment_method_title('Készpénz az iskolában');
		$order->update_meta_data('_rmg_token', $token);
		$order->update_meta_data('_rmg_class', $profile['schoolClass']);
		$order->update_meta_data('_rmg_name', $profile['name']);
		$order->update_meta_data('_rmg_paid', 0);
		$order->update_meta_data('_rmg_collected', 0);
		$order->calculate_totals(false);
		$order->set_status('on-hold', 'Készpénzes fizetés az iskolában.');
		$order->save();
		return ['id' => rmg_order_label($order), 'total' => (int) round((float) $order->get_total())];
	} finally {
		delete_transient($lock);
	}
}

function rmg_order_label(WC_Order $o): string { return 'RMG-' . str_pad((string) $o->get_order_number(), 4, '0', STR_PAD_LEFT); }

function rmg_order_from_label(string $label): ?WC_Order {
	if (!preg_match('/^RMG-0*(\d+)$/', $label, $m)) return null;
	$o = wc_get_order((int) $m[1]);
	return $o instanceof WC_Order ? $o : null;
}

/** Rendelés a felület formátumában. */
function rmg_order_row(WC_Order $o): array {
	$items = [];
	foreach ($o->get_items() as $it) {
		$v = $it->get_product();
		$a = $v ? $v->get_attributes() : [];
		$items[] = ['color' => $a[sanitize_title('Szín')] ?? $it->get_meta(sanitize_title('Szín')), 'size' => $a[sanitize_title('Méret')] ?? $it->get_meta(sanitize_title('Méret')), 'qty' => (int) $it->get_quantity()];
	}
	$created = $o->get_date_created();
	return [
		'id' => rmg_order_label($o),
		'name' => (string) ($o->get_meta('_rmg_name') ?: trim($o->get_billing_last_name() . ' ' . $o->get_billing_first_name())),
		'email' => $o->get_billing_email(),
		'schoolClass' => (string) $o->get_meta('_rmg_class'),
		'items' => wp_json_encode($items, JSON_UNESCAPED_UNICODE),
		'total' => (int) round((float) $o->get_total()),
		'paid' => (int) $o->get_meta('_rmg_paid'),
		'collected' => (int) $o->get_meta('_rmg_collected'),
		'status' => $o->get_status(),
		'createdAt' => $created ? $created->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.000\Z') : gmdate('Y-m-d\TH:i:s.000\Z'),
	];
}

function rmg_orders(array $args = []): array {
	$orders = wc_get_orders(array_merge(['limit' => -1, 'orderby' => 'date', 'order' => 'DESC', 'type' => 'shop_order', 'status' => array_keys(wc_get_order_statuses())], $args));
	return array_values(array_map('rmg_order_row', array_filter($orders, fn($o) => $o instanceof WC_Order && $o->get_status() !== 'trash')));
}

/** Fizetve / Átadva jelölés, a WooCommerce-állapot szinkronizálásával. */
function rmg_set_flag(WC_Order $o, string $field, bool $value): void {
	if (!in_array($field, ['paid', 'collected'], true)) throw new RMG_Error('Hibás állapot.');
	$o->update_meta_data('_rmg_' . $field, $value ? 1 : 0);
	$paid = (int) $o->get_meta('_rmg_paid'); $coll = (int) $o->get_meta('_rmg_collected');
	if (!in_array($o->get_status(), ['cancelled', 'refunded', 'failed'], true)) {
		$target = $paid && $coll ? 'completed' : ($paid ? 'processing' : 'on-hold');
		if ($o->get_status() !== $target) {
			$note = $field === 'paid' ? ($value ? 'Fizetve (szervező jelölte).' : 'Fizetés visszavonva.') : ($value ? 'Átadva (szervező jelölte).' : 'Átadás visszavonva.');
			$o->set_status($target, $note);
		}
	}
	$o->save();
}

/** Ha az adminban kézzel állítják az állapotot, a jelölők kövessék. */
add_action('woocommerce_order_status_changed', function ($id, $from, $to) {
	$o = wc_get_order($id);
	if (!$o || $o->get_created_via() !== 'rmg-merch') return;
	if ($to === 'completed') { $o->update_meta_data('_rmg_paid', 1); $o->update_meta_data('_rmg_collected', 1); $o->save_meta_data(); }
	elseif ($to === 'processing' && !(int) $o->get_meta('_rmg_paid')) { $o->update_meta_data('_rmg_paid', 1); $o->save_meta_data(); }
}, 10, 3);

/** WooCommerce rendeléslista: Osztály oszlop (HPOS és régi tárolás). */
foreach (['manage_woocommerce_page_wc-orders_columns', 'manage_edit-shop_order_columns'] as $hook) {
	add_filter($hook, function ($cols) {
		$out = [];
		foreach ($cols as $k => $v) { $out[$k] = $v; if ($k === 'order_number') $out['rmg_class'] = 'Osztály'; }
		return $out;
	});
}
add_action('manage_woocommerce_page_wc-orders_custom_column', function ($col, $order) { if ($col === 'rmg_class') echo esc_html((string) $order->get_meta('_rmg_class')); }, 10, 2);
add_action('manage_shop_order_posts_custom_column', function ($col, $id) { if ($col === 'rmg_class' && ($o = wc_get_order($id))) echo esc_html((string) $o->get_meta('_rmg_class')); }, 10, 2);
add_action('woocommerce_admin_order_data_after_billing_address', function ($o) {
	$c = $o->get_meta('_rmg_class');
	if ($c) echo '<p><strong>Osztály:</strong> ' . esc_html($c) . '</p>';
});
