<?php
/**
 * PDF Invoice Generator
 *
 * Uses FPDF to generate professional invoices for homestay bookings.
 *
 * @package Himalayan\Homestay\Infrastructure\PDF
 */

namespace Himalayan\Homestay\Infrastructure\PDF;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Require FPDF Library
if ( ! class_exists( 'FPDF' ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'fpdf/fpdf.php';
}

class InvoiceGenerator extends \FPDF {

    public static function init() {
        add_action( 'template_redirect', [ __CLASS__, 'handle_download_request' ] );
        add_action( 'admin_init', [ __CLASS__, 'handle_download_request' ] );
    }

    public static function handle_download_request() {
        if ( isset( $_GET['hhb_download_invoice'] ) && is_numeric( $_GET['hhb_download_invoice'] ) ) {
            $booking_id = (int) $_GET['hhb_download_invoice'];
            
            // Security Check
            global $wpdb;
            $booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}himalayan_bookings WHERE id = %d", $booking_id ) );
            if ( ! $booking ) {
                wp_die( 'Booking not found.' );
            }

            $user = wp_get_current_user();
            if ( ! current_user_can( 'manage_options' ) && $user->user_email !== $booking->customer_email ) {
                wp_die( 'You do not have permission to view this invoice.' );
            }

            $pdf = new self( $booking_id );
            // 'D' forces download
            $pdf->generate( 'D' );
            exit;
        }
    }

    private $booking;
    private $homestay;
    private $currency = 'INR';

    /**
     * Generate the next invoice number atomically using SELECT ... FOR UPDATE.
     * 
     * STRATEGIC BURN BEHAVIOR:
     * This method intentionally opens and closes its own autonomous transaction. 
     * If the parent process calling this method (e.g., webhook confirmation) 
     * subsequently crashes or rolls back, the generated sequence ID is explicitly 
     * "BURNED". This is a hardened accounting requirement to guarantee strict 
     * monotonic chronological integrity. Never recycle a rolled-back sequence ID.
     *
     * @return string Invoice number formatted as INV-YYYYMM-00001
     */
    public static function next_invoice_number(): string {
        global $wpdb;
        $table = $wpdb->prefix . 'himalayan_invoice_sequences';

        $wpdb->query( 'START TRANSACTION' );

        $row = $wpdb->get_row( "SELECT last_invoice_number FROM {$table} WHERE id = 1 FOR UPDATE" );

        if ( ! $row ) {
            $wpdb->query( 'ROLLBACK' );
            error_log( 'HHB Invoice: Sequence table row missing. Using fallback.' );
            return 'INV-' . gmdate( 'Ym' ) . '-' . str_pad( mt_rand( 1, 99999 ), 5, '0', STR_PAD_LEFT );
        }

        $next = (int) $row->last_invoice_number + 1;

        $wpdb->update( $table, [ 'last_invoice_number' => $next ], [ 'id' => 1 ], [ '%d' ], [ '%d' ] );

        $wpdb->query( 'COMMIT' );

        return 'INV-' . gmdate( 'Ym' ) . '-' . str_pad( $next, 5, '0', STR_PAD_LEFT );
    }

    /**
     * Assign an atomic invoice number to a booking (idempotent — skips if already set).
     *
     * @param int $booking_id
     * @return string The assigned (or existing) invoice number.
     */
    public static function assign_invoice_number( int $booking_id ): string {
        global $wpdb;
        $table = $wpdb->prefix . 'himalayan_bookings';

        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT invoice_number FROM {$table} WHERE id = %d",
            $booking_id
        ) );

        if ( ! empty( $existing ) ) {
            return $existing;
        }

        $invoice_number = self::next_invoice_number();

        $wpdb->update(
            $table,
            [ 'invoice_number' => $invoice_number ],
            [ 'id' => $booking_id ],
            [ '%s' ],
            [ '%d' ]
        );

        return $invoice_number;
    }

    public function __construct( $booking_id ) {
        parent::__construct();

        global $wpdb;
        $this->booking = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}himalayan_bookings WHERE id = %d",
            $booking_id
        ), ARRAY_A );

        if ( $this->booking ) {
            $this->homestay = get_post( $this->booking['homestay_id'] );
        }
    }

    /**
     * Generate the PDF and return as string (attachment) or output to browser
     */
    public function generate( $output_type = 'S', $path = '' ) {
        if ( ! $this->booking || ! $this->homestay ) {
            return false;
        }

        $this->AddPage();
        $this->SetAutoPageBreak( true, 15 );
        
        $this->build_header();
        $this->build_customer_info();
        $this->build_booking_details();
        $this->build_table();
        $this->build_totals();
        $this->build_footer();

        $filename = 'Invoice-' . $this->booking['id'] . '.pdf';
        if ( $output_type === 'F' && empty( $path ) ) {
            $upload_dir = wp_upload_dir();
            $path = trailingslashit( $upload_dir['basedir'] ) . $filename;
        }

        // Output: 'I' = Browser, 'D' = Download, 'S' = Return as String, 'F' = Save to local file
        $dest = ( $output_type === 'F' ) ? $path : $filename;
        $result = $this->Output( $output_type, $dest );
        
        return ( $output_type === 'F' ) ? $path : $result;
    }

    private function build_header() {
        $this->SetFont('Helvetica', 'B', 28);
        $this->SetTextColor(17, 17, 17); // Darker text #111
        $this->Cell(130, 12, 'INVOICE', 0, 0);
        
        $this->SetFont('Helvetica', 'B', 16);
        $this->SetTextColor(244, 92, 37); // Brand Orange
        $this->Cell(60, 12, 'Laluri', 0, 1, 'R');

        $this->SetFont('Helvetica', '', 10);
        $this->SetTextColor(119, 119, 119); // Lighter text #777
        $invoice_num = ! empty( $this->booking['invoice_number'] )
            ? $this->booking['invoice_number']
            : '#' . str_pad($this->booking['id'], 5, '0', STR_PAD_LEFT);
        $this->Cell(130, 6, 'Reference: ' . $invoice_num, 0, 0);
        $this->Cell(60, 6, date( 'F j, Y', strtotime( $this->booking['created_at'] ) ), 0, 1, 'R');
        $this->Ln(15);
    }

    private function build_customer_info() {
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(153, 153, 153); // #999 label
        $this->Cell(95, 6, strtoupper('Billed To'), 0, 0);
        $this->Cell(95, 6, strtoupper('Property'), 0, 1);
        $this->Ln(2);

        $this->SetFont('Helvetica', '', 11);
        $this->SetTextColor(33, 33, 33);
        
        $x = $this->GetX();
        $y = $this->GetY();
        
        // Customer Column
        $this->MultiCell(90, 6, 
            $this->booking['customer_name'] . "\n" . 
            $this->booking['customer_email'] . "\n" . 
            $this->booking['customer_phone']
        );
        
        // Property Column
        $this->SetXY($x + 95, $y);
        $this->MultiCell(90, 6, 
            $this->homestay->post_title . "\n" . 
            "Check-in: " . date( 'M d, Y', strtotime($this->booking['check_in']) ) . "\n" . 
            "Check-out: " . date( 'M d, Y', strtotime($this->booking['check_out']) )
        );
        $this->Ln(15);
    }

    private function build_booking_details() {
        $this->SetFillColor(250, 250, 250);
        $this->SetDrawColor(238, 238, 238);
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(119, 119, 119);
        
        $this->Cell(47.5, 10, 'GUESTS', 'T,B', 0, 'C', true);
        $this->Cell(47.5, 10, 'NIGHTS', 'T,B', 0, 'C', true);
        $this->Cell(47.5, 10, 'STATUS', 'T,B', 0, 'C', true);
        $this->Cell(47.5, 10, 'PAYMENT', 'T,B', 1, 'C', true);

        $this->SetFont('Helvetica', '', 11);
        $this->SetTextColor(17, 17, 17);
        $nights = (int) ( ( strtotime( $this->booking['check_out'] ) - strtotime( $this->booking['check_in'] ) ) / 86400 );
        
        $this->Cell(47.5, 12, $this->booking['guests'], 'B', 0, 'C');
        $this->Cell(47.5, 12, $nights, 'B', 0, 'C');
        $this->Cell(47.5, 12, strtoupper( str_replace('_', ' ', $this->booking['status']) ), 'B', 0, 'C');
        $this->Cell(47.5, 12, 'Pending', 'B', 1, 'C');
        $this->Ln(15);
    }

    private function build_table() {
        // Table Header
        $this->SetDrawColor(221, 221, 221); // #ddd
        $this->SetTextColor(119, 119, 119); // #777
        $this->SetFont('Helvetica', 'B', 9);
        $this->Cell(110, 10, 'DESCRIPTION', 'B', 0, 'L');
        $this->Cell(30, 10, 'QTY', 'B', 0, 'C');
        $this->Cell(50, 10, 'TOTAL', 'B', 1, 'R');

        // Table Body
        $this->SetTextColor(17, 17, 17); // #111
        $this->SetFont('Helvetica', '', 11);
        
        $nights = (int) ( ( strtotime( $this->booking['check_out'] ) - strtotime( $this->booking['check_in'] ) ) / 86400 );
        
        // Main Accommodation
        $this->Cell(110, 12, 'Accommodation (' . $nights . ' nights)', 'B', 0, 'L');
        $this->Cell(30, 12, '1', 'B', 0, 'C');
        
        global $wpdb;
        $services = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.service_name, bs.quantity, bs.subtotal FROM {$wpdb->prefix}himalayan_booking_services bs
             JOIN {$wpdb->prefix}himalayan_extra_services s ON bs.service_id = s.id
             WHERE bs.booking_id = %d",
             $this->booking['id']
        ) );

        $services_total = 0;
        foreach ( $services as $svc ) {
            $services_total += $svc->subtotal;
        }

        $accommodation_total = $this->booking['total_price'] - $services_total;

        $this->Cell(50, 12, $this->currency . ' ' . number_format($accommodation_total, 2), 'B', 1, 'R');

        // Extra Services
        foreach ( $services as $svc ) {
            $this->Cell(110, 12, 'Add-on: ' . $svc->service_name, 'B', 0, 'L');
            $this->Cell(30, 12, $svc->quantity, 'B', 0, 'C');
            $this->Cell(50, 12, $this->currency . ' ' . number_format($svc->subtotal, 2), 'B', 1, 'R');
        }

        $this->Ln(10);
    }

    private function build_totals() {
        $this->SetFont('Helvetica', '', 11);
        $this->SetTextColor(119, 119, 119); // #777
        
        // Deposit
        if ( $this->booking['deposit_amount'] > 0 && $this->booking['deposit_amount'] < $this->booking['total_price'] ) {
            $this->Cell(140, 8, 'Deposit Required', 0, 0, 'R');
            $this->SetTextColor(17, 17, 17);
            $this->Cell(50, 8, $this->currency . ' ' . number_format($this->booking['deposit_amount'], 2), 0, 1, 'R');
            
            $this->SetTextColor(119, 119, 119);
            $this->Cell(140, 8, 'Balance Due on Arrival', 0, 0, 'R');
            $this->SetTextColor(17, 17, 17);
            $this->Cell(50, 8, $this->currency . ' ' . number_format($this->booking['balance_due'], 2), 0, 1, 'R');
        }

        $this->Ln(5);

        // Grand Total
        $this->SetFont('Helvetica', 'B', 14);
        $this->SetTextColor(17, 17, 17);
        $this->Cell(140, 12, 'GRAND TOTAL', 0, 0, 'R');
        $this->SetTextColor(244, 92, 37); // Brand Orange
        $this->Cell(50, 12, $this->currency . ' ' . number_format($this->booking['total_price'], 2), 0, 1, 'R');
        $this->Ln(15);
    }

    private function build_footer() {
        $this->SetY(-30);
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(170, 170, 170); // #aaa
        $this->Cell(0, 10, 'Thank you for choosing Laluri. If you have any questions, please contact us.', 0, 0, 'C');
    }
}
