<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function koba_render_security_vault() {
    if (isset($_POST['koba_save_security'])) {
        update_option('koba_google_json_path', stripslashes(sanitize_text_field($_POST['google_path'])));
        update_option('koba_google_bucket', sanitize_text_field($_POST['google_bucket']));
        echo '<div class="updated"><p>Vault Locked.</p></div>';
    }
    ?>
    <div class="wrap" style="background:#020617; color:white; padding:40px; border-radius:12px;">
        <h1 style="color:#f97316;">Security Vault</h1>
        <form method="post">
            <p>JSON Path: <input type="text" name="google_path" value="<?php echo esc_attr(get_option('koba_google_json_path')); ?>" style="width:100%;"></p>
            <p>Bucket Name: <input type="text" name="google_bucket" value="<?php echo esc_attr(get_option('koba_google_bucket')); ?>" style="width:100%;"></p>
            <input type="submit" name="koba_save_security" class="button button-primary" value="SAVE VAULT">
        </form>
    </div>
    <?php
}