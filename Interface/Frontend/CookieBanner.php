<?php
/**
 * Cookie Consent Banner
 *
 * A modern, glassmorphic bottom-fixed banner to comply with GDPR/CCPA.
 *
 * @package Himalayan\Homestay
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div id="hhb-cookie-banner" class="hhb-cookie-banner" style="display: none;">
    <div class="hhb-cookie-content">
        <div class="hhb-cookie-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5"></path><path d="M8.5 8.5v.01"></path><path d="M16 12.5v.01"></path><path d="M12 16v.01"></path><path d="M11 12.5v.01"></path></svg>
        </div>
        <div class="hhb-cookie-text">
            <h4><?php esc_html_e( 'We value your privacy', 'himalayan-homestay-bookings' ); ?></h4>
            <p>
                <?php esc_html_e( 'We use cookies to enhance your browsing experience, serve personalized content, and analyze our traffic. By clicking "Accept All", you consent to our use of cookies.', 'himalayan-homestay-bookings' ); ?>
                <a href="<?php echo esc_url( get_privacy_policy_url() ); ?>" target="_blank"><?php esc_html_e( 'Read Privacy Policy', 'himalayan-homestay-bookings' ); ?></a>
            </p>
        </div>
    </div>
    <div class="hhb-cookie-actions">
        <button id="hhb-cookie-manage" class="hhb-btn-manage"><?php esc_html_e( 'Manage Preferences', 'himalayan-homestay-bookings' ); ?></button>
        <button id="hhb-cookie-reject" class="hhb-btn-reject"><?php esc_html_e( 'Reject Non-Essential', 'himalayan-homestay-bookings' ); ?></button>
        <button id="hhb-cookie-accept" class="hhb-btn-accept"><?php esc_html_e( 'Accept All', 'himalayan-homestay-bookings' ); ?></button>
    </div>
</div>

<style>
/* Modern Glassmorphic Cookie Banner */
.hhb-cookie-banner {
    position: fixed;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%) translateY(120%);
    width: 90%;
    max-width: 1000px;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(255, 255, 255, 0.4);
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    border-radius: 16px;
    padding: 20px 24px;
    display: flex;
    flex-direction: column;
    gap: 20px;
    z-index: 999999;
    opacity: 0;
    transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.6s ease;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

.hhb-cookie-banner.show {
    transform: translateX(-50%) translateY(0);
    opacity: 1;
}

/* For Dark Mode adaptation if needed */
@media (prefers-color-scheme: dark) {
    .hhb-cookie-banner {
        background: rgba(20, 20, 20, 0.85);
        border-color: rgba(255, 255, 255, 0.1);
    }
}

@media (min-width: 768px) {
    .hhb-cookie-banner {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
    }
}

.hhb-cookie-content {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    flex: 1;
}

.hhb-cookie-icon {
    background: rgba(244, 92, 37, 0.1);
    color: #f45c25;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.hhb-cookie-text h4 {
    margin: 0 0 4px;
    font-size: 16px;
    font-weight: 700;
    color: var(--hh-text-main, #111);
}

@media (prefers-color-scheme: dark) {
    .hhb-cookie-text h4 { color: #fff; }
}

.hhb-cookie-text p {
    margin: 0;
    font-size: 13px;
    line-height: 1.5;
    color: var(--hh-text-muted, #555);
}

@media (prefers-color-scheme: dark) {
    .hhb-cookie-text p { color: #aaa; }
}

.hhb-cookie-text a {
    color: #f45c25;
    text-decoration: underline;
    font-weight: 500;
}

.hhb-cookie-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    shrink: 0;
}

.hhb-cookie-actions button {
    padding: 10px 16px;
    font-size: 13px;
    font-weight: 600;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
}

.hhb-btn-manage {
    background: transparent;
    color: var(--hh-text-muted, #555);
    text-decoration: underline;
}

@media (prefers-color-scheme: dark) {
    .hhb-btn-manage { color: #aaa; }
}

.hhb-btn-manage:hover {
    color: #111;
}

.hhb-btn-reject {
    background: transparent;
    border: 1px solid rgba(0,0,0,0.1) !important;
    color: var(--hh-text-main, #333);
}

@media (prefers-color-scheme: dark) {
    .hhb-btn-reject { 
        border-color: rgba(255,255,255,0.2) !important; 
        color: #eee;
    }
    .hhb-btn-reject:hover { background: rgba(255,255,255,0.05); }
}

.hhb-btn-reject:hover {
    background: rgba(0,0,0,0.03);
}

.hhb-btn-accept {
    background: #f45c25;
    color: #fff;
    box-shadow: 0 4px 12px rgba(244, 92, 37, 0.2);
}

.hhb-btn-accept:hover {
    background: #e04010;
    transform: translateY(-1px);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const banner = document.getElementById('hhb-cookie-banner');
    if (!banner) return;

    // Check if user has already made a choice
    const cookieConsent = localStorage.getItem('hhb_cookie_consent');
    
    if (!cookieConsent) {
        // Show banner after a slight delay for better UX
        setTimeout(() => {
            banner.style.display = 'flex';
            // Trigger reflow
            void banner.offsetWidth;
            banner.classList.add('show');
        }, 1000);
    }

    const setConsent = (level) => {
        localStorage.setItem('hhb_cookie_consent', level);
        banner.classList.remove('show');
        setTimeout(() => { banner.style.display = 'none'; }, 600);
        
        // If accepted all, we could trigger analytic scripts here
        if (level === 'all') {
            document.dispatchEvent(new CustomEvent('hhb_cookies_accepted'));
        }
    };

    document.getElementById('hhb-cookie-accept')?.addEventListener('click', () => setConsent('all'));
    document.getElementById('hhb-cookie-reject')?.addEventListener('click', () => setConsent('essential'));
    document.getElementById('hhb-cookie-manage')?.addEventListener('click', () => {
        // For a full implementation, this would open a granular modal.
        // For now, treat as reject non-essential to be safe.
        alert('Advanced preference management coming soon. Setting to essential only for now.');
        setConsent('essential');
    });
});
</script>
