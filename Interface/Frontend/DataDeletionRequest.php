<?php
/**
 * Data Deletion Request Feature
 *
 * Provides a shortcode [hhb_data_deletion_request] to generate a frontend form 
 * allowing users to request their PII be erased under GDPR/CCPA.
 *
 * @package Himalayan\Homestay\Interface\Frontend
 */

namespace Himalayan\Homestay\Interface\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DataDeletionRequest {

    public static function init(): void {
        add_shortcode( 'hhb_data_deletion_request', [ __CLASS__, 'render_shortcode' ] );
        add_action( 'wp_ajax_hhb_submit_deletion_request', [ __CLASS__, 'handle_submission' ] );
        add_action( 'wp_ajax_nopriv_hhb_submit_deletion_request', [ __CLASS__, 'handle_submission' ] );
    }

    public static function render_shortcode( $atts = [] ): string {
        wp_enqueue_script( 'jquery' );
        
        $html = '<div class="hhb-deletion-container" style="max-width: 500px; margin: 0 auto; padding: 30px; background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);">';
        $html .= '<h3 style="margin-top: 0;">' . esc_html__( 'Request Data Deletion', 'himalayan-homestay-bookings' ) . '</h3>';
        $html .= '<p style="color: #666; font-size: 14px; margin-bottom: 20px;">' . esc_html__( 'Under GDPR and CCPA, you have the right to request that we erase your personal data from our systems. Please enter the email address associated with your bookings below.', 'himalayan-homestay-bookings' ) . '</p>';
        
        $html .= '<form id="hhb-deletion-form" method="post">';
        $html .= wp_nonce_field( 'hhb_deletion_nonce', 'security', true, false );
        
        $html .= '<div style="margin-bottom: 15px;">';
        $html .= '<label for="hhb_del_email" style="display: block; margin-bottom: 5px; font-weight: 600;">' . esc_html__( 'Email Address', 'himalayan-homestay-bookings' ) . ' <span style="color:red;">*</span></label>';
        $html .= '<input type="email" id="hhb_del_email" name="email" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">';
        $html .= '</div>';
        
        $html .= '<div style="margin-bottom: 20px;">';
        $html .= '<label style="display: flex; align-items: flex-start; gap: 10px; font-size: 14px; color: #555;">';
        $html .= '<input type="checkbox" required name="confirm" style="margin-top: 4px;">';
        $html .= '<span>' . esc_html__( 'I understand that this action is irreversible and will result in the deletion of my personal booking history.', 'himalayan-homestay-bookings' ) . '</span>';
        $html .= '</label>';
        $html .= '</div>';
        
        $html .= '<button type="submit" id="hhb-del-btn" style="width: 100%; padding: 12px; background: #dc3232; color: #fff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background 0.2s;">';
        $html .= esc_html__( 'Submit Deletion Request', 'himalayan-homestay-bookings' );
        $html .= '</button>';
        
        $html .= '<div id="hhb-del-response" style="margin-top: 15px; display: none; padding: 12px; border-radius: 6px; font-size: 14px;"></div>';
        
        $html .= '</form>';
        $html .= '</div>';

        // Inline JS for handling submission dynamically.
        $html .= '<script>
        jQuery(document).ready(function($) {
            $("#hhb-deletion-form").on("submit", function(e) {
                e.preventDefault();
                var $form = $(this);
                var $btn = $("#hhb-del-btn");
                var $res = $("#hhb-del-response");
                
                $btn.prop("disabled", true).css("opacity", "0.6").text("' . esc_attr__( 'Sending...', 'himalayan-homestay-bookings' ) . '");
                $res.hide().removeClass("hhb-error hhb-success");
                
                var data = {
                    action: "hhb_submit_deletion_request",
                    email: $("#hhb_del_email").val(),
                    security: $("#security").val()
                };
                
                $.post("' . admin_url( 'admin-ajax.php' ) . '", data, function(response) {
                    $btn.prop("disabled", false).css("opacity", "1").text("' . esc_attr__( 'Submit Deletion Request', 'himalayan-homestay-bookings' ) . '");
                    $res.show();
                    if (response.success) {
                        $res.css({"background": "#edfaed", "color": "#1a7c1a", "border": "1px solid #c3ecc3"}).text(response.data.message);
                        $form[0].reset();
                    } else {
                        $res.css({"background": "#faeded", "color": "#dc3232", "border": "1px solid #ecc3c3"}).text(response.data);
                    }
                }).fail(function() {
                    $btn.prop("disabled", false).css("opacity", "1").text("' . esc_attr__( 'Submit Deletion Request', 'himalayan-homestay-bookings' ) . '");
                    $res.show().css({"background": "#faeded", "color": "#dc3232", "border": "1px solid #ecc3c3"}).text("' . esc_attr__( 'Server error. Please try again later.', 'himalayan-homestay-bookings' ) . '");
                });
            });
        });
        </script>';

        return $html;
    }

    public static function handle_submission(): void {
        check_ajax_referer( 'hhb_deletion_nonce', 'security' );

        $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        
        if ( ! is_email( $email ) ) {
            wp_send_json_error( __( 'Please provide a valid email address.', 'himalayan-homestay-bookings' ) );
        }

        // We leverage WordPress's native User Data Erasure API by creating a formal request.
        $request_id = wp_create_user_request( $email, 'remove_personal_data' );

        if ( is_wp_error( $request_id ) ) {
            wp_send_json_error( $request_id->get_error_message() );
        }

        // Send the confirmation email native to WordPress.
        wp_send_user_request( $request_id );

        wp_send_json_success( [
            'message' => __( 'Your request has been received. We have sent a confirmation email to that address. You must click the link in the email to confirm the deletion.', 'himalayan-homestay-bookings' )
        ] );
    }
}
