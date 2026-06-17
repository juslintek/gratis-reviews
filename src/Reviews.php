<?php declare(strict_types=1);

namespace Gratis\Reviews;

defined('ABSPATH') || exit;

/**
 * Testimonial CPT, rating + testimonials shortcodes and Schema.org markup.
 */
final class Reviews {

    public const POST_TYPE = 'gratis_testimonial';

    /** Register the testimonial CPT and its meta. */
    public static function register(): void {
        register_post_type(self::POST_TYPE, [
            'labels'       => [
                'name'          => __('Testimonials', 'gratis-reviews'),
                'singular_name' => __('Testimonial', 'gratis-reviews'),
                'add_new_item'  => __('Add Testimonial', 'gratis-reviews'),
                'edit_item'     => __('Edit Testimonial', 'gratis-reviews'),
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_rest' => true,
            'menu_icon'    => 'dashicons-star-filled',
            'supports'     => ['title', 'editor', 'thumbnail', 'custom-fields'],
        ]);

        register_post_meta(self::POST_TYPE, '_rating', [
            'type'              => 'integer',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => static fn($v) => max(1, min(5, absint($v))),
            'auth_callback'     => static fn() => current_user_can('edit_posts'),
        ]);
        register_post_meta(self::POST_TYPE, '_author_name', [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => static fn() => current_user_can('edit_posts'),
        ]);
        register_post_meta(self::POST_TYPE, '_author_role', [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => static fn() => current_user_can('edit_posts'),
        ]);
    }

    /**
     * `[gratis-rating value="4.5" max="5" label="Excellent"]` star display.
     *
     * @param array<string,mixed>|string $atts Shortcode attributes.
     */
    public static function ratingShortcode($atts): string {
        $atts  = shortcode_atts(['value' => 5, 'max' => 5, 'label' => ''], $atts);
        $max   = max(1, (int) $atts['max']);
        $value = min((float) $atts['value'], $max);

        $stars = '';
        for ($i = 1; $i <= $max; $i++) {
            $stars .= $i <= $value ? '★' : '☆';
        }

        $out = '<span class="gratis-rating" aria-label="'
            . esc_attr(sprintf(
                /* translators: 1: rating value, 2: maximum rating */
                __('Rated %1$s out of %2$s', 'gratis-reviews'),
                (string) $value,
                (string) $max
            ))
            . '" style="color:#f59e0b;font-size:1.2em">' . esc_html($stars) . '</span>';

        if ('' !== (string) $atts['label']) {
            $out .= ' <span class="gratis-rating-label">' . esc_html((string) $atts['label']) . '</span>';
        }
        return $out;
    }

    /**
     * `[gratis-testimonials count="3" columns="3"]` testimonial grid.
     *
     * @param array<string,mixed>|string $atts Shortcode attributes.
     */
    public static function testimonialsShortcode($atts): string {
        $defaults = ['count' => Settings::defaultCount(), 'columns' => Settings::defaultColumns()];
        $atts     = shortcode_atts($defaults, $atts);
        $count    = max(1, (int) $atts['count']);
        $columns  = max(1, min(6, (int) $atts['columns']));

        // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_numberposts -- bounded by the count attribute.
        $testimonials = get_posts([
            'post_type'   => self::POST_TYPE,
            'numberposts' => $count,
            'post_status' => 'publish',
        ]);
        if (empty($testimonials)) {
            return '';
        }

        $html = '<div class="gratis-testimonials" style="display:grid;grid-template-columns:repeat('
            . $columns . ',1fr);gap:1.5rem">';
        foreach ($testimonials as $t) {
            $rating = (int) get_post_meta($t->ID, '_rating', true);
            $rating = $rating >= 1 && $rating <= 5 ? $rating : 5;
            $author = (string) get_post_meta($t->ID, '_author_name', true);
            $author = '' !== $author ? $author : $t->post_title;
            $role   = (string) get_post_meta($t->ID, '_author_role', true);
            $stars  = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);

            $html .= '<figure class="gratis-testimonial" style="margin:0;background:#161a22;border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:1.5rem">';
            $html .= '<div class="gratis-testimonial-stars" style="color:#f59e0b;margin-bottom:0.5rem">' . esc_html($stars) . '</div>';
            $html .= '<blockquote style="margin:0 0 1rem;color:rgba(255,255,255,0.8);font-size:0.95rem;line-height:1.6">' . esc_html($t->post_content) . '</blockquote>';
            $html .= '<figcaption><span style="font-weight:600;font-size:0.85rem">' . esc_html($author) . '</span>';
            if ('' !== $role) {
                $html .= '<br><span style="color:rgba(255,255,255,0.4);font-size:0.8rem">' . esc_html($role) . '</span>';
            }
            $html .= '</figcaption></figure>';
        }
        $html .= '</div>';
        return $html;
    }

