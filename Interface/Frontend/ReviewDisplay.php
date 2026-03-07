<?php
/**
 * Frontend Review Display
 *
 * Appends aggregated ratings and recent guest reviews
 * to the bottom of the single homestay content.
 *
 * @package Himalayan\Homestay\Interface\Frontend
 */

namespace Himalayan\Homestay\Interface\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ReviewDisplay {

    public static function init(): void {
        // AJAX hooks must be registered on every request — admin-ajax.php has is_admin() = true.
        add_action( 'wp_ajax_hhb_load_reviews',           [ __CLASS__, 'ajax_load_reviews' ] );
        add_action( 'wp_ajax_nopriv_hhb_load_reviews',    [ __CLASS__, 'ajax_load_reviews' ] );
        add_action( 'wp_ajax_hhb_submit_review_frontend', [ __CLASS__, 'ajax_submit_review' ] );
        add_action( 'wp_ajax_hhb_update_review_frontend', [ __CLASS__, 'ajax_update_review' ] );

        // Content filter only needed on the frontend.
        if ( ! is_admin() ) {
            add_filter( 'the_content', [ __CLASS__, 'append_reviews_to_content' ] );
        }
    }

    public static function ajax_load_reviews(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'hhb_reviews';

        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            wp_send_json_error( 'Reviews table not found.' );
        }

        $reviews = $wpdb->get_results(
            "SELECT r.*, p.post_title AS homestay_name
             FROM {$table} r
             LEFT JOIN {$wpdb->posts} p ON r.homestay_id = p.ID
             WHERE r.status = 'approved'
             ORDER BY r.created_at DESC
             LIMIT 6"
        );

        if ( empty( $reviews ) ) {
            wp_send_json_error( 'No reviews found.' );
        }

        ob_start();
        foreach ( $reviews as $review ) :
            $initial   = esc_html( strtoupper( substr( $review->customer_name, 0, 1 ) ) );
            $name      = esc_html( $review->customer_name );
            $comment   = esc_html( wp_trim_words( $review->comment, 30, '…' ) );
            $property  = esc_html( $review->homestay_name );
            $rating    = (int) $review->rating;
        ?>
        <div class="hhb-review-card opacity-0 translate-y-4 transition-all duration-700 ease-out">
            <div class="hhb-review-stars mb-3">
                <?php for ( $s = 1; $s <= 5; $s++ ) : ?>
                    <span class="material-symbols-outlined text-base" style="font-variation-settings: 'FILL' <?php echo $s <= $rating ? '1' : '0'; ?>">star</span>
                <?php endfor; ?>
            </div>
            <p class="text-slate-600 text-sm italic leading-relaxed mb-5">
                "<?php echo $comment; ?>"
            </p>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary font-black text-sm">
                    <?php echo $initial; ?>
                </div>
                <div>
                    <p class="text-sm font-bold text-slate-900"><?php echo $name; ?></p>
                    <?php if ( $property ) : ?>
                        <p class="text-[11px] text-slate-400"><?php echo $property; ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach;

