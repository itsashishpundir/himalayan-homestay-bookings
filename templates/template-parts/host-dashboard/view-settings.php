<?php
/**
 * View: Settings (Host Profile)
 *
 * @package Himalayan\Homestay
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$user = wp_get_current_user();
$avatar_id  = get_user_meta( $user->ID, 'hhb_avatar_id', true );
$avatar_url = $avatar_id ? wp_get_attachment_image_url( $avatar_id, 'thumbnail' ) : get_avatar_url( $user->ID );
?>

<div class="hhb-dashboard-block" style="background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 32px; max-width: 600px;">

    <h2 style="font-size: 20px; font-weight: 600; color: #0f172a; margin-top: 0; margin-bottom: 24px;">Profile Settings</h2>

    <form id="hhb-settings-form" method="post" enctype="multipart/form-data">
        <?php wp_nonce_field( 'hhb_save_settings_nonce', 'hhb_settings_nonce' ); ?>
        <input type="hidden" name="action" value="hhb_save_host_settings">

        <!-- Avatar -->
        <div style="margin-bottom: 24px; display: flex; align-items: center; gap: 24px;">
            <div id="hhb-avatar-preview-container" style="width: 80px; height: 80px; border-radius: 50%; overflow: hidden; background: #e2e8f0; flex-shrink: 0;">
                <img id="hhb-avatar-preview" src="<?php echo esc_url( $avatar_url ); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
            </div>
            <div>
                <label style="display: block; font-weight: 500; color: #334155; margin-bottom: 8px;">Profile Picture</label>
                <input type="hidden" name="hhb_avatar_id" id="hhb-avatar-id" value="<?php echo esc_attr( $avatar_id ); ?>">
                <button type="button" id="hhb-upload-avatar-btn" style="background: #fff; border: 1px solid #cbd5e1; color: #475569; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500;">Choose Image</button>
                <button type="button" id="hhb-remove-avatar-btn" style="background: transparent; border: none; color: #ef4444; padding: 8px 16px; cursor: pointer; font-size: 14px; font-weight: 500; <?php echo $avatar_id ? '' : 'display: none;'; ?>">Remove</button>
            </div>
        </div>

        <div style="margin-bottom: 20px;">
            <label for="first_name" style="display: block; font-weight: 500; color: #334155; margin-bottom: 8px;">First Name</label>
            <input type="text" id="first_name" name="first_name" value="<?php echo esc_attr( $user->first_name ); ?>" style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 15px; box-sizing: border-box;">
        </div>

        <div style="margin-bottom: 20px;">
            <label for="last_name" style="display: block; font-weight: 500; color: #334155; margin-bottom: 8px;">Last Name</label>
            <input type="text" id="last_name" name="last_name" value="<?php echo esc_attr( $user->last_name ); ?>" style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 15px; box-sizing: border-box;">
        </div>

        <div style="margin-bottom: 20px;">
            <label for="user_email" style="display: block; font-weight: 500; color: #334155; margin-bottom: 8px;">Email Address</label>
            <input type="email" id="user_email" name="user_email" value="<?php echo esc_attr( $user->user_email ); ?>" required style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 15px; box-sizing: border-box;">
        </div>

        <div style="margin-bottom: 32px;">
            <label for="description" style="display: block; font-weight: 500; color: #334155; margin-bottom: 8px;">Short Biography</label>
            <textarea id="description" name="description" rows="4" style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 15px; box-sizing: border-box;"><?php echo esc_textarea( $user->description ); ?></textarea>
        </div>

        <div style="display: flex; gap: 16px;">
            <button type="submit" id="hhb-save-settings-btn" style="background: #2563eb; color: #fff; border: none; padding: 10px 24px; border-radius: 6px; font-weight: 500; cursor: pointer;">Save Changes</button>
            <span id="hhb-settings-spinner" style="display: none; align-self: center;" class="material-symbols-outlined hhb-spin">sync</span>
        </div>
        <div id="hhb-settings-messages" style="margin-top: 16px;"></div>
    </form>
</div>

<style>
@keyframes hhbSpin { 100% { transform: rotate(360deg); } }
.hhb-spin { animation: hhbSpin 1s linear infinite; color: #64748b; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let avatarFrame;
    const btnUpload = document.getElementById('hhb-upload-avatar-btn');
    const btnRemove = document.getElementById('hhb-remove-avatar-btn');
    const inputId   = document.getElementById('hhb-avatar-id');
    const imgPrev   = document.getElementById('hhb-avatar-preview');

    btnUpload.addEventListener('click', function(e) {
        e.preventDefault();
        if (avatarFrame) { avatarFrame.open(); return; }
        avatarFrame = wp.media({ title: 'Select Profile Picture', button: { text: 'Use this media' }, multiple: false });
        avatarFrame.on('select', function() {
            const att = avatarFrame.state().get('selection').first().toJSON();
            inputId.value = att.id;
            imgPrev.src = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
            btnRemove.style.display = 'inline-block';
        });
        avatarFrame.open();
    });

    btnRemove.addEventListener('click', function(e) {
        e.preventDefault();
        inputId.value = '';
        imgPrev.src = '';
        btnRemove.style.display = 'none';
    });

    document.getElementById('hhb-settings-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const spinner   = document.getElementById('hhb-settings-spinner');
        const btnSubmit = document.getElementById('hhb-save-settings-btn');
        const msgDiv    = document.getElementById('hhb-settings-messages');
        spinner.style.display = 'inline-block';
        btnSubmit.disabled = true;
        btnSubmit.style.opacity = '0.7';
        msgDiv.innerHTML = '';

        fetch('<?php echo admin_url("admin-ajax.php"); ?>', { method: 'POST', body: new FormData(this) })
        .then(r => r.json())
        .then(data => {
            spinner.style.display = 'none';
            btnSubmit.disabled = false;
            btnSubmit.style.opacity = '1';
            if (data.success) {
                msgDiv.innerHTML = `<div style="padding:12px 16px;background:#ecfdf5;color:#059669;border-radius:6px;">${data.data}</div>`;
            } else {
                msgDiv.innerHTML = `<div style="padding:12px 16px;background:#fef2f2;color:#dc2626;border-radius:6px;">${data.data}</div>`;
            }
        })
        .catch(() => {
            spinner.style.display = 'none';
            btnSubmit.disabled = false;
            btnSubmit.style.opacity = '1';
            msgDiv.innerHTML = `<div style="padding:12px 16px;background:#fef2f2;color:#dc2626;border-radius:6px;">An unexpected error occurred.</div>`;
        });
    });
});
</script>