    /** Emit aggregate Review schema for singular content carrying a `_rating`. */
    public static function schemaMarkup(): void {
        if (!Settings::schemaEnabled() || !is_singular()) {
            return;
        }
        $post_id = (int) get_the_ID();
        $rating  = (float) get_post_meta($post_id, '_rating', true);
        if ($rating <= 0) {
            return;
        }

        $schema = [
            '@context'     => 'https://schema.org',
            '@type'        => 'Review',
            'itemReviewed' => ['@type' => 'Thing', 'name' => get_the_title($post_id)],
            'reviewRating' => [
                '@type'       => 'Rating',
                'ratingValue' => $rating,
                'bestRating'  => 5,
                'worstRating' => 1,
            ],
            'author'       => ['@type' => 'Person', 'name' => get_the_author()],
        ];

        echo '<script type="application/ld+json">'
            . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            . '</script>' . "\n";
    }

    /**
     * Compute aggregate stats over published testimonials carrying a 1-5 `_rating`.
     *
     * @return array{count:int,average:float} Review count and mean rating (0.0 when none).
     */
    private static function aggregateRatingStats(): array {
        // ponytail: O(n) scan over published testimonials per call. For very large
        // volumes, cache in a transient invalidated on save/transition/delete. Fine
        // for typical testimonial counts. get_posts() primes the meta cache so the
        // per-post _rating reads below do not each hit the database.
        // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_numberposts -- aggregate needs every published rating.
        $testimonials = get_posts([
            'post_type'   => self::POST_TYPE,
            'numberposts' => -1,
            'post_status' => 'publish',
        ]);

        $sum   = 0;
        $count = 0;
        foreach ($testimonials as $t) {
            $rating = (int) get_post_meta($t->ID, '_rating', true);
            if ($rating >= 1 && $rating <= 5) {
                $sum += $rating;
                ++$count;
            }
        }

        return [
            'count'   => $count,
            'average' => $count > 0 ? $sum / $count : 0.0,
        ];
    }

    /**
     * Build the AggregateRating schema array, or null when there are no ratings.
     *
     * Block-free helper reusable by themes. The reviewed item defaults to an
     * Organization named after the site; both the type and name are filterable,
     * as is the final schema array.
     *
     * @return array<string,mixed>|null
     */
    public static function aggregateRatingData(): ?array {
        $stats = self::aggregateRatingStats();
        if ($stats['count'] < 1) {
            return null;
        }

        /** Filter the schema type of the reviewed item (e.g. Organization, Thing, Product). */
        $item_type = (string) apply_filters('gratis_reviews_aggregate_item_type', 'Organization');
        /** Filter the name of the reviewed item (defaults to the site name). */
        $item_name = (string) apply_filters('gratis_reviews_aggregate_item_name', get_bloginfo('name'));

        $schema = [
            '@context'     => 'https://schema.org',
            '@type'        => 'AggregateRating',
            'itemReviewed' => [
                '@type' => '' !== $item_type ? $item_type : 'Organization',
                'name'  => $item_name,
            ],
            'ratingValue'  => round($stats['average'], 1),
            'reviewCount'  => $stats['count'],
            'bestRating'   => 5,
            'worstRating'  => 1,
        ];

        /** Filter the complete AggregateRating schema before output. */
        return (array) apply_filters('gratis_reviews_aggregate_schema', $schema, $stats);
    }

