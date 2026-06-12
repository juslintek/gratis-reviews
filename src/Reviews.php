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
}
