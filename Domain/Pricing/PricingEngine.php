<?php
/**
 * Pricing Engine
 *
 * Calculates booking prices day-by-day, applying seasonal rates, weekend
 * multipliers, extra guest fees, and extra services. This is the financial
 * core of the entire booking system.
 *
 * @package Himalayan\Homestay\Domain\Pricing
 */

namespace Himalayan\Homestay\Domain\Pricing;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PricingEngine {

    /**
     * Calculate the total price for a stay, applying all pricing rules.
     *
     * @param int    $homestay_id  The homestay post ID.
     * @param string $check_in     Check-in date (Y-m-d).
     * @param string $check_out    Check-out date (Y-m-d).
     * @param int    $guests       Number of guests.
     * @param string $coupon_code  Optional coupon code.
     * @return array Detailed price breakdown.
     */
    public function calculate_detailed_price( int $homestay_id, string $check_in, string $check_out, int $guests = 1, array $service_ids = [], string $coupon_code = '' ): array {
        $base_price  = (float) get_post_meta( $homestay_id, 'base_price_per_night', true );
        $offer_price = (float) get_post_meta( $homestay_id, 'offer_price_per_night', true );
        $max_guests  = (int) get_post_meta( $homestay_id, 'max_guests', true ) ?: 2;
        $extra_guest_fee = (float) get_post_meta( $homestay_id, 'hhb_extra_guest_fee', true );

        // Use offer price as base if available.
        $effective_base = ( $offer_price > 0 && $offer_price < $base_price ) ? $offer_price : $base_price;

        if ( $effective_base <= 0 ) {
            return [ 'total' => 0, 'nights' => 0, 'breakdown' => [], 'error' => 'No base price set.' ];
        }

        $date_start = new \DateTime( $check_in );
        $date_end   = new \DateTime( $check_out );
        $nights     = (int) $date_start->diff( $date_end )->days;

        if ( $nights < 1 ) {
            return [ 'total' => 0, 'nights' => 0, 'breakdown' => [], 'error' => 'Invalid date range.' ];
        }

        // -------------------------------------------------------------------
        // Fetch pricing rules.
        // -------------------------------------------------------------------
        global $wpdb;
        $rules_table = $wpdb->prefix . 'himalayan_pricing_rules';
        $rules       = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$rules_table} WHERE homestay_id = %d ORDER BY priority DESC",
            $homestay_id
        ) );

        // -------------------------------------------------------------------
        // Day-by-day calculation.
        // -------------------------------------------------------------------
        $breakdown    = [];
        $nightly_total = 0;
        $current_date = clone $date_start;

        for ( $i = 0; $i < $nights; $i++ ) {
            $date_str   = $current_date->format( 'Y-m-d' );
            $day_of_week = (int) $current_date->format( 'N' ); // 1=Mon, 7=Sun
            $night_price = $effective_base;
            $rule_applied = 'base';

            foreach ( $rules as $rule ) {
                $applies = false;

                if ( 'weekend' === $rule->rule_type ) {
                    // Weekend: default Fri(5), Sat(6) or custom days_of_week.
                    $weekend_days = ! empty( $rule->days_of_week )
                        ? array_map( 'intval', explode( ',', $rule->days_of_week ) )
                        : [ 5, 6 ];
                    $applies = in_array( $day_of_week, $weekend_days, true );
                } elseif ( 'seasonal' === $rule->rule_type ) {
                    // Seasonal: date must fall within range.
                    if ( $rule->start_date && $rule->end_date ) {
                        $applies = ( $date_str >= $rule->start_date && $date_str <= $rule->end_date );
                    }
                }

                if ( $applies ) {
                    switch ( $rule->modifier_type ) {
                        case 'override':
                            $night_price = (float) $rule->value;
                            break;
                        case 'percentage':
                            $night_price = $night_price * ( 1 + ( (float) $rule->value / 100 ) );
                            break;
                        case 'fixed':
                            $night_price += (float) $rule->value;
                            break;
                    }
                    $rule_applied = $rule->rule_type . ':' . $rule->modifier_type;
                    if ( ! $rule->stackable ) {
                        break; // Non-stackable = stop after first match.
                    }
                }
            }

            $breakdown[] = [
                'date'  => $date_str,
                'price' => round( $night_price, 2 ),
                'rule'  => $rule_applied,
            ];
            $nightly_total += $night_price;
            $current_date->modify( '+1 day' );
        }

        // -------------------------------------------------------------------
        // Extra guest fee.
        // -------------------------------------------------------------------
        $extra_guest_charge = 0;
        if ( $guests > $max_guests && $extra_guest_fee > 0 ) {
            $extra_persons = $guests - $max_guests;
            $extra_guest_charge = $extra_persons * $extra_guest_fee * $nights;
        }

        // -------------------------------------------------------------------
        // Extra services.
        // -------------------------------------------------------------------
        $services_total   = 0;
        $services_detail  = [];
        if ( ! empty( $service_ids ) ) {
            $services_table = $wpdb->prefix . 'himalayan_extra_services';
            $placeholders   = implode( ',', array_fill( 0, count( $service_ids ), '%d' ) );
            $query          = $wpdb->prepare(
                "SELECT * FROM {$services_table} WHERE id IN ($placeholders) AND is_active = 1",
                ...$service_ids
            );
            $services = $wpdb->get_results( $query );

            foreach ( $services as $svc ) {
                $svc_cost = (float) $svc->price;
                if ( 'per_night' === $svc->price_type ) {
                    $svc_cost = $svc_cost * $nights;
                } elseif ( 'per_guest' === $svc->price_type ) {
                    $svc_cost = $svc_cost * $guests;
                } elseif ( 'per_guest_per_night' === $svc->price_type ) {
                    $svc_cost = $svc_cost * $guests * $nights;
                }
                $services_detail[] = [
                    'id'    => $svc->id,
                    'name'  => $svc->service_name,
                    'cost'  => round( $svc_cost, 2 ),
                    'type'  => $svc->price_type,
                ];
                $services_total += $svc_cost;
            }
        }

        // -------------------------------------------------------------------
        // Coupon discount.
        // -------------------------------------------------------------------
        $grand_total   = round( $nightly_total + $extra_guest_charge + $services_total, 2 );
        $coupon_amount = 0;
        $coupon_detail = null;

        if ( ! empty( $coupon_code ) ) {
            $coupon_table = $wpdb->prefix . 'himalayan_coupons';
            $coupon       = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$coupon_table} WHERE code = %s AND is_active = 1",
                strtoupper( $coupon_code )
            ) );

            if ( $coupon ) {
                $is_valid = true;
                $today    = current_time( 'Y-m-d H:i:s' );

                if ( $coupon->max_uses > 0 && $coupon->used_count >= $coupon->max_uses ) {
                    $is_valid = false;
                }
                if ( $coupon->valid_from && $today < $coupon->valid_from ) {
                    $is_valid = false;
                }
                if ( $coupon->valid_to && $today > $coupon->valid_to ) {
                    $is_valid = false;
                }

                if ( $is_valid ) {
                    if ( 'percent' === $coupon->discount_type ) {
                        $coupon_amount = round( $grand_total * ( (float) $coupon->discount_value / 100 ), 2 );
                    } else {
                        $coupon_amount = (float) $coupon->discount_value;
                    }
                    
                    // Prevent negative total
                    $coupon_amount = min( $coupon_amount, $grand_total );
                    $grand_total  -= $coupon_amount;

                    $coupon_detail = [
                        'code'   => $coupon->code,
                        'amount' => $coupon_amount,
                        'type'   => $coupon->discount_type,
                        'value'  => $coupon->discount_value,
                    ];
                }
            }
        }

        // -------------------------------------------------------------------
        // Deposit calculation.
        // -------------------------------------------------------------------
        $deposit_percent = (float) get_post_meta( $homestay_id, 'hhb_deposit_percent', true );
        $deposit_amount  = ( $deposit_percent > 0 ) ? round( $grand_total * ( $deposit_percent / 100 ), 2 ) : $grand_total;
        $balance_due     = round( $grand_total - $deposit_amount, 2 );

        return [
            'nights'             => $nights,
            'base_per_night'     => $effective_base,
            'nightly_total'      => round( $nightly_total, 2 ),
            'extra_guest_charge' => round( $extra_guest_charge, 2 ),
            'services_total'     => round( $services_total, 2 ),
            'services_detail'    => $services_detail,
            'coupon_amount'      => round( $coupon_amount, 2 ),
            'coupon_detail'      => $coupon_detail,
            'grand_total'        => $grand_total,
            'deposit_amount'     => $deposit_amount,
            'deposit_percent'    => $deposit_percent,
            'balance_due'        => $balance_due,
            'breakdown'          => $breakdown,
            'currency'           => get_post_meta( $homestay_id, 'currency', true ) ?: 'INR',
        ];
    }

    /**
     * Simplified total-only calculation (backwards compatible).
     */
    public function calculate_price( $homestay_id, $check_in, $check_out, $guests = 1 ): float {
        $result = $this->calculate_detailed_price( (int) $homestay_id, $check_in, $check_out, (int) $guests );
        return $result['grand_total'] ?? 0;
    }
}
