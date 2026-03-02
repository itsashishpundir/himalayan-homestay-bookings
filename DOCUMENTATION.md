# Himalayan Homestay Bookings - Complete Technical Documentation

This document serves as the definitive guide to the architecture, database schema, features, and workflows of the **Himalayan Homestay Bookings** WordPress plugin. It is designed for developers maintaining or extending the plugin.

---

## 1. Plugin Architecture & Directory Structure

The plugin strictly follows Domain-Driven Design (DDD) principles with clear separation of concerns, organized by namespace (`Himalayan\Homestay`).

```text
himalayan-homestay-bookings/
├── Core/               # Fundamental WP registrations (Post Types, Taxonomies)
├── Domain/             # Core business logic (Booking Manager, Availability Engine)
├── Frontend/           # AJAX Handlers (Host Applications, Property Manager)
├── Infrastructure/     # External integrations, background processes, physical data
│   ├── API/            # REST API controllers
│   ├── Admin/          # Admin-specific utilities (GDPR Eraser, User Cleanup)
│   ├── Database/       # Schema definitions (Installer.php)
│   ├── ICAL/           # iCal synchronization engine
│   ├── Notifications/  # Email sending and automation
│   ├── PDF/            # Invoice generation via FPDF
│   ├── Payments/       # Payment Gateways (Razorpay, Stripe)
│   └── Reviews/        # Automated guest review handling
└── Interface/          # User-facing representations (UI)
    ├── Admin/          # WP Admin pages (Settings, Bookings, Reports, etc.)
    └── Frontend/       # Shortcodes (Booking Widget, My Account, Checkout)
```

---

## 2. Database Schema (Version 3.2.0)

The plugin creates custom tables upon activation via `Infrastructure\Database\Installer::install()`. All tables use the WordPress prefix (e.g., `wp_`).

### Core Tables
1. **`himalayan_bookings`**: The central ledger for all reservations.
   - **Key Columns**: `homestay_id`, `customer_email`, `check_in`, `check_out`, `guests`, `total_price`, `status`, `payment_token`, `payment_expires_at`, `transaction_id`, `invoice_number`.
2. **`himalayan_booking_hold`**: Manages temporary availability locks during the checkout process (prevents double-booking). Expires automatically.
3. **`himalayan_coupons`**: Discount codes (fixed or percentage) with expiry rules.
4. **`himalayan_host_applications`**: Form submissions from users applying to become hosts.
5. **`himalayan_ical_feeds`**: URLs for importing availability from platforms like Airbnb or Booking.com.

### Auditing & Integrity Tables
6. **`himalayan_email_log`**: Logs every email sent. Uses a unique key on `(booking_id, email_type)` to guarantee **idempotency** (emails cannot be double-sent).
7. **`himalayan_webhook_events`**: Logs Razorpay webhook IDs to prevent duplicate processing of the same event.
8. **`himalayan_invoice_sequences`**: A single-row table used explicitly for **Atomic Invoice Generation** (`SELECT ... FOR UPDATE`).
9. **`himalayan_audit_log`**: An immutable timeline recording every time a booking's status changes (`booking_id`, `old_status`, `new_status`, `actor`, `note`).
10. **`himalayan_payouts`**: Tracks the financial split. Auto-populated on confirmation to track `commission_amount` vs `host_payout_amount`.

---

## 3. The State Machine (`BookingManager`)

The lifeblood of the plugin is the `Domain\Booking\BookingManager`. It governs how a booking moves from creation to completion. **No code should manually update a booking's `status` column.** Everything must route through `$manager->transition_status()`.

### The Valid Flow
1. **`pending_inquiry`**: Initial state when the checkout form is submitted.
2. **`approved`**: An admin accepts the booking. Triggers a payment link email. The `payment_expires_at` countdown starts.
3. **`confirmed`**: Payment is successfully captured (via Gateway or Cash).
    - **Triggered Actions**: Invoice assigned, confirmation email sent with PDF, host payout record generated.
4. **`completed`**: The guest completes their stay (status updated via cron).

