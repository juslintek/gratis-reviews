<?php
/**
 * Plugin Name: Gratis Reviews
 * Plugin URI: https://github.com/juslintek/gratis-reviews
 * Description: Star ratings + testimonials + Schema.org markup. Replaces WP Review Pro ($67/yr).
 * Version: 1.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Linas Jusys
 * Author URI: https://github.com/juslintek
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gratis-reviews
 *
 * @package Gratis\Reviews
 */

defined('ABSPATH') || exit;

define('GRATIS_REVIEWS_VERSION', '1.0.0');
define('GRATIS_REVIEWS_FILE', __FILE__);
define('GRATIS_REVIEWS_DIR', plugin_dir_path(__FILE__));
define('GRATIS_REVIEWS_URL', plugin_dir_url(__FILE__));

require_once __DIR__ . '/autoload.php';

add_action('init', [\Gratis\Reviews\Reviews::class, 'register']);
add_shortcode('gratis-rating', [\Gratis\Reviews\Reviews::class, 'ratingShortcode']);
add_shortcode('gratis-testimonials', [\Gratis\Reviews\Reviews::class, 'testimonialsShortcode']);
add_action('wp_head', [\Gratis\Reviews\Reviews::class, 'schemaMarkup']);

add_action('add_meta_boxes', [\Gratis\Reviews\MetaBox::class, 'register']);
add_action('save_post_gratis_testimonial', [\Gratis\Reviews\MetaBox::class, 'save'], 10, 2);

add_action('admin_menu', [\Gratis\Reviews\Settings::class, 'register_menu']);
add_action('admin_init', [\Gratis\Reviews\Settings::class, 'register_settings']);

add_action('plugins_loaded', function () {
    if (defined('WP_CLI') && WP_CLI) {
        \WP_CLI::add_command('gratis-reviews', \Gratis\Reviews\CLI\ReviewsCommand::class);
    }
});