    /**
     * `[gratis-aggregate-rating schema="yes" text="yes"]` — visible summary + JSON-LD.
     *
     * Emits a valid schema.org AggregateRating (gated by the global schema setting)
     * alongside an optional human-readable "X out of 5 based on N reviews" line.
     *
     * @param array<string,mixed>|string $atts Shortcode attributes.
     */
    public static function aggregateRatingShortcode($atts): string {
        $atts        = shortcode_atts(['schema' => 'yes', 'text' => 'yes'], $atts);
        $show_schema = 'no' !== strtolower((string) $atts['schema']);
        $show_text   = 'no' !== strtolower((string) $atts['text']);

        $stats = self::aggregateRatingStats();
        if ($stats['count'] < 1) {
            return '';
        }

        $average = $stats['average'];
        $count   = $stats['count'];
        $rounded = max(1, min(5, (int) round($average)));
        $stars   = str_repeat('★', $rounded) . str_repeat('☆', 5 - $rounded);

        /* translators: 1: average rating, 2: best possible rating, 3: number of reviews */
        $template = _n(
            '%1$s out of %2$s based on %3$s review',
            '%1$s out of %2$s based on %3$s reviews',
            $count,
            'gratis-reviews'
        );
        $summary = sprintf(
            $template,
            number_format_i18n($average, 1),
            '5',
            number_format_i18n($count)
        );

        $html = '<div class="gratis-aggregate-rating">';
        if ($show_text) {
            // Visible summary conveys the rating; the stars are decorative for AT.
            $html .= '<span class="gratis-aggregate-rating-stars" style="color:#f59e0b;font-size:1.2em" aria-hidden="true">'
                . esc_html($stars) . '</span>';
            $html .= ' <span class="gratis-aggregate-rating-text">' . esc_html($summary) . '</span>';
        } else {
            // Stars only: expose the rating to assistive tech via an accessible label.
            $html .= '<span class="gratis-aggregate-rating-stars" role="img" aria-label="' . esc_attr($summary)
                . '" style="color:#f59e0b;font-size:1.2em">' . esc_html($stars) . '</span>';
        }
        $html .= '</div>';

        if ($show_schema && Settings::schemaEnabled()) {
            $schema = self::aggregateRatingData();
            if (null !== $schema) {
                $html .= '<script type="application/ld+json">'
                    . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    . '</script>';
            }
        }

        return $html;
    }

    /**
     * `[gratis-review-form]` — moderated front-end submission form.
     *
     * Renders a nonce-protected form posting to admin-post.php. Submissions are
     * stored as `pending` testimonials (never auto-published). A status flag in
     * the URL (set by the Post/Redirect/Get handler) drives the notice.
     *
     * @param array<string,mixed>|string $atts Shortcode attributes.
     */
    public static function reviewFormShortcode($atts): string {
        $atts  = shortcode_atts(['title' => __('Leave a review', 'gratis-reviews')], $atts);
        $title = (string) $atts['title'];

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only status flag set by our own PRG redirect.
        $state  = isset($_GET['gratis_review']) ? sanitize_key(wp_unslash($_GET['gratis_review'])) : '';
        $notice = '';
        if ('success' === $state) {
            $notice = '<div class="gratis-review-notice gratis-review-notice-ok" role="status" '
                . 'style="border-left:4px solid #16a34a;background:#f0fdf4;color:#166534;padding:.75rem 1rem;border-radius:6px;margin-bottom:1rem">'
                . esc_html__('Thank you! Your review has been submitted and is awaiting moderation.', 'gratis-reviews')
                . '</div>';
        } elseif ('error' === $state) {
            $notice = '<div class="gratis-review-notice gratis-review-notice-error" role="alert" '
                . 'style="border-left:4px solid #dc2626;background:#fef2f2;color:#991b1b;padding:.75rem 1rem;border-radius:6px;margin-bottom:1rem">'
                . esc_html__('Sorry, your review could not be submitted. Please check your entries and try again.', 'gratis-reviews')
                . '</div>';
        }

        $field_style = 'width:100%;max-width:420px;padding:.5rem;border:1px solid #ccc;border-radius:6px';

        $html  = '<div class="gratis-review-form">';
        $html .= $notice;
        if ('' !== $title) {
            $html .= '<h3 class="gratis-review-form-title">' . esc_html($title) . '</h3>';
        }

        $html .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="gratis-review-form-form">';
        $html .= '<input type="hidden" name="action" value="gratis_review_submit">';
        $html .= wp_nonce_field('gratis_review_submit', 'gratis_review_nonce', true, false);

        $html .= '<p><label for="gratis-review-rating">' . esc_html__('Rating', 'gratis-reviews') . '</label><br>';
        $html .= '<select id="gratis-review-rating" name="gr_rating" required style="' . esc_attr($field_style) . '">';
        for ($i = 5; $i >= 1; $i--) {
            $html .= '<option value="' . absint($i) . '">'
                . esc_html(str_repeat('★', $i) . ' (' . $i . ')') . '</option>';
        }
        $html .= '</select></p>';

        $html .= '<p><label for="gratis-review-name">' . esc_html__('Your name', 'gratis-reviews') . '</label><br>';
        $html .= '<input type="text" id="gratis-review-name" name="gr_name" required maxlength="100" '
            . 'autocomplete="name" style="' . esc_attr($field_style) . '"></p>';

        $html .= '<p><label for="gratis-review-content">' . esc_html__('Your review', 'gratis-reviews') . '</label><br>';
        $html .= '<textarea id="gratis-review-content" name="gr_content" required rows="4" maxlength="2000" '
            . 'style="' . esc_attr($field_style) . '"></textarea></p>';

        // ponytail: honeypot — bots fill the off-screen field; humans never see it.
        $html .= '<p style="position:absolute;left:-9999px" aria-hidden="true">';
        $html .= '<label>' . esc_html__('Leave this field empty', 'gratis-reviews')
            . '<input type="text" name="gr_website" tabindex="-1" autocomplete="off"></label></p>';

        $html .= '<p><button type="submit" class="gratis-review-form-submit" '
            . 'style="padding:.6rem 1.2rem;cursor:pointer;border-radius:6px">'
            . esc_html__('Submit review', 'gratis-reviews') . '</button></p>';

        $html .= '</form></div>';

        return $html;
    }

