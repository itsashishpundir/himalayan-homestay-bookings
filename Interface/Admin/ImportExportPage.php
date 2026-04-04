<?php
/**
 * Homestay Import / Export Page
 *
 * Provides a full JSON backup, export, and import for all hhb_homestay posts
 * including meta, taxonomies, gallery images, and child rooms.
 *
 * @package Himalayan\Homestay\Interface\Admin
 */

namespace Himalayan\Homestay\Interface\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ImportExportPage {

    const NONCE_EXPORT = 'hhb_export_homestays';
    const NONCE_IMPORT = 'hhb_import_homestays';
    const EXPORT_VERSION = '1.0';

    public static function init(): void {
        add_action( 'admin_menu',  [ __CLASS__, 'add_page' ], 35 );
        add_action( 'admin_init',  [ __CLASS__, 'handle_export' ] );
        add_action( 'admin_init',  [ __CLASS__, 'handle_import' ] );
    }

    // =========================================================================
    // Menu Registration
    // =========================================================================

    public static function add_page(): void {
        add_submenu_page(
            'edit.php?post_type=hhb_homestay',
            __( 'Import / Export', 'himalayan-homestay-bookings' ),
            __( '📦 Import / Export', 'himalayan-homestay-bookings' ),
            'manage_options',
            'hhb-import-export',
            [ __CLASS__, 'render_page' ]
        );
    }

    // =========================================================================
    // Export Handler
    // =========================================================================

    public static function handle_export(): void {
        if (
            ! isset( $_POST['hhb_action'] ) ||
            $_POST['hhb_action'] !== 'export' ||
            ! check_admin_referer( self::NONCE_EXPORT )
        ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $data = self::collect_export_data();

        $filename = 'himalayan-homestays-backup-' . date( 'Y-m-d-His' ) . '.json';

        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );

        echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        exit;
    }

    // =========================================================================
    // Import Handler
    // =========================================================================

    public static function handle_import(): void {
        if (
            ! isset( $_POST['hhb_action'] ) ||
            $_POST['hhb_action'] !== 'import' ||
            ! check_admin_referer( self::NONCE_IMPORT )
        ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        if ( empty( $_FILES['hhb_import_file']['tmp_name'] ) ) {
            wp_safe_redirect( add_query_arg( [ 'page' => 'hhb-import-export', 'import_error' => 'no_file' ], admin_url( 'edit.php?post_type=hhb_homestay' ) ) );
            exit;
        }

        $raw  = file_get_contents( $_FILES['hhb_import_file']['tmp_name'] );
        $data = json_decode( $raw, true );

        if ( ! $data || empty( $data['homestays'] ) ) {
            wp_safe_redirect( add_query_arg( [ 'page' => 'hhb-import-export', 'import_error' => 'invalid_file' ], admin_url( 'edit.php?post_type=hhb_homestay' ) ) );
            exit;
        }

        $mode    = sanitize_text_field( $_POST['import_mode'] ?? 'skip' ); // skip | overwrite
        $results = self::process_import( $data['homestays'], $mode );

        wp_safe_redirect( add_query_arg( [
            'page'           => 'hhb-import-export',
            'import_success' => 1,
            'imported'       => $results['imported'],
            'skipped'        => $results['skipped'],
            'updated'        => $results['updated'],
        ], admin_url( 'edit.php?post_type=hhb_homestay' ) ) );
        exit;
    }

    // =========================================================================
    // Data Collection for Export
    // =========================================================================

    private static function collect_export_data(): array {
        $posts = get_posts( [
            'post_type'      => 'hhb_homestay',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ] );

        $homestays = [];

        foreach ( $posts as $post ) {
            $meta  = get_post_meta( $post->ID );
            $clean_meta = [];
            foreach ( $meta as $key => $values ) {
                // Only export hhb_ meta and common useful meta
                if ( str_starts_with( $key, 'hhb_' ) || str_starts_with( $key, 'room_' ) || in_array( $key, [ '_thumbnail_id', '_wp_attachment_metadata' ], true ) ) {
                    $clean_meta[ $key ] = count( $values ) === 1 ? $values[0] : $values;
                }
            }

            // Taxonomies
            $tax_data = [];
            foreach ( [ 'hhb_location', 'hhb_property_type' ] as $tax ) {
                $terms = get_the_terms( $post->ID, $tax );
                if ( $terms && ! is_wp_error( $terms ) ) {
                    $tax_data[ $tax ] = array_map( fn( $t ) => [
                        'slug'   => $t->slug,
                        'name'   => $t->name,
                        'parent' => $t->parent ? get_term( $t->parent )->slug : null,
                    ], $terms );
                }
            }

            // Featured image URL
            $thumb_url = get_the_post_thumbnail_url( $post->ID, 'full' );

            // Gallery image URLs
            $gallery_ids  = maybe_unserialize( get_post_meta( $post->ID, 'hhb_gallery', true ) );
            $gallery_urls = [];
            if ( is_array( $gallery_ids ) ) {
                foreach ( $gallery_ids as $img_id ) {
                    $url = wp_get_attachment_url( (int) $img_id );
                    if ( $url ) $gallery_urls[] = $url;
                }
            }

            // Child rooms
            $rooms = get_posts( [
                'post_type'      => 'hhb_room',
                'post_parent'    => $post->ID,
                'post_status'    => 'any',
                'posts_per_page' => -1,
            ] );

            $rooms_data = [];
            foreach ( $rooms as $room ) {
                $room_meta  = get_post_meta( $room->ID );
                $clean_room_meta = [];
                foreach ( $room_meta as $k => $v ) {
                    $clean_room_meta[ $k ] = count( $v ) === 1 ? $v[0] : $v;
                }
                $rooms_data[] = [
                    'title'   => $room->post_title,
                    'status'  => $room->post_status,
                    'content' => $room->post_content,
                    'meta'    => $clean_room_meta,
                ];
            }

            // Reviews
            global $wpdb;
            $table_reviews = $wpdb->prefix . 'hhb_reviews';
            $reviews = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_reviews} WHERE homestay_id = %d", $post->ID ), ARRAY_A );
            
            // Clean up review data before export
            $clean_reviews = [];
            if ( $reviews ) {
                foreach ( $reviews as $rev ) {
                    unset($rev['id'], $rev['homestay_id']); // We don't need the local primary key or the old homestay_id
                    $clean_reviews[] = $rev;
                }
            }

            $homestays[] = [
                'export_id'    => $post->ID,
                'title'        => $post->post_title,
                'slug'         => $post->post_name,
                'status'       => $post->post_status,
                'content'      => $post->post_content,
                'excerpt'      => $post->post_excerpt,
                'date'         => $post->post_date,
                'meta'         => $clean_meta,
                'taxonomies'   => $tax_data,
                'thumb_url'    => $thumb_url ?: '',
                'gallery_urls' => $gallery_urls,
                'rooms'        => $rooms_data,
                'reviews'      => $clean_reviews,
            ];
        }

        return [
            'version'      => self::EXPORT_VERSION,
            'exported_at'  => current_time( 'mysql' ),
            'site_url'     => get_site_url(),
            'total'        => count( $homestays ),
            'homestays'    => $homestays,
        ];
    }

