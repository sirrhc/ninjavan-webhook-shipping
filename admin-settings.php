<?php
// Add the submenu to the WordPress admin menu
add_action('admin_menu', 'ninja_van_email_settings_menu');
function ninja_van_email_settings_menu() {
    add_options_page(
        'Ninja Van Email Settings',  // Page title
        'NV Email Notifications',       // Menu title
        'manage_options',            // Capability
        'ninja-van-email-settings',  // Menu slug
        'ninja_van_email_settings_page' // Callback function
    );
}

// Register settings and sections
add_action('admin_init', 'ninja_van_register_settings');
function ninja_van_register_settings() {
    // Register settings
    register_setting('ninja_van_email_settings_group', 'ninja_van_email_recipients');
    register_setting('ninja_van_email_settings_group', 'ninja_van_email_statuses');

    // Add settings section
    add_settings_section(
        'ninja_van_email_main_section',    // Section ID
        'Email Notification Settings',    // Section title
        'ninja_van_email_main_section_cb', // Callback for description
        'ninja-van-email-settings'        // Page slug
    );

    // Add "Email Recipients" field
    add_settings_field(
        'ninja_van_email_recipients',     // Field ID
        'Email Recipients',               // Field title
        'ninja_van_email_recipients_cb',  // Callback function
        'ninja-van-email-settings',       // Page slug
        'ninja_van_email_main_section'    // Section ID
    );

    // Add "Trigger Statuses" field
    add_settings_field(
        'ninja_van_email_statuses',       // Field ID
        'Trigger Statuses',               // Field title
        'ninja_van_email_statuses_cb',    // Callback function
        'ninja-van-email-settings',       // Page slug
        'ninja_van_email_main_section'    // Section ID
    );
}

// Section callback
function ninja_van_email_main_section_cb() {
    echo '<p>Configure the email notifications for Ninja Van orders based on statuses.</p>';
}

// Email recipients field callback
function ninja_van_email_recipients_cb() {
    $recipients = esc_attr(get_option('ninja_van_email_recipients', ''));
    echo '<textarea name="ninja_van_email_recipients" rows="5" cols="50" style="width: 100%;">' . esc_textarea($recipients) . '</textarea>';
    echo '<p>Enter email addresses separated by commas. Example: user1@example.com, user2@example.com</p>';
}

// Trigger statuses field callback
function ninja_van_email_statuses_cb() {
    $statuses = esc_attr(get_option('ninja_van_email_statuses', 'Cancelled'));
    echo '<input type="text" name="ninja_van_email_statuses" value="' . $statuses . '" style="width: 100%;">';
    echo '<p>Enter statuses separated by commas. Example: Cancelled, Pending, Shipped</p>';
}

// Settings page callback
function ninja_van_email_settings_page() {
    ?>
    <div class="wrap">
        <h1>Ninja Van Email Notifications</h1>
        <form method="post" action="options.php">
            <?php
            // Render settings fields
            settings_fields('ninja_van_email_settings_group');
            do_settings_sections('ninja-van-email-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

?>
