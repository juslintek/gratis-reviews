<?php declare(strict_types=1);

namespace Gratis\Reviews;

defined('ABSPATH') || exit;

/**
 * Per-testimonial meta box: star rating, author name and role.
 */
final class MetaBox {

    private const NONCE_ACTION = 'gratis_reviews_save_meta';
    private const NONCE_FIELD  = 'gratis_reviews_meta_nonce';

    /** Register the meta box. */
    public static function register(): void {
        add_meta_box(
            'gratis-reviews-details',
            __('Testimonial Details', 'gratis-reviews'),
            [__CLASS__, 'render'],
            Reviews::POST_TYPE,
            'side',
            'high'
        );
    }

    /** Render the meta box. */
    public static function render(\WP_Post $post): void {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        $rating = (int) get_post_meta($post->ID, '_rating', true);
        $rating = $rating >= 1 && $rating <= 5 ? $rating : 5;
        $name   = (string) get_post_meta($post->ID, '_author_name', true);
        $role   = (string) get_post_meta($post->ID, '_author_role', true);

        echo '<p><label for="gr_rating"><strong>' . esc_html__('Rating', 'gratis-reviews') . '</strong></label><br>';
        echo '<select id="gr_rating" name="gr_rating" class="widefat">';
        for ($i = 5; $i >= 1; $i--) {
            echo '<option value="' . absint($i) . '"' . selected($rating, $i, false) . '>'
                . esc_html(str_repeat('★', $i)) . ' (' . absint($i) . ')</option>';
        }
        echo '</select></p>';

        echo '<p><label for="gr_author"><strong>' . esc_html__('Author name', 'gratis-reviews') . '</strong></label><br>';
        echo '<input type="text" id="gr_author" name="gr_author" class="widefat" value="' . esc_attr($name) . '"></p>';

        echo '<p><label for="gr_role"><strong>' . esc_html__('Author role / company', 'gratis-reviews') . '</strong></label><br>';
        echo '<input type="text" id="gr_role" name="gr_role" class="widefat" value="' . esc_attr($role) . '"></p>';
    }

    /** Persist the meta box values. */
    public static function save(int $post_id, \WP_Post $post): void {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        $nonce = isset($_POST[self::NONCE_FIELD])
            ? sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD]))
            : '';
        if ('' === $nonce || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return;
        }
        if (!current_user_can('edit_post', $post_id) || Reviews::POST_TYPE !== $post->post_type) {
            return;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified above.
        $rating = isset($_POST['gr_rating']) ? absint(wp_unslash($_POST['gr_rating'])) : 5;
        update_post_meta($post_id, '_rating', max(1, min(5, $rating)));
        update_post_meta($post_id, '_author_name', isset($_POST['gr_author']) ? sanitize_text_field(wp_unslash($_POST['gr_author'])) : '');
        update_post_meta($post_id, '_author_role', isset($_POST['gr_role']) ? sanitize_text_field(wp_unslash($_POST['gr_role'])) : '');
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }
}
