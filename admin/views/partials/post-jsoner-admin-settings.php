<div class="wrap">
    <?php settings_errors(); ?>
    <form method="POST" action="options.php">
        <?php
        settings_fields( 'post_jsoner_general_settings' );
        do_settings_sections( 'post_jsoner_general_settings' );
        ?>
        <?php submit_button(); ?>
    </form>
</div>