    /**
     * Handle `[gratis-review-form]` submissions (admin-post.php endpoint).
     *
     * Verifies the nonce, drops honeypot hits, sanitizes input, and stores the
     * testimonial as `pending` for moderation. Redirects back via PRG with a
     * status flag; never echoes output and never auto-publishes.
     */
    public static function handleFormSubmission(): void {
        $nonce = isset($_POST['gratis_review_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['gratis_review_nonce']))
            : '';
        if ('' === $nonce || !wp_verify_nonce($nonce, 'gratis_review_submit')) {
            self::redirectAfterSubmit('error');
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified above.
        // Honeypot: a filled trap means a bot. Report success so we don't tip it off.
        $trap = isset($_POST['gr_website']) ? sanitize_text_field(wp_unslash($_POST['gr_website'])) : '';
        if ('' !== $trap) {
            self::redirectAfterSubmit('success');
        }

        $name    = isset($_POST['gr_name']) ? sanitize_text_field(wp_unslash($_POST['gr_name'])) : '';
        $content = isset($_POST['gr_content']) ? sanitize_textarea_field(wp_unslash($_POST['gr_content'])) : '';
        $rating  = isset($_POST['gr_rating']) ? absint(wp_unslash($_POST['gr_rating'])) : 0;
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        $rating = max(1, min(5, $rating));

        if ('' === $name || '' === $content) {
            self::redirectAfterSubmit('error');
        }

        $post_id = wp_insert_post([
            'post_type'    => self::POST_TYPE,
            'post_title'   => wp_slash($name),
            'post_content' => wp_slash($content),
            'post_status'  => 'pending',
        ], true);

        if (is_wp_error($post_id)) {
            self::redirectAfterSubmit('error');
        }

        update_post_meta($post_id, '_rating', $rating);
        update_post_meta($post_id, '_author_name', $name);

        self::redirectAfterSubmit('success');
    }

    /**
     * Redirect back to the submitting page with a status flag, then exit.
     *
     * @param string $status Either `success` or `error`.
     */
    private static function redirectAfterSubmit(string $status): void {
        $back = wp_get_referer();
        if (!$back) {
            $back = home_url('/');
        }
        wp_safe_redirect(add_query_arg('gratis_review', $status, $back));
        exit;
    }
}
