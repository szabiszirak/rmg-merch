<?php defined('ABSPATH') || exit; get_header(); ?>
<div id="rmg-root"></div>
<noscript><main class="page-content"><h1>RMG Merch</h1><p><?php echo esc_html(RMG_THEME_DESC); ?></p><p>Az oldal használatához kapcsold be a JavaScriptet. Kapcsolat: <?php echo esc_html(function_exists('rmg_contact_email') ? rmg_contact_email() : 'radnotimerch@gmail.com'); ?></p></main></noscript>
<?php get_footer();
