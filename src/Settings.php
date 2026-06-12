<?php declare(strict_types=1);

namespace Gratis\Reviews;

defined('ABSPATH') || exit;

/**
 * Global settings for Gratis Reviews.
 */
final class Settings {

    private const GROUP = 'gratis_reviews_settings_group';
    private const PAGE  = 'gratis-reviews-settings';

    private const OPT_SCHEMA  = 'gratis_reviews_schema';
    private const OPT_COLUMNS = 'gratis_reviews_columns';
    private const OPT_COUNT   = 'gratis_reviews_count';

    /** Register the settings submenu under the Testimonials menu. */
    public static function register_menu(): void {
        add_submenu_page(
            'edit.php?post_type=' . Reviews::POST_TYPE,
            __('Reviews Settings', 'gratis-reviews'),
            __('Settings', 'gratis-reviews'),
            'manage_options',
            self::PAGE,
            [__CLASS__, 'render_page']
        );
    }

    /** Register options. */
    public static function register_settings(): void {
        register_setting(self::GROUP, self::OPT_SCHEMA, [
            'type'              => 'boolean',
            'sanitize_callback' => static fn($v) => (bool) $v,
            'default'           => true,
        ]);
        register_setting(self::GROUP, self::OPT_COLUMNS, [
            'type'              => 'integer',
            'sanitize_callback' => static fn($v) => max(1, min(6, absint($v))),
            'default'           => 3,
        ]);
        register_setting(self::GROUP, self::OPT_COUNT, [
            'type'              => 'integer',
            'sanitize_callback' => static fn($v) => max(1, min(50, absint($v))),
            'default'           => 3,
        ]);
    }

    /** Whether Review schema markup is emitted. */
    public static function schemaEnabled(): bool {
        return (bool) get_option(self::OPT_SCHEMA, true);
    }

    /** Default testimonial grid columns. */
    public static function defaultColumns(): int {
        return max(1, min(6, (int) get_option(self::OPT_COLUMNS, 3)));
    }

    /** Default testimonial count. */
    public static function defaultCount(): int {
        return max(1, min(50, (int) get_option(self::OPT_COUNT, 3)));
    }

    /** Render the settings screen. */
    public static function render_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'gratis-reviews'));
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Reviews Settings', 'gratis-reviews') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields(self::GROUP);
        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr><th scope="row">' . esc_html__('Schema.org markup', 'gratis-reviews') . '</th><td>';
        echo '<label><input type="checkbox" name="' . esc_attr(self::OPT_SCHEMA) . '" value="1"' . checked(self::schemaEnabled(), true, false) . '> ';
        echo esc_html__('Emit Review structured data on singular content with a rating.', 'gratis-reviews') . '</label></td></tr>';

        echo '<tr><th scope="row"><label for="gr_columns">' . esc_html__('Default columns', 'gratis-reviews') . '</label></th><td>';
        echo '<input type="number" min="1" max="6" id="gr_columns" class="small-text" name="' . esc_attr(self::OPT_COLUMNS) . '" value="' . esc_attr((string) self::defaultColumns()) . '"></td></tr>';

        echo '<tr><th scope="row"><label for="gr_count">' . esc_html__('Default count', 'gratis-reviews') . '</label></th><td>';
        echo '<input type="number" min="1" max="50" id="gr_count" class="small-text" name="' . esc_attr(self::OPT_COUNT) . '" value="' . esc_attr((string) self::defaultCount()) . '"></td></tr>';

        echo '</tbody></table>';
        submit_button(__('Save settings', 'gratis-reviews'));
        echo '</form>';

        echo '<hr><h2>' . esc_html__('Usage', 'gratis-reviews') . '</h2>';
        echo '<p><code>[gratis-rating value="4.5" max="5" label="Excellent"]</code></p>';
        echo '<p><code>[gratis-testimonials count="3" columns="3"]</code></p>';
        echo '</div>';
    }
}
