<?php declare(strict_types=1);
namespace Gratis\Reviews;

final class Reviews
{
    public static function register(): void
    {
        register_post_type('gratis_testimonial', [
            'labels'       => ['name' => 'Testimonials', 'singular_name' => 'Testimonial'],
            'public'       => false,
            'show_ui'      => true,
            'show_in_rest' => true,
            'menu_icon'    => 'dashicons-star-filled',
            'supports'     => ['title', 'editor', 'thumbnail', 'custom-fields'],
        ]);

        register_post_meta('gratis_testimonial', '_rating', ['type' => 'integer', 'single' => true, 'show_in_rest' => true]);
        register_post_meta('gratis_testimonial', '_author_name', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
        register_post_meta('gratis_testimonial', '_author_role', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
    }

    public static function ratingShortcode($atts): string
    {
        $atts = shortcode_atts(['value' => 5, 'max' => 5, 'label' => ''], $atts);
        $value = min((float) $atts['value'], (int) $atts['max']);
        $stars = '';
        for ($i = 1; $i <= (int) $atts['max']; $i++) {
            $stars .= $i <= $value ? '★' : '☆';
        }
        $label = $atts['label'] ? '<span class="gratis-rating-label">' . esc_html($atts['label']) . '</span>' : '';
        return '<span class="gratis-rating" style="color:#f59e0b;font-size:1.2em">' . $stars . '</span>' . $label;
    }

    public static function testimonialsShortcode($atts): string
    {
        $atts = shortcode_atts(['count' => 3, 'columns' => 3], $atts);
        $testimonials = get_posts(['post_type' => 'gratis_testimonial', 'numberposts' => (int) $atts['count'], 'post_status' => 'publish']);
        if (empty($testimonials)) return '';

        $html = '<div class="gratis-testimonials" style="display:grid;grid-template-columns:repeat(' . (int) $atts['columns'] . ',1fr);gap:1.5rem">';
        foreach ($testimonials as $t) {
            $rating = (int) get_post_meta($t->ID, '_rating', true) ?: 5;
            $author = get_post_meta($t->ID, '_author_name', true) ?: $t->post_title;
            $role = get_post_meta($t->ID, '_author_role', true);
            $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);

            $html .= '<div class="gratis-testimonial" style="background:#161a22;border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:1.5rem">';
            $html .= '<div style="color:#f59e0b;margin-bottom:0.5rem">' . $stars . '</div>';
            $html .= '<p style="color:rgba(255,255,255,0.8);font-size:0.95rem;line-height:1.6;margin:0 0 1rem">' . esc_html($t->post_content) . '</p>';
            $html .= '<div style="font-weight:600;font-size:0.85rem">' . esc_html($author) . '</div>';
            if ($role) $html .= '<div style="color:rgba(255,255,255,0.4);font-size:0.8rem">' . esc_html($role) . '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';
        return $html;
    }

    public static function schemaMarkup(): void
    {
        if (!is_singular()) return;
        $rating = get_post_meta(get_the_ID(), '_gratis_rating', true);
        if (!$rating) return;

        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'Review',
            'itemReviewed' => ['@type' => 'Thing', 'name' => get_the_title()],
            'reviewRating' => ['@type' => 'Rating', 'ratingValue' => (float) $rating, 'bestRating' => 5],
            'author'       => ['@type' => 'Person', 'name' => get_the_author()],
        ];
        echo '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }
}