    // =========================================================================
    // Import Processor
    // =========================================================================

    private static function process_import( array $homestays, string $mode ): array {
        $imported = 0;
        $skipped  = 0;
        $updated  = 0;

        foreach ( $homestays as $hs ) {
            $title = sanitize_text_field( $hs['title'] ?? '' );
            if ( ! $title ) continue;

            // Check if a post with same slug already exists
            $existing = get_page_by_path( sanitize_title( $hs['slug'] ?? $title ), OBJECT, 'hhb_homestay' );

            if ( $existing && $mode === 'skip' ) {
                $skipped++;
                continue;
            }

            $post_data = [
                'post_type'    => 'hhb_homestay',
                'post_title'   => $title,
                'post_name'    => sanitize_title( $hs['slug'] ?? $title ),
                'post_status'  => sanitize_key( $hs['status'] ?? 'draft' ),
                'post_content' => wp_kses_post( $hs['content'] ?? '' ),
                'post_excerpt' => sanitize_textarea_field( $hs['excerpt'] ?? '' ),
            ];

            if ( $existing && $mode === 'overwrite' ) {
                $post_data['ID'] = $existing->ID;
                $post_id = wp_update_post( $post_data, true );
                $updated++;
            } else {
                $post_id = wp_insert_post( $post_data, true );
                $imported++;
            }

            if ( is_wp_error( $post_id ) ) continue;

            // Meta
            if ( ! empty( $hs['meta'] ) ) {
                foreach ( $hs['meta'] as $key => $value ) {
                    if ( in_array( $key, [ '_thumbnail_id', '_wp_attachment_metadata' ], true ) ) continue;
                    update_post_meta( $post_id, sanitize_key( $key ), $value );
                }
            }

            // Taxonomies
            if ( ! empty( $hs['taxonomies'] ) ) {
                foreach ( $hs['taxonomies'] as $tax => $terms ) {
                    $term_ids = [];
                    foreach ( $terms as $t ) {
                        $term = get_term_by( 'slug', $t['slug'], $tax );
                        if ( ! $term ) {
                            $parent_id = 0;
                            if ( ! empty( $t['parent'] ) ) {
                                $parent_term = get_term_by( 'slug', $t['parent'], $tax );
                                if ( $parent_term ) $parent_id = $parent_term->term_id;
                            }
                            $result = wp_insert_term( $t['name'], $tax, [ 'slug' => $t['slug'], 'parent' => $parent_id ] );
                            $term   = ! is_wp_error( $result ) ? get_term( $result['term_id'] ) : null;
                        }
                        if ( $term ) $term_ids[] = $term->term_id;
                    }
                    if ( $term_ids ) {
                        wp_set_post_terms( $post_id, $term_ids, $tax );
                    }
                }
            }

            // Child rooms
            if ( ! empty( $hs['rooms'] ) ) {
                foreach ( $hs['rooms'] as $room ) {
                    $room_id = wp_insert_post( [
                        'post_type'    => 'hhb_room',
                        'post_title'   => sanitize_text_field( $room['title'] ?? 'Room' ),
                        'post_status'  => sanitize_key( $room['status'] ?? 'publish' ),
                        'post_content' => wp_kses_post( $room['content'] ?? '' ),
                        'post_parent'  => $post_id,
                    ] );
                    if ( ! is_wp_error( $room_id ) && ! empty( $room['meta'] ) ) {
                        foreach ( $room['meta'] as $k => $v ) {
                            update_post_meta( $room_id, sanitize_key( $k ), $v );
                        }
                    }
                }
            }

            // Reviews
            if ( ! empty( $hs['reviews'] ) ) {
                global $wpdb;
                $table_reviews = $wpdb->prefix . 'hhb_reviews';
                foreach ( $hs['reviews'] as $review ) {
                    $review['homestay_id'] = $post_id;
                    // If the original booking ID doesn't exist on this site, it might violate constraints or cause issues.
                    // We keep it as is, or default to 0 if missing. 
                    // To prevent UNIQUE KEY (booking_id) collisions if importing the same review twice in 'overwrite' mode,
                    // we can check if it exists:
                    $existing_review = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table_reviews} WHERE booking_id = %d AND homestay_id = %d AND customer_email = %s LIMIT 1", $review['booking_id'] ?? 0, $post_id, $review['customer_email'] ?? '' ) );
                    
                    if ( ! $existing_review ) {
                        $wpdb->insert( $table_reviews, $review );
                    }
                }
            }
        }

