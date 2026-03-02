# Himalayan Homestay Bookings

**Version:** 2.0.0  
**Requires:** WordPress 6.0+, PHP 7.4+  
**License:** Proprietary  

A professional, enterprise-grade homestay booking system built for the Indian hospitality market. Features custom database tables, event-driven architecture, an advanced pricing engine, availability calendar, Razorpay payment integration, and a transaction-safe reservation engine.

---

## Architecture

```
himalayan-homestay-bookings/
├── Core/                           # Post Types & Taxonomies
├── Domain/                         # Business Logic (DDD)
│   ├── Availability/               #   AvailabilityEngine
│   ├── Booking/                    #   BookingManager, BookingStatus
│   └── Pricing/                    #   PricingEngine
├── Infrastructure/                 # External Services & I/O
│   ├── API/                        #   REST API Controller
│   ├── Database/                   #   Schema Installer (dbDelta)
│   ├── ICAL/                       #   iCal Sync Manager
│   ├── Notifications/              #   EmailNotifier, EmailAutomator
│   ├── Payments/                   #   RazorpayGateway, AbstractGateway
│   ├── PDF/                        #   InvoiceGenerator (TCPDF)
│   └── Reviews/                    #   ReviewManager
├── Interface/                      # UI Layer
│   ├── Admin/                      #   Admin pages (Bookings, Calendar, Settings, ...)
│   └── Frontend/                   #   Booking Widget, Confirmation, My Account, ...
├── Frontend/                       # Host Application & Property Manager
└── assets/                         # CSS & JS
```

**Autoloading:** PSR-4 via `spl_autoload_register()`.

---

## Database Schema (v3.0.0)

| Table | Purpose |
|-------|---------|
| `himalayan_bookings` | Core bookings ledger with refund audit columns |
| `himalayan_booking_hold` | Temporary date locks (session-based) |
| `himalayan_pricing_rules` | Seasonal, weekend, and custom pricing rules |
| `himalayan_extra_services` | Add-on services per homestay |
| `himalayan_booking_services` | Booking ↔ Service pivot |
| `himalayan_email_log` | Idempotent email log (`UNIQUE KEY` on booking_id + email_type) |
| `himalayan_webhook_events` | Razorpay webhook replay prevention |
| `himalayan_invoice_sequences` | Atomic invoice number generator (`SELECT ... FOR UPDATE`) |
| `himalayan_coupons` | Discount coupon codes |
| `hhb_reviews` | Verified guest reviews (multi-dimension ratings) |
| `hhb_ical_feeds` | External iCal feed URLs for sync |

---

## State Machine

All booking status transitions are enforced through `BookingManager::transition_status()`:

```
pending ──→ approved ──→ confirmed ──→ refunded (terminal)
  │              │                ╰──→ cancelled (terminal)
  │              ╰──→ payment_expired
  ╰──→ dropped
  ╰──→ cancelled (terminal)
```

Every status change includes:
- **Optimistic locking** (`WHERE status = current_status`)
- **Audit logging** (previous → new, actor, timestamp)
- **Lifecycle hooks** fired automatically

---

## Features

### Booking Engine
- Real-time availability checking with date overlap detection
- Temporary date holds (30-minute session locks)
- Dynamic pricing engine (seasonal, weekend, custom rules)
- Extra services / add-on support
- Discount coupons with usage tracking
- Multi-guest support (adults + children)

### Payment Integration
- **Razorpay** gateway with order creation and payment verification
- Client-side verification (`/razorpay-verify`)
- Server-to-server webhook (`/razorpay-webhook`) with:
  - HMAC-SHA256 signature verification
  - Amount and currency validation
  - Event idempotency via `webhook_events` table
  - DB transaction wrapping (`START TRANSACTION` → `COMMIT`)
  - `refund.processed` self-healing listener
- **Cash** payment mode (pay-on-arrival)
- Automated refund API (`/v1/payments/{id}/refund`)

### Admin Panel
- **Bookings Manager** — List table with status filters, detail view, approve/confirm/cancel actions
- **Availability Calendar** — Visual date management
- **Settings** — SMTP, Razorpay keys, payment expiry window, commission rates
- **Coupons** — Create/manage discount codes
- **Host Applications** — Review become-a-host submissions
- **Guest Users** — Browse registered guests
- **Reviews** — Moderate verified guest reviews
- **Admin Dashboard** — Booking analytics and revenue overview
- **System Tools** — Database diagnostics and maintenance

### Email System
- **Transactional emails:** Booking received, Approved, Confirmed, Dropped, Cancelled, Payment expired
- **Marketing automation:** Pre-arrival (3 days), Post-checkout review (1 day), Review follow-up (5 days), Win-back primary (60 days), Win-back secondary (180 days)
- **Idempotency:** Database-level `UNIQUE KEY` + application-level check prevents duplicates
- **Retry-safe:** Failed sends are not logged, allowing automatic retry on next trigger
- **PDF invoice** attachment on confirmation

### Security & Integrity
- Rate limiting: 5 booking attempts per IP per 15 minutes (transient-based)
- Webhook signature verification (HMAC-SHA256)
- Nonce verification on all admin actions
- State machine prevents invalid transitions
- Payment amount validation on webhooks
- GDPR compliance: Cookie banner, data deletion requests, personal data eraser

### Cron Jobs
| Schedule | Task |
|----------|------|
| Every 5 min | Cleanup expired date holds |
| Every 5 min | Expire approved bookings past payment deadline |
| Every 15 min | Drop pending bookings past payment window |
| Every 15 min | Sync external iCal feeds |
| Daily | Marketing email sequences (pre-arrival, review, win-back) |
| Daily | Archive stale cash bookings (>7 days pending) |

### Frontend
- **Booking Widget** — AJAX-powered date picker, guest selector, service add-ons
- **Confirmation Page** — Razorpay checkout integration
- **My Account** — Guest booking history
- **Review Page** — Submit verified reviews with multi-dimension ratings
- **Wishlist** — Save favorite properties
- **SEO** — JSON-LD schema markup (LodgingBusiness, AggregateRating)

---

## REST API Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/himalayan/v1/check-availability` | Public | Check date availability |
| GET | `/himalayan/v1/calculate-price` | Public | Get detailed price breakdown |
| POST | `/himalayan/v1/create-booking` | Public (rate-limited) | Create a new booking |
| POST | `/himalayan/v1/razorpay-verify` | Public (token-verified) | Client-side payment verification |
| POST | `/himalayan/v1/razorpay-webhook` | Public (signature-verified) | Server-to-server webhook |
| POST | `/himalayan/v1/drop-booking` | Public (token-verified) | Cancel/drop a pending booking |

---

## Setup

1. Upload the plugin to `/wp-content/plugins/himalayan-homestay-bookings/`
2. Activate from WP Admin → Plugins
3. Navigate to **Bookings → Settings** to configure:
   - SMTP email credentials
   - Razorpay API keys (Key ID, Key Secret, Webhook Secret)
   - Payment expiry window (default: 60 minutes)
   - Commission percentage
4. In the **Razorpay Dashboard → Settings → Webhooks**, add:
   - URL: `https://yourdomain.com/wp-json/himalayan/v1/razorpay-webhook`
   - Events: `payment.captured`, `order.paid`, `refund.processed`
   - Secret: Must match the Webhook Secret in plugin settings

---

## Dependencies

- WordPress 6.0+
- PHP 7.4+ (with `json`, `hash`, `mbstring`)
- MySQL 5.7+ / MariaDB 10.3+ (InnoDB for transactions)
- TCPDF (bundled in `Infrastructure/PDF/`)
- Razorpay Account (for online payments)