### Alternate Flows
- **`payment_expired`**: Cron job transitions `approved` bookings that exceed the payment window.
- **`cancelled`**: Admin or Guest cancels before check-in.
- **`refunded`**: Admin or Guest cancels and a partial/full refund is issued natively via the Razorpay API.
- **`dropped`**: Manual cleanup of abandoned carts or failed bookings.

---

## 4. Payment Integrations

### Gateway Implementations
Found in `Infrastructure\Payments`. The primary engine is the `RazorpayGateway`. 
- **Checkout Flow**: Creates a Razorpay Order ID. Hands off to frontend JavaScript. Validates the signature natively on success before confirming the booking.
- **Webhook Securtiy**: Handled by `Interface\Frontend\ConfirmationPage`. Verifies the `HTTP_X_RAZORPAY_SIGNATURE`. Checks idempotency against `himalayan_webhook_events`. Validates the captured currency and amount against the database.
- **Refunds**: Executed dynamically. The plugin reads the selected **Cancellation Policy** (Flexible/Moderate/Strict/Custom), calculates the exact percentage, and calls the Razorpay Refund API (`/payments/{id}/refund`).

---

## 5. Background Processes (Cron Jobs)

The plugin relies heavily on `wp_cron` for automation. These schedules are registered in the main plugin file.

1. **`himalayan_cleanup_expired_holds`** (Every 5 mins): Cleans up expired rows in `himalayan_booking_hold` so canceled checkouts release their dates immediately.
2. **`hhb_check_payment_expiry`** (Every 5 mins): Transitions bookings from `approved` to `payment_expired` if they miss their payment window. Triggers the Expired email.
3. **`hhb_sync_ical_feeds`** (Every 15 mins): Fetches `.ics` files from external OTAs, parses them, and inserts dummy "blocked" bookings to prevent overbooking.
4. **`hhb_review_automator_cron`** (Daily): Scans for bookings that checked out exactly X days ago. Triggers the Automated Review Request email flow.
5. **`hhb_mark_completed_bookings`** (Daily): Automatically sweeps `confirmed` bookings where `check_out < NOW()` and marks them as `completed`.

---

## 6. Admin User Interfaces

WP Admin interfaces are built natively using standard WP list tables and forms, located in `Interface\Admin`.

- **BookingsPage**: The command center. Displays a list of bookings. The detail view shows customer notes, the exact payment timeline, and the **Audit Log** of status transitions. Houses the "Cancel & Refund" action.
- **CalendarPage**: A visual representation of availability across all homestays.
- **SettingsPage**: Tabbed interface governing Gateways, Email Templates, Cancellation Policies, GDPR, and Automation Timing.
- **FinancialReportsPage**: Date-range driven analytics. Shows revenue, aggregated commissions, total refunds, and allows CSV export of monthly breakdowns.
- **PayoutsPage**: Displays the ledger of pending host commissions. Allows admins to mark payouts as paid.

---

## 7. Frontend User Interfaces (Shortcodes)

Located in `Interface\Frontend`.

- **`[himalayan_booking_widget]`**: The property-specific checkout engine. Includes real-time availability polling, dynamic pricing calculations (base + extra guests), coupon validation, and checkout form generation.
- **`[hhb_my_account]`**: The Guest Dashboard. Requires login. Shows upcoming/past bookings. Includes the **Guest Self-Service Cancellation** engine, which preview the policy-driven refund amount and executes the cancellation securely. Includes the Wishlist.

---

## 8. Development & Extension Notes

- **Idempotency Everywhere**: When extending the plugin, assume hooks and webhooks might fire twice. Always use unique database constraints (like `INSERT IGNORE` on `webhook_events`) or check state before acting (like `email_log` checking).
- **Timezones**: The core engine strictly uses GMT/UTC for backend logic (`current_time('mysql', 1)`). Display layers convert to local time via `get_option('timezone_string')`.
- **i18n Ready**: All text strings are prefixed with the `himalayan-homestay-bookings` text domain. Ensure any new output is wrapped in `__()` or `esc_html__()`.