        return compact( 'imported', 'skipped', 'updated' );
    }

    // =========================================================================
    // Render Page
    // =========================================================================

    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $total = wp_count_posts( 'hhb_homestay' )->publish + wp_count_posts( 'hhb_homestay' )->draft;
        ?>
        <div class="wrap">
            <h1>📦 <?php esc_html_e( 'Homestay Import / Export', 'himalayan-homestay-bookings' ); ?></h1>
            <p style="color:#555;"><?php esc_html_e( 'Backup, migrate, or restore all your homestay listings. This exports and imports all homestay data including rooms, meta fields, and taxonomy terms.', 'himalayan-homestay-bookings' ); ?></p>

            <?php // Notices ?>
            <?php if ( isset( $_GET['import_success'] ) ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        ✅ <strong><?php esc_html_e( 'Import complete!', 'himalayan-homestay-bookings' ); ?></strong>
                        <?php printf(
                            esc_html__( '%d imported, %d updated, %d skipped (duplicates).', 'himalayan-homestay-bookings' ),
                            intval( $_GET['imported'] ?? 0 ),
                            intval( $_GET['updated'] ?? 0 ),
                            intval( $_GET['skipped'] ?? 0 )
                        ); ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if ( isset( $_GET['import_error'] ) ) : ?>
                <div class="notice notice-error is-dismissible">
                    <p>❌ <?php
                        $err = sanitize_text_field( $_GET['import_error'] );
                        if ( $err === 'no_file' ) esc_html_e( 'No file was uploaded. Please select a .json backup file.', 'himalayan-homestay-bookings' );
                        elseif ( $err === 'invalid_file' ) esc_html_e( 'Invalid file. Please upload a valid Himalayan Homestays JSON export.', 'himalayan-homestay-bookings' );
                    ?></p>
                </div>
            <?php endif; ?>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:24px;max-width:900px;">

                <!-- ── EXPORT ── -->
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:24px;box-shadow:0 1px 4px rgba(0,0,0,.06);">
                    <h2 style="margin-top:0;display:flex;align-items:center;gap:8px;">
                        <span style="font-size:24px;">📤</span>
                        <?php esc_html_e( 'Export Homestays', 'himalayan-homestay-bookings' ); ?>
                    </h2>
                    <p style="color:#555;font-size:13px;"><?php printf(
                        esc_html__( 'Download all %d homestay listings (including rooms and meta) as a JSON backup file.', 'himalayan-homestay-bookings' ),
                        intval( $total )
                    ); ?></p>
                    <ul style="color:#555;font-size:12px;margin:0 0 20px;padding-left:18px;">
                        <li><?php esc_html_e( 'Post title, content, slug, status', 'himalayan-homestay-bookings' ); ?></li>
                        <li><?php esc_html_e( 'All meta fields (price, location, amenities…)', 'himalayan-homestay-bookings' ); ?></li>
                        <li><?php esc_html_e( 'Taxonomy terms (Location, Property Type)', 'himalayan-homestay-bookings' ); ?></li>
                        <li><?php esc_html_e( 'Child rooms with their meta', 'himalayan-homestay-bookings' ); ?></li>
                        <li><?php esc_html_e( 'Verified guest reviews and ratings', 'himalayan-homestay-bookings' ); ?></li>
                        <li><?php esc_html_e( 'Gallery image URLs', 'himalayan-homestay-bookings' ); ?></li>
                    </ul>
                    <form method="post">
                        <?php wp_nonce_field( self::NONCE_EXPORT ); ?>
                        <input type="hidden" name="hhb_action" value="export">
                        <button type="submit" class="button button-primary" style="font-size:14px;padding:6px 20px;" <?php echo $total === 0 ? 'disabled' : ''; ?>>
                            ⬇️ <?php esc_html_e( 'Download JSON Backup', 'himalayan-homestay-bookings' ); ?>
                        </button>
                        <?php if ( $total === 0 ) : ?>
                            <p style="color:#888;font-size:12px;margin-top:8px;"><?php esc_html_e( 'No homestays found to export.', 'himalayan-homestay-bookings' ); ?></p>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- ── IMPORT ── -->
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:24px;box-shadow:0 1px 4px rgba(0,0,0,.06);">
                    <h2 style="margin-top:0;display:flex;align-items:center;gap:8px;">
                        <span style="font-size:24px;">📥</span>
                        <?php esc_html_e( 'Import Homestays', 'himalayan-homestay-bookings' ); ?>
                    </h2>
                    <p style="color:#555;font-size:13px;"><?php esc_html_e( 'Upload a previously exported JSON file to restore or migrate your homestay listings.', 'himalayan-homestay-bookings' ); ?></p>

                    <form method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field( self::NONCE_IMPORT ); ?>
                        <input type="hidden" name="hhb_action" value="import">

                        <div style="margin-bottom:16px;">
                            <label style="display:block;font-weight:600;font-size:13px;margin-bottom:6px;"><?php esc_html_e( 'Select backup file (.json)', 'himalayan-homestay-bookings' ); ?></label>
                            <input type="file" name="hhb_import_file" accept=".json" required style="width:100%;">
                        </div>

                        <div style="margin-bottom:20px;">
                            <label style="display:block;font-weight:600;font-size:13px;margin-bottom:6px;"><?php esc_html_e( 'If a homestay already exists…', 'himalayan-homestay-bookings' ); ?></label>
                            <label style="display:block;margin-bottom:4px;">
                                <input type="radio" name="import_mode" value="skip" checked>
                                <?php esc_html_e( 'Skip duplicate (keep existing)', 'himalayan-homestay-bookings' ); ?>
                            </label>
                            <label style="display:block;">
                                <input type="radio" name="import_mode" value="overwrite">
                                <?php esc_html_e( 'Overwrite with imported data', 'himalayan-homestay-bookings' ); ?>
                            </label>
                        </div>

                        <div style="background:#fff8e1;border-left:4px solid #fbc02d;padding:10px 14px;font-size:12px;color:#555;margin-bottom:16px;border-radius:0 4px 4px 0;">
                            ⚠️ <?php esc_html_e( 'Images are not re-uploaded — only URLs are referenced. Run this on the same or a matching server for full compatibility.', 'himalayan-homestay-bookings' ); ?>
                        </div>

                        <button type="submit" class="button button-primary" style="font-size:14px;padding:6px 20px;">
                            ⬆️ <?php esc_html_e( 'Import from JSON', 'himalayan-homestay-bookings' ); ?>
                        </button>
                    </form>
                </div>

            </div><!-- /grid -->
        </div>
        <?php
    }
}
