<?php
/**
 * Plugin Name: Gratis Reviews
 * Description: Star ratings + testimonials + Schema.org markup. Replaces WP Review Pro ($67/yr).
 * Version: 1.0.0
 * Requires PHP: 8.1
 * Author: Linas Jusys
 * License: GPL-2.0-or-later
 * Text Domain: gratis-reviews
 */
defined('ABSPATH') || exit;
require_once __DIR__ . '/autoload.php';

add_action('init', [\Gratis\Reviews\Reviews::class, 'register']);
add_shortcode('gratis-rating', [\Gratis\Reviews\Reviews::class, 'ratingShortcode']);
add_shortcode('gratis-testimonials', [\Gratis\Reviews\Reviews::class, 'testimonialsShortcode']);
add_action('wp_head', [\Gratis\Reviews\Reviews::class, 'schemaMarkup']);
