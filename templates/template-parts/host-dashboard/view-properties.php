<?php
/**
 * Host Dashboard — Properties View
 *
 * @package Himalayan\Homestay
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$current_user_id = get_current_user_id();
$properties = get_posts([
    'post_type'      => 'hhb_homestay',
    'author'         => $current_user_id,
    'posts_per_page' => -1,
    'post_status'    => [ 'publish', 'draft', 'pending' ],
    'orderby'        => 'title',
    'order'          => 'ASC'
]);
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
    <h2 style="margin: 0; font-size: 20px; color: #0f172a;">My Properties</h2>
    <a href="<?php echo esc_url( add_query_arg( 'view', 'edit-property', $dashboard_url ) ); ?>" class="hhb-btn" style="text-decoration: none;">+ Add New Property</a>
</div>

<?php if ( empty( $properties ) ) : ?>
    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:40px; text-align:center;">
        <span class="material-symbols-outlined" style="font-size:48px; color:#cbd5e1; margin-bottom:16px;">holiday_village</span>
        <h2 style="margin:0 0 8px; color:#1e293b; font-size:20px;">No Properties Yet</h2>
        <p style="color:#64748b; margin-top:0;">You haven't listed any properties. Click the button above to get started.</p>
    </div>
<?php else : ?>

<style>
.hhb-table-container { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
.hhb-table { width: 100%; border-collapse: collapse; text-align: left; }
.hhb-table th { background: #f8fafc; padding: 16px 24px; font-size: 13px; font-weight: 600; color: #64748b; border-bottom: 1px solid #e2e8f0; text-transform: uppercase; letter-spacing: 0.5px;}
.hhb-table td { padding: 16px 24px; border-bottom: 1px solid #e2e8f0; color: #334155; font-size: 14px; vertical-align: middle; }
.hhb-table tr:last-child td { border-bottom: none; }
.hhb-status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
.hhb-status-publish { background: #dcfce7; color: #166534; }
.hhb-status-draft { background: #f1f5f9; color: #475569; }
.hhb-status-pending { background: #fef9c3; color: #854d0e; }
.hhb-prop-thumb { width: 64px; height: 48px; object-fit: cover; border-radius: 6px; border: 1px solid #e2e8f0; flex-shrink: 0; }
</style>

<div class="hhb-table-container">
    <table class="hhb-table">
        <thead>
            <tr>
                <th>Property</th>
                <th>Location</th>
                <th>Status</th>
                <th>Base Price</th>
                <th style="text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $properties as $post ) :
                $img_id = get_post_thumbnail_id( $post->ID );
                $thumb_url = $img_id ? wp_get_attachment_image_url( $img_id, 'thumbnail' ) : HHB_PLUGIN_URL . 'assets/img/placeholder.png';

                $locations = get_the_terms( $post->ID, 'hhb_location' );
                $location  = ( $locations && ! is_wp_error( $locations ) ) ? implode( ', ', wp_list_pluck( $locations, 'name' ) ) : 'Location not set';

                $price    = get_post_meta( $post->ID, 'base_price_per_night', true );
                $currency = get_post_meta( $post->ID, 'currency', true ) ?: 'INR';
                $sym      = [ 'USD' => '$', 'INR' => '₹', 'EUR' => '€', 'GBP' => '£', 'NPR' => 'रु' ][ strtoupper($currency) ] ?? $currency;

                $status_class = 'hhb-status-' . $post->post_status;
                $status_label = ucwords( $post->post_status );
                if ( $post->post_status === 'publish' ) $status_label = 'Active';
                $status_icon = $post->post_status === 'publish' ? 'check_circle' : 'pending';
            ?>
            <tr>
                <td>
                    <div style="display: flex; align-items: center; gap: 16px;">
                        <img src="<?php echo esc_url( $thumb_url ); ?>" class="hhb-prop-thumb" alt="">
                        <a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" target="_blank" style="color: #0f172a; font-weight: 600; text-decoration: none; font-size: 15px;">
                            <?php echo esc_html( $post->post_title ?: '(No Title)' ); ?>
                        </a>
                    </div>
                </td>
                <td style="color: #64748b;"><?php echo esc_html( $location ); ?></td>
                <td>
                    <span class="hhb-status-badge <?php echo esc_attr( $status_class ); ?>">
                        <span class="material-symbols-outlined" style="font-size: 14px;"><?php echo esc_html( $status_icon ); ?></span>
                        <?php echo esc_html( $status_label ); ?>
                    </span>
                </td>
                <td style="font-weight: 600;">
                    <?php echo $price ? esc_html( $sym . ' ' . $price ) . ' <span style="font-size:12px;color:#94a3b8;font-weight:400;">/night</span>' : 'Not set'; ?>
                </td>
                <td style="text-align: right;">
                    <a href="<?php echo esc_url( add_query_arg( ['view' => 'edit-property', 'id' => $post->ID], $dashboard_url ) ); ?>" class="button button-secondary" style="display: inline-flex; align-items: center; gap: 6px; border: 1px solid #e2e8f0; padding: 6px 12px; border-radius: 6px; color: #334155; text-decoration: none; font-size: 13px; font-weight: 600; margin-right: 8px;">
                        <span class="material-symbols-outlined" style="font-size: 16px;">edit</span> Edit
                    </a>
                    <button class="button button-secondary hhb-delete-property" data-id="<?php echo esc_attr( $post->ID ); ?>" style="display: inline-flex; align-items: center; gap: 6px; border: 1px solid #fee2e2; background: #fef2f2; padding: 6px 12px; border-radius: 6px; color: #dc2626; font-size: 13px; font-weight: 600; cursor: pointer;">
                        <span class="material-symbols-outlined" style="font-size: 16px;">delete</span>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.hhb-delete-property').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            if ( ! confirm('Are you sure you want to delete this property? This action cannot be undone.') ) return;
            const propId = this.dataset.id;
            const btnEl = this;
            btnEl.innerHTML = '<span class="material-symbols-outlined hhb-spin" style="font-size: 16px;">sync</span>';
            btnEl.disabled = true;

            const formData = new FormData();
            formData.append('action', 'hhb_delete_property');
            formData.append('property_id', propId);
            formData.append('security', '<?php echo wp_create_nonce("hhb_delete_property_nonce"); ?>');

            fetch('<?php echo admin_url("admin-ajax.php"); ?>', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    btnEl.closest('tr').remove();
                } else {
                    alert(data.data || 'Failed to delete property.');
                    btnEl.innerHTML = '<span class="material-symbols-outlined" style="font-size: 16px;">delete</span>';
                    btnEl.disabled = false;
                }
            })
            .catch(() => {
                alert('An error occurred.');
                btnEl.innerHTML = '<span class="material-symbols-outlined" style="font-size: 16px;">delete</span>';
                btnEl.disabled = false;
            });
        });
    });
});
</script>

<?php endif;
