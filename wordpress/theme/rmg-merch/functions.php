<?php
defined('ABSPATH') || exit;

add_action('after_setup_theme', function () {
	add_theme_support('title-tag');
	add_theme_support('html5', ['style', 'script', 'navigation-widgets']);
	add_theme_support('woocommerce');
});

const RMG_THEME_DESC = 'Fekete és fehér kapucnis pulóver RMG-emblémával a Szegedi Radnóti Miklós Kísérleti Gimnázium diákjainak és tanárainak. S–XXL méret, átvétel az iskolában.';

add_filter('document_title_parts', function ($p) {
	if (is_front_page()) return ['title' => 'RMG Merch – a Szegedi Radnóti pulóverei'];
	$p['site'] = 'RMG Merch';
	unset($p['tagline']);
	return $p;
});
add_filter('document_title_separator', fn() => '–');

add_action('wp_enqueue_scripts', function () {
	if (!function_exists('rmg_enqueue_app')) return;
	if (get_query_var('rmg_page')) return;
	if (is_front_page()) rmg_enqueue_app('storefront');
	else { [$css, $v] = rmg_asset('app/app.css'); wp_enqueue_style('rmg-app', $css, [], $v); }
	wp_enqueue_style('rmg-theme', get_stylesheet_directory_uri() . '/theme.css', ['rmg-app'], (string) filemtime(__DIR__ . '/theme.css'));
	wp_dequeue_style('wp-block-library');
	wp_dequeue_style('global-styles');
	wp_dequeue_style('classic-theme-styles');
}, 20);

/** Fölösleges WordPress-fejlécelemek eltávolítása. */
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');
remove_action('wp_head', 'wp_generator');
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'rsd_link');

/** Ikonok, leírás, megosztási adatok, strukturált adat. */
add_action('wp_head', function () {
	$img = function_exists('rmg_asset') ? plugins_url('assets/img/', RMG_FILE) : '';
	$home = home_url('/');
	echo '<meta name="theme-color" content="#171918">' . "\n";
	if ($img) {
		echo '<link rel="icon" href="' . esc_url($img . 'favicon.svg') . '" type="image/svg+xml">' . "\n";
		echo '<link rel="icon" href="' . esc_url($img . 'favicon-32.png') . '" sizes="32x32" type="image/png">' . "\n";
		echo '<link rel="apple-touch-icon" href="' . esc_url($img . 'apple-touch-icon.png') . '">' . "\n";
	}
	if (!is_front_page() || get_query_var('rmg_page')) return;
	$t = 'RMG Merch – a Szegedi Radnóti pulóverei';
	$price = function_exists('rmg_price') ? rmg_price() : 10000;
	printf('<meta name="description" content="%s">' . "\n", esc_attr(RMG_THEME_DESC));
	printf('<link rel="canonical" href="%s">' . "\n", esc_url($home));
	foreach (['og:type' => 'website', 'og:site_name' => 'RMG Merch', 'og:locale' => 'hu_HU', 'og:url' => $home, 'og:title' => $t, 'og:description' => 'Kapucnis pulóver feketében és fehérben, RMG-emblémával. S–XXL, ' . number_format($price, 0, ',', ' ') . ' Ft.', 'og:image' => $img . 'og-image.jpg', 'og:image:width' => '1200', 'og:image:height' => '630', 'og:image:alt' => 'RMG Merch – fekete és fehér RMG kapucnis pulóver'] as $k => $v)
		printf('<meta property="%s" content="%s">' . "\n", esc_attr($k), esc_attr($v));
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	$ld = [
		['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => 'RMG Merch', 'alternateName' => ['Radnóti Merch', 'RMG pulóver'], 'url' => $home, 'inLanguage' => 'hu'],
		['@context' => 'https://schema.org', '@type' => 'Product', 'name' => 'RMG kapucnis pulóver', 'description' => 'Fekete vagy fehér kapucnis pulóver kenguruzsebbel, elöl kis RMG-pajzzsal, hátul nagy RMG-mintával. A Szegedi Radnóti Miklós Kísérleti Gimnázium pulóvere.', 'image' => [$img . 'black-back-v2.png', $img . 'white-front-v2.png', $img . 'black-front-v3.png', $img . 'white-back-v2.png'], 'brand' => ['@type' => 'Brand', 'name' => 'RMG Merch'], 'color' => 'fekete, fehér', 'size' => ['S', 'M', 'L', 'XL', 'XXL'],
			'offers' => ['@type' => 'Offer', 'url' => $home, 'priceCurrency' => 'HUF', 'price' => (string) $price, 'availability' => (function_exists('rmg_is_open') && rmg_is_open()) ? 'https://schema.org/InStock' : 'https://schema.org/PreOrder', 'itemCondition' => 'https://schema.org/NewCondition']],
	];
	echo '<script type="application/ld+json">' . wp_json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
}, 2);

/** robots.txt: a szervezői felület és a WP admin ne kerüljön a keresőbe. */
add_filter('robots_txt', function ($out, $public) {
	if (!$public) return $out;
	return "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\nDisallow: /szervezo/\n\nSitemap: " . home_url('/wp-sitemap.xml') . "\n";
}, 10, 2);

/** Egyszerű fejléc és lábléc a nem főoldali oldalakhoz. */
function rmg_theme_header(): void { ?>
	<header class="nav page-nav"><a class="brand" href="<?php echo esc_url(home_url('/')); ?>">RMG<span>RADNÓTI / SZEGED</span></a><a class="page-back" href="<?php echo esc_url(home_url('/#kollekcio')); ?>">← Vissza a pulóverekhez</a></header>
<?php }
function rmg_theme_footer(): void { ?>
	<footer><a class="brand" href="<?php echo esc_url(home_url('/')); ?>">RMG<span>RADNÓTI / SZEGED</span></a><span>Szegedi Radnóti Miklós Kísérleti Gimnázium</span></footer>
<?php }
