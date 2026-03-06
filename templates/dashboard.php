<?php
/**
 * Plugin Template: Host Dashboard
 *
 * The secure frontend dashboard for homestay hosts.
 * Loads view partials from the plugin's own template-parts/ directory.
 * Theme can override by providing page-host-panel.php in the theme root.
 *
 * @package Himalayan\Homestay
 */

// 1. Authentication Check
if ( ! is_user_logged_in() ) {
    wp_redirect( wp_login_url( get_permalink() ) );
    exit;
}

// 2. Role Check (Must be an active Host or Admin)
$current_user = wp_get_current_user();
if ( ! in_array( 'hhb_host', (array) $current_user->roles ) && ! in_array( 'administrator', (array) $current_user->roles ) ) {
    wp_die( __( 'You do not have permission to access the Host Dashboard. Please apply to become a host.', 'himalayan-homestay-bookings' ) );
}

// 3. Routing
$current_view = sanitize_text_field( $_GET['view'] ?? 'overview' );
$valid_views  = [ 'overview', 'properties', 'edit-property', 'bookings', 'calendar', 'payouts', 'settings' ];
if ( ! in_array( $current_view, $valid_views, true ) ) {
    $current_view = 'overview';
}
$dashboard_url = get_permalink();

get_header();
?>

<div class="hhb-dashboard-wrapper" style="background-color: #f8fafc; min-height: 100vh; display: flex; font-family: 'Inter', sans-serif;">

    <!-- Sidebar -->
    <aside class="hhb-dashboard-sidebar" style="width: 260px; background: #ffffff; border-right: 1px solid #e2e8f0; padding: 24px 0; display: flex; flex-direction: column;">
        <div style="padding: 0 24px 24px; border-bottom: 1px solid #e2e8f0; margin-bottom: 24px;">
            <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; margin: 0;">Host Panel</h2>
        </div>

        <nav style="flex: 1; padding: 0 16px;">
            <?php
            $menu_items = [
                'overview'   => [ 'icon' => 'dashboard', 'label' => 'Dashboard' ],
                'properties' => [ 'icon' => 'home', 'label' => 'My Properties' ],
                'bookings'   => [ 'icon' => 'receipt_long', 'label' => 'Bookings' ],
                'calendar'   => [ 'icon' => 'calendar_month', 'label' => 'Calendar' ],
                'payouts'    => [ 'icon' => 'account_balance', 'label' => 'Payouts' ],
                'settings'   => [ 'icon' => 'settings', 'label' => 'Settings' ],
            ];

            foreach ( $menu_items as $view_id => $item ) :
                $is_active    = ( $current_view === $view_id || ( $current_view === 'edit-property' && $view_id === 'properties' ) );
                $active_style = $is_active ? 'background: #eff6ff; color: #2563eb; font-weight: 600;' : 'color: #64748b;';
            ?>
                <a href="<?php echo esc_url( add_query_arg( 'view', $view_id, $dashboard_url ) ); ?>"
                   style="display: flex; items-center; padding: 12px 16px; border-radius: 8px; text-decoration: none; margin-bottom: 8px; transition: all 0.2s; <?php echo $active_style; ?>">
                    <span class="material-symbols-outlined" style="margin-right: 12px; font-size: 20px;"><?php echo esc_html( $item['icon'] ); ?></span>
                    <?php echo esc_html( $item['label'] ); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div style="padding: 24px 16px; border-top: 1px solid #e2e8f0; margin-top: auto;">
            <a href="<?php echo wp_logout_url( home_url() ); ?>" style="display: flex; items-center; padding: 12px 16px; color: #ef4444; text-decoration: none; border-radius: 8px; transition: all 0.2s;">
                <span class="material-symbols-outlined" style="margin-right: 12px; font-size: 20px;">logout</span>
                Logout
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="hhb-dashboard-main" style="flex: 1; padding: 40px; overflow-y: auto;">

        <!-- Top Bar -->
        <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px;">
            <div>
                <h1 style="font-size: 24px; font-weight: 700; color: #0f172a; margin: 0;">
                    <?php
                    if ( $current_view === 'edit-property' ) {
                        echo 'Edit Property';
                    } else {
                        echo esc_html( $menu_items[ $current_view ]['label'] );
                    }
                    ?>
                </h1>
            </div>
            <div style="display: flex; align-items: center; gap: 16px;">
                <span class="material-symbols-outlined" style="color: #64748b; cursor: pointer;">notifications</span>
                <div style="width: 40px; height: 40px; border-radius: 50%; background: #e2e8f0; overflow: hidden; display: flex; align-items: center; justify-content: center;">
                    <?php
                    $header_avatar_id = get_user_meta( $current_user->ID, 'hhb_avatar_id', true );
                    if ( $header_avatar_id ) {
                        echo wp_get_attachment_image( $header_avatar_id, [40, 40], false, ['style' => 'width: 100%; height: 100%; object-fit: cover;'] );
                    } else {
                        echo get_avatar( $current_user->ID, 40, '', '', ['style' => 'width: 100%; height: 100%; object-fit: cover;'] );
                    }
                    ?>
                </div>
            </div>
        </header>

        <!-- Dynamic View Content — loads from plugin templates/template-parts/host-dashboard/ -->
        <div class="hhb-dashboard-content">
            <?php
            // First check if the theme overrides the view file
            $theme_view = locate_template( [ "template-parts/host-dashboard/view-{$current_view}.php" ] );
            if ( $theme_view ) {
                require $theme_view;
            } else {
                $plugin_view = HHB_PLUGIN_DIR . "templates/template-parts/host-dashboard/view-{$current_view}.php";
                if ( file_exists( $plugin_view ) ) {
                    require $plugin_view;
                } else {
                    echo "<div style='padding:40px; text-align:center; background:#fff; border-radius:12px; border:1px solid #e2e8f0;'>
                            <span class='material-symbols-outlined' style='font-size:48px; color:#cbd5e1; margin-bottom:16px;'>construction</span>
                            <h3 style='margin:0; color:#334155;'>View Under Construction</h3>
                            <p style='color:#64748b; margin-top:8px;'>The <code>{$current_view}</code> view is currently being built.</p>
                          </div>";
                }
            }
            ?>
        </div>

    </main>

</div>

<?php wp_footer(); ?>
</body>
</html>
