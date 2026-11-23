<?php defined('ABSPATH') || exit; get_header(); rmg_theme_header(); ?>
<main class="page-content">
<?php if (have_posts()): while (have_posts()): the_post(); ?>
	<article <?php post_class(); ?>><h1><?php the_title(); ?></h1><div class="entry"><?php the_content(); ?></div></article>
<?php endwhile; else: ?>
	<h1>Nincs ilyen oldal.</h1><p><a class="button dark" href="<?php echo esc_url(home_url('/')); ?>">Vissza a főoldalra <span>↗</span></a></p>
<?php endif; ?>
</main>
<?php rmg_theme_footer(); get_footer();
