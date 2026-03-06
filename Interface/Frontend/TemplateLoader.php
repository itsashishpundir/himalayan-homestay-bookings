<?php
/**
 * Plugin Template Loader
 *
 * Intercepts WordPress template loading and serves the plugin's own default templates
 * when the active theme does not provide an override.
 *
 * Priority order:
 *   1. Theme template (standard WP hierarchy lookup) — always wins
 *   2. Plugin template from /templates/ — fallback if theme has no override
 *
 * @package Himalayan\Homestay\Interface\Frontend
 */

namespace Himalayan\Homestay\Interface\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class TemplateLoader
 */
class TemplateLoader {

    /**
     * Map post type / page template slugs to plugin template filenames.
     * Key   = page template slug that is assigned to a WordPress page.
     * Value = filename in /templates/
     *
     * @var array<string, string>
     */
    private array $page_template_map = [
        'host-panel-template'      => 'dashboard.php',
        'page-host-panel.php'      => 'dashboard.php', // Backwards compatibility with theme template
        'become-host-template'     => 'become-host.php',
        'page-become-a-host.php'   => 'become-host.php', // Backwards compatibility
        'legal-page-template'      => 'legal-page.php',
    ];

    /**
     * Constructor — hooks into WordPress template loading.
     */
    public function __construct() {
        add_filter( 'template_include', [ $this, 'intercept_template' ], 99 );
        add_filter( 'theme_page_templates', [ $this, 'register_page_templates' ] );
    }

    /**
     * Register custom page templates so they appear in the WP editor Page Templates dropdown.
     *
     * @param array $templates Existing templates.
     * @return array
     */
    public function register_page_templates( array $templates ): array {
        $templates['host-panel-template']  = __( 'Host Dashboard (Plugin)', 'himalayan-homestay-bookings' );
        $templates['become-host-template'] = __( 'Become a Host (Plugin)', 'himalayan-homestay-bookings' );
        $templates['legal-page-template']  = __( 'Legal Page (Plugin)', 'himalayan-homestay-bookings' );
        return $templates;
    }

    /**
     * Main template interception hook.
     *
     * For custom page templates (Host Dashboard, Become a Host) and for CPT/taxonomy
     * archives, we serve the plugin default if the theme has no override.
     *
     * @param string $template Resolved template path from WordPress.
     * @return string
     */
    public function intercept_template( string $template ): string {

        // ── Page Templates ─────────────────────────────────────────────────────
        if ( is_singular() ) {
            $page_template = get_post_meta( get_the_ID(), '_wp_page_template', true );

            if ( isset( $this->page_template_map[ $page_template ] ) ) {
                $plugin_file = $this->page_template_map[ $page_template ];
                return $this->resolve( $plugin_file, $template );
            }
        }

        // ── CPT: Single Homestay ───────────────────────────────────────────────
        if ( is_singular( 'hhb_homestay' ) ) {
            return $this->resolve( 'single-hhb_homestay.php', $template );
        }

        // ── CPT: Archive ───────────────────────────────────────────────────────
        if ( is_post_type_archive( 'hhb_homestay' ) ) {
            return $this->resolve( 'archive-hhb_homestay.php', $template );
        }

        // ── Taxonomies ─────────────────────────────────────────────────────────
        if ( is_tax( 'hhb_location' ) ) {
            // First try the taxonomy-specific template, then fall back to archive
            $resolved = $this->resolve( 'taxonomy-hhb_location.php', $template );
            if ( $resolved !== $template ) {
                return $resolved;
            }
            return $this->resolve( 'archive-hhb_homestay.php', $template );
        }

        if ( is_tax( 'hhb_property_type' ) ) {
            $resolved = $this->resolve( 'taxonomy-hhb_property_type.php', $template );
            if ( $resolved !== $template ) {
                return $resolved;
            }
            return $this->resolve( 'archive-hhb_homestay.php', $template );
        }

        return $template;
    }

    /**
     * Resolve a template file path following the override hierarchy.
     *
     * @param string $filename  Template filename to look for (relative to /templates/).
     * @param string $fallback  The current resolved template to fall back to if nothing found.
     * @return string
     */
    private function resolve( string $filename, string $fallback ): string {
        // 1. Check if the theme provides this template (highest priority)
        $theme_template = locate_template( [ $filename ] );
        if ( $theme_template ) {
            return $theme_template;
        }

        // 2. Fall back to plugin template
        $plugin_template = HHB_PLUGIN_DIR . 'templates/' . $filename;
        if ( file_exists( $plugin_template ) ) {
            return $plugin_template;
        }

        // 3. Nothing found — return original resolved template
        return $fallback;
    }
}
