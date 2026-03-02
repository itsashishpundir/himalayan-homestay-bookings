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
        add_filter( 'the_content', [ __CLASS__, 'append_reviews_to_content' ] );
    }

    public static function append_reviews_to_content( $content ) {
        if ( ! is_singular( 'hhb_homestay' ) || ! in_the_loop() || ! is_main_query() ) {
            return $content;
        }

        $homestay_id  = get_the_ID();
        $reviews_html = self::generate_reviews_html( $homestay_id );

        return $content . $reviews_html;
    }

    private static function generate_reviews_html( int $homestay_id ): string {
        global $wpdb;
        $table = $wpdb->prefix . 'hhb_reviews';

        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) != $table ) {
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
}
