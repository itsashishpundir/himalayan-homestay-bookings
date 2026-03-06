<?php
/**
 * Plugin Template: Become a Host Page
 *
 * Host application page. Theme can override by providing page-become-a-host.php.
 * Customizer calls removed; uses hardcoded defaults (can be overridden via theme).
 *
 * @package Himalayan\Homestay
 */

get_header();

// Hardcoded defaults — no customizer dependency
$hero_bg_url   = 'https://images.unsplash.com/photo-1470770841497-7b3200e37531?w=1920&q=80';
$hero_badge    = 'Host the Future';
$hero_title    = 'Share Your World.<br>Become a <span class="text-primary">Host.</span>';
$hero_subtitle = "Join our exclusive community of premium mountain retreats and unique homestays. List your property on the Himalayan region's leading hospitality platform.";

$step1_title = 'Apply Online';
$step1_desc  = 'Submit your property details and photos through this form.';
$step2_title = 'Manual Review';
$step2_desc  = 'Our team reviews every application within 48 hours to ensure quality standards are met.';
$step3_title = 'Onboarding';
$step3_desc  = 'Once approved, we help you set up your profile and get your listing live.';

$benefit1 = 'Reach thousands of verified travelers';
$benefit2 = 'Dedicated host support & assistance';
$benefit3 = '24/7 customer support for your guests';
?>

<!-- Hero Section -->
<section class="relative h-[60vh] min-h-[500px] w-full flex items-center justify-center overflow-hidden">
    <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('<?php echo esc_url( $hero_bg_url ); ?>');">
        <div class="absolute inset-0 bg-gradient-to-b from-background-dark/40 via-background-dark/20 to-background-light dark:to-background-dark"></div>
    </div>
    <div class="relative z-10 text-center px-4 max-w-4xl mx-auto">
        <?php if ( $hero_badge ) : ?>
        <span class="inline-block px-4 py-1.5 bg-primary/20 text-primary rounded-full text-xs font-bold uppercase tracking-widest mb-6 backdrop-blur-md border border-primary/30"><?php echo esc_html( $hero_badge ); ?></span>
        <?php endif; ?>
        <h1 class="text-5xl md:text-7xl font-black text-white leading-tight mb-6 drop-shadow-2xl">
            <?php echo wp_kses_post( $hero_title ); ?>
        </h1>
        <p class="text-lg md:text-xl text-white/90 font-medium max-w-2xl mx-auto drop-shadow-md">
            <?php echo esc_html( $hero_subtitle ); ?>
        </p>
    </div>
</section>