        wp_send_json_success( ob_get_clean() );
    }

    public static function append_reviews_to_content( $content ) {
        if ( ! is_singular( 'hhb_homestay' ) || ! in_the_loop() || ! is_main_query() ) {
            return $content;
        }

        $homestay_id  = get_the_ID();
        $reviews_html = self::generate_reviews_html( $homestay_id );
        $form_html    = self::generate_user_review_form( $homestay_id );

        return $content . $reviews_html . $form_html;
    }

    private static function generate_reviews_html( int $homestay_id ): string {
        global $wpdb;
        $table = $wpdb->prefix . 'hhb_reviews';

        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            return '';
        }

        $transient_key = 'hhb_reviews_' . $homestay_id;
        $reviews       = get_transient( $transient_key );

        if ( false === $reviews ) {
            $reviews = $wpdb->get_results( $wpdb->prepare( "
                SELECT customer_name, rating, rating_cleanliness, rating_communication, rating_location, rating_value, comment, created_at
                FROM {$table}
                WHERE homestay_id = %d AND status = 'approved'
                ORDER BY created_at DESC
            ", $homestay_id ) );
            
            set_transient( $transient_key, $reviews, 12 * HOUR_IN_SECONDS );
        }

        if ( empty( $reviews ) ) {
            return '';
        }

        $total_reviews = count( $reviews );
        $sum_rating    = 0;
        $sum_clean     = 0;
        $sum_comm      = 0;
        $sum_loc       = 0;
        $sum_val       = 0;

        foreach ( $reviews as $r ) {
            $sum_rating += (int) $r->rating;
            $sum_clean  += (int) $r->rating_cleanliness;
            $sum_comm   += (int) $r->rating_communication;
            $sum_loc    += (int) $r->rating_location;
            $sum_val    += (int) $r->rating_value;
        }

        $avg_rating = round( $sum_rating / $total_reviews, 1 );
        $avg_clean  = round( $sum_clean / $total_reviews, 1 );
        $avg_comm   = round( $sum_comm / $total_reviews, 1 );
        $avg_loc    = round( $sum_loc / $total_reviews, 1 );
        $avg_val    = round( $sum_val / $total_reviews, 1 );

        // Rating breakdown
        $breakdown = [ 5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0 ];
        foreach ( $reviews as $r ) {
            $star = max( 1, min( 5, (int) $r->rating ) );
            $breakdown[ $star ]++;
        }

        ob_start();
        ?>
        <style>
        .hhb-reviews-section { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        .hhb-star-fill  { color: #e85e30; }
        .hhb-star-empty { color: #e2e8f0; }
        .hhb-review-bar-fill { background: #e85e30; border-radius: 9999px; height: 6px; transition: width 0.4s; }
        .hhb-review-bar-bg   { background: #f1f5f9; border-radius: 9999px; height: 6px; overflow: hidden; flex: 1; }
        </style>

        <div class="hhb-reviews-section" style="margin-top:48px; padding-top:40px; border-top:1px solid #f1f5f9;">

            <!-- Header -->
            <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px; margin-bottom:32px;">
                <div>
                    <h3 style="margin:0 0 4px; font-size:22px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;">
                        <span style="color:#e85e30; font-size:20px;">★</span>
                        <?php echo esc_html( number_format( $avg_rating, 1 ) ); ?>
                        <span style="color:#64748b; font-weight:500; font-size:16px;">·</span>
                        <span style="font-weight:600; font-size:16px; color:#334155;">
                            <?php echo esc_html( sprintf( _n( '%d Review', '%d Reviews', $total_reviews, 'himalayan-homestay-bookings' ), $total_reviews ) ); ?>
                        </span>
                    </h3>
                    <p style="margin:0; font-size:13px; color:#94a3b8; font-weight:500;">Verified guest experiences</p>
                </div>

                <!-- Rating Breakdown bars -->
                <div style="display:flex; flex-direction:column; gap:6px; min-width:220px;">
                    <?php for ( $star = 5; $star >= 1; $star-- ) :
                        $pct = $total_reviews > 0 ? round( ( $breakdown[ $star ] / $total_reviews ) * 100 ) : 0;
                    ?>
                    <div style="display:flex; align-items:center; gap:10px; font-size:12px; font-weight:600; color:#64748b;">
                        <span style="width:8px; text-align:right;"><?php echo $star; ?></span>
                        <span style="color:#e85e30; font-size:11px;">★</span>
                        <div class="hhb-review-bar-bg">
                            <div class="hhb-review-bar-fill" style="width:<?php echo $pct; ?>%;"></div>
                        </div>
                        <span style="width:28px;"><?php echo $pct; ?>%</span>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Axis Breakdown -->
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px 40px; margin-bottom:40px; padding-bottom:32px; border-bottom:1px solid #e2e8f0;">
                <div style="display:flex; flex-direction:column; gap:6px;">
                    <div style="display:flex; justify-content:space-between; font-size:14px; color:#475569;">
                        <span><?php esc_html_e('Cleanliness', 'himalayan-homestay-bookings'); ?></span>
                        <span style="font-weight:600; color:#0f172a;"><?php echo number_format($avg_clean, 1); ?></span>
                    </div>
                    <div class="hhb-review-bar-bg"><div class="hhb-review-bar-fill" style="width:<?php echo ($avg_clean / 5) * 100; ?>%; background:#0f172a;"></div></div>
                </div>
                <div style="display:flex; flex-direction:column; gap:6px;">
                    <div style="display:flex; justify-content:space-between; font-size:14px; color:#475569;">
                        <span><?php esc_html_e('Communication', 'himalayan-homestay-bookings'); ?></span>
                        <span style="font-weight:600; color:#0f172a;"><?php echo number_format($avg_comm, 1); ?></span>
                    </div>
                    <div class="hhb-review-bar-bg"><div class="hhb-review-bar-fill" style="width:<?php echo ($avg_comm / 5) * 100; ?>%; background:#0f172a;"></div></div>
                </div>
                <div style="display:flex; flex-direction:column; gap:6px;">
                    <div style="display:flex; justify-content:space-between; font-size:14px; color:#475569;">
                        <span><?php esc_html_e('Location', 'himalayan-homestay-bookings'); ?></span>
                        <span style="font-weight:600; color:#0f172a;"><?php echo number_format($avg_loc, 1); ?></span>
                    </div>
                    <div class="hhb-review-bar-bg"><div class="hhb-review-bar-fill" style="width:<?php echo ($avg_loc / 5) * 100; ?>%; background:#0f172a;"></div></div>
                </div>
                <div style="display:flex; flex-direction:column; gap:6px;">
                    <div style="display:flex; justify-content:space-between; font-size:14px; color:#475569;">
                        <span><?php esc_html_e('Value', 'himalayan-homestay-bookings'); ?></span>
                        <span style="font-weight:600; color:#0f172a;"><?php echo number_format($avg_val, 1); ?></span>
                    </div>
                    <div class="hhb-review-bar-bg"><div class="hhb-review-bar-fill" style="width:<?php echo ($avg_val / 5) * 100; ?>%; background:#0f172a;"></div></div>
                </div>
            </div>

            <!-- Review Grid -->
            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:20px;">
                <?php foreach ( $reviews as $review ) :
                    $date   = date_i18n( get_option( 'date_format' ), strtotime( $review->created_at ) );
                    $rating = max( 1, min( 5, (int) $review->rating ) );
                    // Generate a warm avatar colour from name
                    $colours = ['#e85e30','#f97316','#10b981','#3b82f6','#8b5cf6','#ec4899'];
                    $avatar_bg = $colours[ abs( crc32( $review->customer_name ) ) % count( $colours ) ];
                    $initial   = mb_strtoupper( mb_substr( $review->customer_name, 0, 1 ) );
                ?>
                <div style="background:#fff; border:1px solid #f1f5f9; border-radius:16px; padding:24px; box-shadow:0 2px 12px rgba(0,0,0,0.04); transition:box-shadow 0.2s;" onmouseover="this.style.boxShadow='0 8px 30px rgba(0,0,0,0.09)'" onmouseout="this.style.boxShadow='0 2px 12px rgba(0,0,0,0.04)'">
                    <!-- Reviewer -->
                    <div style="display:flex; align-items:center; gap:12px; margin-bottom:14px;">
                        <div style="width:44px; height:44px; border-radius:50%; background:<?php echo esc_attr($avatar_bg); ?>; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:17px; color:#fff; flex-shrink:0; letter-spacing:-0.5px;">
                            <?php echo esc_html( $initial ); ?>
                        </div>
                        <div>
                            <div style="font-weight:700; font-size:14px; color:#0f172a;"><?php echo esc_html( $review->customer_name ); ?></div>
                            <div style="font-size:11px; color:#94a3b8; margin-top:1px;"><?php echo esc_html( $date ); ?></div>
                        </div>
                        <!-- Stars pushed right -->
                        <div style="margin-left:auto; display:flex; gap:2px; font-size:14px;">
                            <?php for ( $i = 1; $i <= 5; $i++ ) : ?>
                                <span class="<?php echo $i <= $rating ? 'hhb-star-fill' : 'hhb-star-empty'; ?>">★</span>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- Comment -->
                    <p style="margin:0; font-size:14px; line-height:1.7; color:#475569;">
                        <?php echo nl2br( esc_html( $review->comment ) ); ?>
                    </p>

                    <!-- Verified badge -->
                    <div style="margin-top:14px; display:inline-flex; align-items:center; gap:4px; background:#f0fdf4; color:#16a34a; font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px;">
                        <svg style="width:11px;height:11px;" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                        Verified Stay
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }

    // =========================================================================
    // USER REVIEW FORM — create or edit own review
    // =========================================================================

    private static function generate_user_review_form( int $homestay_id ): string {
        global $wpdb;
        $table          = $wpdb->prefix . 'hhb_reviews';
        $table_bookings = $wpdb->prefix . 'himalayan_bookings';

        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            return '';
        }

        $login_url = wp_login_url( get_permalink( $homestay_id ) );

        // Not logged in — don't show anything.
        if ( ! is_user_logged_in() ) {
            return '';
        }

        $user       = wp_get_current_user();
        $user_email = $user->user_email;

        // Check for a confirmed + checked-out booking.
        $booking = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, customer_name FROM {$table_bookings}
             WHERE homestay_id = %d AND customer_email = %s
               AND status = 'confirmed' AND check_out <= %s
             ORDER BY check_out DESC LIMIT 1",
            $homestay_id, $user_email, current_time( 'Y-m-d' )
        ) );

        if ( ! $booking ) {
            return ''; // No completed stay — don't show form.
        }

        // Check for existing review.
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, rating, rating_cleanliness, rating_communication,
                    rating_location, rating_value, comment, status
             FROM {$table}
             WHERE homestay_id = %d AND customer_email = %s
             ORDER BY created_at DESC LIMIT 1",
            $homestay_id, $user_email
        ) );

        $is_edit   = ! empty( $existing );
        $nonce_key = $is_edit ? 'hhb_update_review_' . $existing->id : 'hhb_submit_review_' . $homestay_id;
        $action    = $is_edit ? 'hhb_update_review_frontend' : 'hhb_submit_review_frontend';

        // Pre-fill values for edit.
        $val_rating = $is_edit ? (int) $existing->rating               : 0;
        $val_clean  = $is_edit ? (int) $existing->rating_cleanliness   : 5;
        $val_comm   = $is_edit ? (int) $existing->rating_communication : 5;
        $val_loc    = $is_edit ? (int) $existing->rating_location      : 5;
        $val_value  = $is_edit ? (int) $existing->rating_value         : 5;
        $val_comment= $is_edit ? esc_textarea( $existing->comment )    : '';

        ob_start();
        ?>
        <style>
        .hhb-urf { font-family:'Inter',-apple-system,BlinkMacSystemFont,sans-serif; margin-top:48px; padding-top:40px; border-top:1px solid #f1f5f9; }
        .hhb-urf-card { background:#fff; border:1px solid #e2e8f0; border-radius:20px; padding:28px; box-shadow:0 2px 16px rgba(0,0,0,0.05); max-width:680px; }
        .hhb-urf h3 { margin:0 0 4px; font-size:20px; font-weight:800; color:#0f172a; }
        .hhb-urf-sub { font-size:13px; color:#64748b; margin:0 0 24px; }
        .hhb-urf-stars { display:inline-flex; flex-direction:row-reverse; }
        .hhb-urf-stars input { display:none; }
        .hhb-urf-stars label { font-size:34px; color:#d1d5db; cursor:pointer; padding-right:4px; transition:color .15s; }
        .hhb-urf-stars label:before { content:'★'; }
        .hhb-urf-stars input:checked ~ label,
        .hhb-urf-stars label:hover,
        .hhb-urf-stars label:hover ~ label { color:#f59e0b; }
        .hhb-urf-sub-stars { display:inline-flex; flex-direction:row-reverse; }
        .hhb-urf-sub-stars input { display:none; }
        .hhb-urf-sub-stars label { font-size:22px; color:#d1d5db; cursor:pointer; padding-right:2px; transition:color .15s; }
        .hhb-urf-sub-stars label:before { content:'★'; }
        .hhb-urf-sub-stars input:checked ~ label,
        .hhb-urf-sub-stars label:hover,
        .hhb-urf-sub-stars label:hover ~ label { color:#f59e0b; }
        .hhb-urf-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:20px; padding:16px; background:#f8fafc; border-radius:12px; }
        @media(max-width:480px) { .hhb-urf-grid { grid-template-columns:1fr; } }
        .hhb-urf-field label { display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.5px; }
        .hhb-urf textarea { width:100%; padding:12px; border:1px solid #e2e8f0; border-radius:10px; font-size:14px; font-family:inherit; resize:vertical; min-height:100px; box-sizing:border-box; transition:border-color .2s; }
        .hhb-urf textarea:focus { border-color:#e85e30; outline:none; box-shadow:0 0 0 3px rgba(232,94,48,.1); }
        .hhb-urf-btn { display:inline-flex; align-items:center; gap:8px; background:#e85e30; color:#fff; border:none; border-radius:10px; padding:12px 28px; font-size:15px; font-weight:700; cursor:pointer; font-family:inherit; transition:all .2s; margin-top:8px; }
        .hhb-urf-btn:hover { background:#c94d22; transform:translateY(-1px); }
        .hhb-urf-btn:disabled { opacity:.6; cursor:not-allowed; transform:none; }
        .hhb-urf-msg { margin-top:14px; padding:12px 16px; border-radius:10px; font-size:14px; font-weight:600; display:none; }
        .hhb-urf-msg.success { background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0; }
        .hhb-urf-msg.error   { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; }
        .hhb-urf-status { display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:700; padding:3px 10px; border-radius:20px; margin-bottom:16px; }
        .hhb-urf-status.approved { background:#f0fdf4; color:#16a34a; }
        .hhb-urf-status.pending  { background:#fefce8; color:#a16207; }
        </style>

        <div class="hhb-urf">
            <div class="hhb-urf-card">
                <h3><?php echo $is_edit ? esc_html__( 'Edit Your Review', 'himalayan-homestay-bookings' ) : esc_html__( 'Write a Review', 'himalayan-homestay-bookings' ); ?></h3>
                <p class="hhb-urf-sub">
                    <?php if ( $is_edit ) : ?>
                        <?php esc_html_e( 'Update your experience below.', 'himalayan-homestay-bookings' ); ?>
                        <span class="hhb-urf-status <?php echo esc_attr( $existing->status ); ?>">
                            <?php echo $existing->status === 'approved' ? '✓ Published' : '⏳ Pending approval'; ?>
                        </span>
                    <?php else : ?>
                        <?php printf( esc_html__( 'Hi %s, share your experience to help other travelers.', 'himalayan-homestay-bookings' ), esc_html( $booking->customer_name ) ); ?>
                    <?php endif; ?>
                </p>

                <form id="hhb-urf-form" data-action="<?php echo esc_attr( $action ); ?>">
                    <?php wp_nonce_field( $nonce_key, 'hhb_urf_nonce' ); ?>
                    <input type="hidden" name="homestay_id" value="<?php echo esc_attr( $homestay_id ); ?>">
                    <?php if ( $is_edit ) : ?>
                        <input type="hidden" name="review_id" value="<?php echo esc_attr( $existing->id ); ?>">
                    <?php endif; ?>

                    <!-- Overall rating -->
                    <div class="hhb-urf-field" style="margin-bottom:20px; text-align:center;">
                        <label style="font-size:14px; text-transform:uppercase; letter-spacing:1px; color:#0f172a; font-weight:800;"><?php esc_html_e( 'Overall Experience', 'himalayan-homestay-bookings' ); ?></label>
                        <div class="hhb-urf-stars" style="justify-content:center; display:flex; margin-top:8px;">
                            <?php for ( $s = 5; $s >= 1; $s-- ) : ?>
                                <input type="radio" name="rating" id="hhb-urf-star<?php echo $s; ?>" value="<?php echo $s; ?>" <?php checked( $val_rating, $s ); ?> required>
                                <label for="hhb-urf-star<?php echo $s; ?>"></label>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- Sub-ratings -->
                    <div class="hhb-urf-grid">
                        <?php
                        $sub_ratings = [
                            'rating_cleanliness'   => [ 'Cleanliness',   'cl',  $val_clean ],
                            'rating_communication' => [ 'Communication', 'cm',  $val_comm  ],
                            'rating_location'      => [ 'Location',      'lc',  $val_loc   ],
                            'rating_value'         => [ 'Value',         'vl',  $val_value ],
                        ];
                        foreach ( $sub_ratings as $field => [ $label, $prefix, $current ] ) : ?>
                        <div class="hhb-urf-field">
                            <label><?php echo esc_html( $label ); ?></label>
                            <div class="hhb-urf-sub-stars">
                                <?php for ( $s = 5; $s >= 1; $s-- ) : ?>
                                    <input type="radio" name="<?php echo esc_attr( $field ); ?>" id="hhb-<?php echo $prefix . $s; ?>" value="<?php echo $s; ?>" <?php checked( $current, $s ); ?>>
                                    <label for="hhb-<?php echo $prefix . $s; ?>"></label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Comment -->
                    <div class="hhb-urf-field" style="margin-bottom:20px;">
                        <label><?php esc_html_e( 'Your Review', 'himalayan-homestay-bookings' ); ?></label>
                        <textarea name="comment" placeholder="<?php esc_attr_e( 'Tell us about your stay…', 'himalayan-homestay-bookings' ); ?>" required><?php echo $val_comment; ?></textarea>
                    </div>

                    <button type="submit" class="hhb-urf-btn" id="hhb-urf-submit">
                        <span><?php echo $is_edit ? esc_html__( 'Update Review', 'himalayan-homestay-bookings' ) : esc_html__( 'Submit Review', 'himalayan-homestay-bookings' ); ?></span>
                    </button>
                    <div class="hhb-urf-msg" id="hhb-urf-msg"></div>
                </form>
            </div>
        </div>

        <script>
        (function() {
            var form = document.getElementById('hhb-urf-form');
            var btn  = document.getElementById('hhb-urf-submit');
            var msg  = document.getElementById('hhb-urf-msg');
            if (!form) return;

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var rating = form.querySelector('input[name="rating"]:checked');
                if (!rating) {
                    msg.textContent = 'Please select an overall rating.';
                    msg.className   = 'hhb-urf-msg error';
                    msg.style.display = 'block';
                    return;
                }

                btn.disabled = true;
                btn.querySelector('span').textContent = '<?php echo $is_edit ? esc_js( __( 'Updating…', 'himalayan-homestay-bookings' ) ) : esc_js( __( 'Submitting…', 'himalayan-homestay-bookings' ) ); ?>';
                msg.style.display = 'none';

                var data = new FormData(form);
                data.append('action', form.dataset.action);

                fetch('<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', {
                    method: 'POST',
                    body: data
                })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        msg.textContent   = res.data.message;
                        msg.className     = 'hhb-urf-msg success';
                        msg.style.display = 'block';
                        btn.querySelector('span').textContent = res.data.btn_label;
                        // If new submission, hide the form fields.
                        <?php if ( ! $is_edit ) : ?>
                        form.querySelectorAll('input:not([type=hidden]), textarea, button').forEach(function(el){ el.style.display='none'; });
                        <?php endif; ?>
                    } else {
                        msg.textContent   = res.data || 'Something went wrong. Please try again.';
                        msg.className     = 'hhb-urf-msg error';
                        msg.style.display = 'block';
                        btn.disabled      = false;
                        btn.querySelector('span').textContent = '<?php echo $is_edit ? esc_js( __( 'Update Review', 'himalayan-homestay-bookings' ) ) : esc_js( __( 'Submit Review', 'himalayan-homestay-bookings' ) ); ?>';
                    }
                })
                .catch(function() {
                    msg.textContent   = 'Network error. Please try again.';
                    msg.className     = 'hhb-urf-msg error';
                    msg.style.display = 'block';
                    btn.disabled      = false;
                    btn.querySelector('span').textContent = '<?php echo $is_edit ? esc_js( __( 'Update Review', 'himalayan-homestay-bookings' ) ) : esc_js( __( 'Submit Review', 'himalayan-homestay-bookings' ) ); ?>';
                });
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    // ── AJAX: Submit new review from frontend ─────────────────────────────────
    public static function ajax_submit_review(): void {
        $homestay_id = intval( $_POST['homestay_id'] ?? 0 );
        if ( ! wp_verify_nonce( $_POST['hhb_urf_nonce'] ?? '', 'hhb_submit_review_' . $homestay_id ) ) {
            wp_send_json_error( 'Security check failed.' );
        }
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'You must be logged in.' );
        }

        global $wpdb;
        $table          = $wpdb->prefix . 'hhb_reviews';
        $table_bookings = $wpdb->prefix . 'himalayan_bookings';
        $user_email     = wp_get_current_user()->user_email;

        // Verify a completed booking exists.
        $booking = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, customer_name FROM {$table_bookings}
             WHERE homestay_id = %d AND customer_email = %s
               AND status = 'confirmed' AND check_out <= %s LIMIT 1",
            $homestay_id, $user_email, current_time( 'Y-m-d' )
        ) );
        if ( ! $booking ) {
            wp_send_json_error( 'You must have completed a stay to leave a review.' );
        }

        // Prevent duplicates.
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE homestay_id = %d AND customer_email = %s LIMIT 1",
            $homestay_id, $user_email
        ) );
        if ( $exists ) {
            wp_send_json_error( 'You have already reviewed this property.' );
        }

        $rating  = max( 1, min( 5, intval( $_POST['rating'] ?? 0 ) ) );
        $comment = sanitize_textarea_field( wp_unslash( $_POST['comment'] ?? '' ) );
        if ( ! $rating || empty( $comment ) ) {
            wp_send_json_error( 'Please provide a rating and a comment.' );
        }

        $wpdb->insert( $table, [
            'booking_id'           => $booking->id,
            'homestay_id'          => $homestay_id,
            'customer_name'        => $booking->customer_name,
            'customer_email'       => $user_email,
            'rating'               => $rating,
            'rating_cleanliness'   => max( 1, min( 5, intval( $_POST['rating_cleanliness']   ?? 5 ) ) ),
            'rating_communication' => max( 1, min( 5, intval( $_POST['rating_communication'] ?? 5 ) ) ),
            'rating_location'      => max( 1, min( 5, intval( $_POST['rating_location']      ?? 5 ) ) ),
            'rating_value'         => max( 1, min( 5, intval( $_POST['rating_value']         ?? 5 ) ) ),
            'comment'              => $comment,
            'status'               => 'approved',
            'created_at'           => current_time( 'mysql' ),
        ] );

        delete_transient( 'hhb_reviews_' . $homestay_id );

        wp_send_json_success( [
            'message'   => __( 'Thank you! Your review has been published.', 'himalayan-homestay-bookings' ),
            'btn_label' => __( 'Review Submitted ✓', 'himalayan-homestay-bookings' ),
        ] );
    }

    // ── AJAX: Update existing review from frontend ────────────────────────────
    public static function ajax_update_review(): void {
        $review_id = intval( $_POST['review_id'] ?? 0 );
        if ( ! wp_verify_nonce( $_POST['hhb_urf_nonce'] ?? '', 'hhb_update_review_' . $review_id ) ) {
            wp_send_json_error( 'Security check failed.' );
        }
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'You must be logged in.' );
        }

        global $wpdb;
        $table      = $wpdb->prefix . 'hhb_reviews';
        $user_email = wp_get_current_user()->user_email;

        // Verify ownership.
        $review = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, homestay_id FROM {$table} WHERE id = %d AND customer_email = %s LIMIT 1",
            $review_id, $user_email
        ) );
        if ( ! $review ) {
            wp_send_json_error( 'Review not found or permission denied.' );
        }

        $rating  = max( 1, min( 5, intval( $_POST['rating'] ?? 0 ) ) );
        $comment = sanitize_textarea_field( wp_unslash( $_POST['comment'] ?? '' ) );
        if ( ! $rating || empty( $comment ) ) {
            wp_send_json_error( 'Please provide a rating and a comment.' );
        }

        $wpdb->update(
            $table,
            [
                'rating'               => $rating,
                'rating_cleanliness'   => max( 1, min( 5, intval( $_POST['rating_cleanliness']   ?? 5 ) ) ),
                'rating_communication' => max( 1, min( 5, intval( $_POST['rating_communication'] ?? 5 ) ) ),
                'rating_location'      => max( 1, min( 5, intval( $_POST['rating_location']      ?? 5 ) ) ),
                'rating_value'         => max( 1, min( 5, intval( $_POST['rating_value']         ?? 5 ) ) ),
                'comment'              => $comment,
            ],
            [ 'id' => $review_id ],
            [ '%d', '%d', '%d', '%d', '%d', '%s' ],
            [ '%d' ]
        );

        delete_transient( 'hhb_reviews_' . $review->homestay_id );

        wp_send_json_success( [
            'message'   => __( 'Your review has been updated.', 'himalayan-homestay-bookings' ),
            'btn_label' => __( 'Update Review', 'himalayan-homestay-bookings' ),
        ] );
    }
}
