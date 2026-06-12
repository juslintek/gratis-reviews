<?php declare(strict_types=1);

namespace Gratis\Reviews\CLI;

use Gratis\Reviews\Reviews;

defined('ABSPATH') || exit;

/**
 * WP-CLI helpers for Gratis Reviews.
 */
final class ReviewsCommand {

    /**
     * List testimonials.
     *
     * @when after_wp_load
     *
     * @param array<int,string>    $args       Positional args (unused).
     * @param array<string,string> $assoc_args Associative args (unused).
     */
    public function list($args, $assoc_args): void {
        unset($args, $assoc_args);
        // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_numberposts -- admin CLI listing.
        $items = get_posts(['post_type' => Reviews::POST_TYPE, 'numberposts' => -1, 'post_status' => 'any']);
        if (empty($items)) {
            \WP_CLI::log('No testimonials.');
            return;
        }
        $rows = array_map(static fn($t) => [
            'ID'     => $t->ID,
            'Author' => (string) get_post_meta($t->ID, '_author_name', true) ?: $t->post_title,
            'Rating' => (int) get_post_meta($t->ID, '_rating', true),
            'Status' => $t->post_status,
        ], $items);
        \WP_CLI\Utils\format_items('table', $rows, ['ID', 'Author', 'Rating', 'Status']);
    }

    /**
     * Add a testimonial.
     *
     * ## OPTIONS
     *
     * <quote>
     * : The testimonial text.
     *
     * [--author=<author>]
     * : Author name.
     *
     * [--role=<role>]
     * : Author role / company.
     *
     * [--rating=<rating>]
     * : Star rating 1-5 (default 5).
     *
     * @when after_wp_load
     *
     * @param array<int,string>    $args       Positional args.
     * @param array<string,string> $assoc_args Associative args.
     */
    public function add($args, $assoc_args): void {
        $quote  = (string) ($args[0] ?? '');
        $author = (string) ($assoc_args['author'] ?? __('Anonymous', 'gratis-reviews'));
        $role   = (string) ($assoc_args['role'] ?? '');
        $rating = max(1, min(5, (int) ($assoc_args['rating'] ?? 5)));

        if ('' === trim($quote)) {
            \WP_CLI::error('A testimonial quote is required.');
        }

        $post_id = wp_insert_post([
            'post_type'    => Reviews::POST_TYPE,
            'post_title'   => $author,
            'post_content' => $quote,
            'post_status'  => 'publish',
        ], true);

        if (is_wp_error($post_id)) {
            \WP_CLI::error($post_id->get_error_message());
        }

        update_post_meta($post_id, '_author_name', sanitize_text_field($author));
        update_post_meta($post_id, '_author_role', sanitize_text_field($role));
        update_post_meta($post_id, '_rating', $rating);

        \WP_CLI::success("Testimonial #{$post_id} created.");
    }
}