<!-- Main Content -->
<section class="max-w-6xl mx-auto px-4 py-20 -mt-20 relative z-20">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-12">

        <!-- Form: 2/3 width -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-slate-900 rounded-xl shadow-2xl p-8 md:p-12 border border-slate-100 dark:border-slate-800">

                <!-- Form Header -->
                <div class="flex items-center gap-4 mb-10">
                    <div class="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center">
                        <span class="material-symbols-outlined text-primary">edit_document</span>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold"><?php esc_html_e( 'Host Application', 'himalayan-homestay-bookings' ); ?></h3>
                        <p class="text-slate-500 dark:text-slate-400"><?php esc_html_e( 'Tell us about your extraordinary space', 'himalayan-homestay-bookings' ); ?></p>
                    </div>
                </div>

                <!-- Application Form -->
                <form class="space-y-8" method="post" enctype="multipart/form-data" id="hm-host-application-form">
                    <?php wp_nonce_field( 'hm_host_application', 'hm_host_nonce' ); ?>

                    <!-- Honeypot Field -->
                    <div style="position: absolute; left: -5000px;" aria-hidden="true">
                        <input type="text" name="hhb_host_website" tabindex="-1" value="">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-slate-700 dark:text-slate-300 ml-1"><?php esc_html_e( 'Full Name', 'himalayan-homestay-bookings' ); ?> <span class="text-red-400">*</span></label>
                            <input class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg h-14 px-4 focus:border-primary focus:ring-primary/20 transition-all outline-none" name="host_name" placeholder="<?php esc_attr_e( 'Your full name', 'himalayan-homestay-bookings' ); ?>" type="text" required />
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-slate-700 dark:text-slate-300 ml-1"><?php esc_html_e( 'Email Address', 'himalayan-homestay-bookings' ); ?> <span class="text-red-400">*</span></label>
                            <input class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg h-14 px-4 focus:border-primary focus:ring-primary/20 transition-all outline-none" name="host_email" placeholder="<?php esc_attr_e( 'you@example.com', 'himalayan-homestay-bookings' ); ?>" type="email" required />
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-slate-700 dark:text-slate-300 ml-1"><?php esc_html_e( 'Phone Number', 'himalayan-homestay-bookings' ); ?> <span class="text-red-400">*</span></label>
                            <input class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg h-14 px-4 focus:border-primary focus:ring-primary/20 transition-all outline-none" name="host_phone" placeholder="<?php esc_attr_e( '+91 98765 43210', 'himalayan-homestay-bookings' ); ?>" type="tel" required />
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="space-y-4">
                        <label class="flex items-start gap-3 cursor-pointer group">
                            <input type="checkbox" name="host_consent" required class="mt-1 w-5 h-5 rounded border-slate-300 text-primary focus:ring-primary/20 transition-colors" />
                            <span class="text-sm text-slate-600 dark:text-slate-400">
                                <?php esc_html_e( 'I consent to the collection and processing of my personal data for the purpose of this application. I understand my data will be handled in accordance with the', 'himalayan-homestay-bookings' ); ?>
                                <a href="<?php echo esc_url( get_privacy_policy_url() ); ?>" target="_blank" class="text-primary hover:underline font-semibold"><?php esc_html_e( 'Privacy Policy', 'himalayan-homestay-bookings' ); ?></a>.
                            </span>
                        </label>

                        <button class="w-full bg-primary text-white h-14 rounded-lg font-extrabold text-lg shadow-xl shadow-primary/30 hover:scale-[1.02] active:scale-[0.98] transition-transform cursor-pointer" type="submit">
                            <?php esc_html_e( 'Submit Application', 'himalayan-homestay-bookings' ); ?>
                        </button>
                    </div>
                </form>

                <!-- Success message (hidden by default) -->
                <div id="hm-host-success" class="hidden text-center py-16">
                    <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <span class="material-symbols-outlined text-green-600 text-4xl">check_circle</span>
                    </div>
                    <h3 class="text-2xl font-bold mb-2"><?php esc_html_e( 'Application Submitted!', 'himalayan-homestay-bookings' ); ?></h3>
                    <p class="text-slate-500 max-w-md mx-auto"><?php esc_html_e( 'Thank you for your interest. Our team will review your application and get back to you within 48 hours.', 'himalayan-homestay-bookings' ); ?></p>
                </div>
            </div>
        </div>

        <!-- Sidebar: 1/3 width -->
        <div class="space-y-8">

            <!-- Our Process -->
            <div class="bg-white dark:bg-slate-900 p-8 rounded-xl shadow-xl border border-slate-100 dark:border-slate-800">
                <h4 class="text-xl font-bold mb-6"><?php esc_html_e( 'Our Process', 'himalayan-homestay-bookings' ); ?></h4>
                <div class="space-y-8">
                    <div class="flex gap-4">
                        <div class="w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center font-bold text-sm shrink-0">1</div>
                        <div>
                            <p class="font-bold text-slate-900 dark:text-slate-100"><?php echo esc_html( $step1_title ); ?></p>
                            <p class="text-sm text-slate-500 dark:text-slate-400"><?php echo esc_html( $step1_desc ); ?></p>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <div class="w-8 h-8 rounded-full bg-primary/20 text-primary flex items-center justify-center font-bold text-sm shrink-0">2</div>
                        <div>
                            <p class="font-bold text-slate-900 dark:text-slate-100"><?php echo esc_html( $step2_title ); ?></p>
                            <p class="text-sm text-slate-500 dark:text-slate-400"><?php echo esc_html( $step2_desc ); ?></p>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <div class="w-8 h-8 rounded-full bg-primary/20 text-primary flex items-center justify-center font-bold text-sm shrink-0">3</div>
                        <div>
                            <p class="font-bold text-slate-900 dark:text-slate-100"><?php echo esc_html( $step3_title ); ?></p>
                            <p class="text-sm text-slate-500 dark:text-slate-400"><?php echo esc_html( $step3_desc ); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Why Host with Us? -->
            <div class="bg-primary/10 p-8 rounded-xl border border-primary/20 relative overflow-hidden">
                <span class="material-symbols-outlined absolute -right-4 -bottom-4 text-9xl text-primary/5 rotate-12">verified</span>
                <h4 class="text-xl font-bold text-primary mb-4"><?php esc_html_e( 'Why Host with Us?', 'himalayan-homestay-bookings' ); ?></h4>
                <ul class="space-y-4">
                    <li class="flex items-start gap-3">
                        <span class="material-symbols-outlined text-primary text-xl">check_circle</span>
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300"><?php echo esc_html( $benefit1 ); ?></span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="material-symbols-outlined text-primary text-xl">check_circle</span>
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300"><?php echo esc_html( $benefit2 ); ?></span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="material-symbols-outlined text-primary text-xl">check_circle</span>
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300"><?php echo esc_html( $benefit3 ); ?></span>
                    </li>
                </ul>
            </div>

        </div>
    </div>
</section>

<script>
(function() {
    const form = document.getElementById('hm-host-application-form');
    if (!form) return;

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(form);
        formData.append('action', 'hhb_host_application');

        const btn = form.querySelector('button[type="submit"]');
        const originalText = btn.textContent;
        btn.textContent = '<?php echo esc_js( __( 'Submitting...', 'himalayan-homestay-bookings' ) ); ?>';
        btn.disabled = true;
        btn.style.opacity = '0.7';

        fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
            method: 'POST',
            body: formData,
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                form.style.display = 'none';
                document.getElementById('hm-host-success').classList.remove('hidden');
                document.getElementById('hm-host-success').scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else {
                alert(data.data || '<?php echo esc_js( __( 'Something went wrong. Please try again.', 'himalayan-homestay-bookings' ) ); ?>');
                btn.textContent = originalText;
                btn.disabled = false;
                btn.style.opacity = '1';
            }
        })
        .catch(() => {
            alert('<?php echo esc_js( __( 'Network error. Please try again.', 'himalayan-homestay-bookings' ) ); ?>');
            btn.textContent = originalText;
            btn.disabled = false;
            btn.style.opacity = '1';
        });
    });
})();
</script>

<?php get_footer(); ?>
