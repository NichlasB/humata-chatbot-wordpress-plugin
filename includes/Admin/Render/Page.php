<?php
/**
 * Settings page renderers
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

trait Humata_Chatbot_Admin_Settings_Render_Page_Trait {

    /**
     * Render the settings form for a given Settings API page ID.
     *
     * @since 1.0.0
     * @param string $page_id    Settings API page ID used by `do_settings_sections()`.
     * @param string $active_tab Active tab key.
     * @return void
     */
    public function render_tab_form( $page_id, $active_tab ) {
        $page_id    = sanitize_key( (string) $page_id );
        $active_tab = sanitize_key( (string) $active_tab );
        ?>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'humata_chatbot_settings' );
            echo '<input type="hidden" name="humata_active_tab" value="' . esc_attr( $active_tab ) . '" />';
            do_settings_sections( $page_id );
            submit_button( __( 'Save Settings', 'humata-chatbot' ) );
            ?>
        </form>
        <?php
    }

    /**
     * Render the settings page.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Check if settings were saved
        if ( isset( $_GET['settings-updated'] ) ) {
            // Flush rewrite rules when settings are updated
            flush_rewrite_rules();
        }

        $tabs       = $this->get_tabs_controller();
        $active_tab = $tabs->get_active_tab();
        $modules    = $this->get_tab_modules();

        if ( ! isset( $modules[ $active_tab ] ) ) {
            $default    = $tabs->get_default_tab();
            $active_tab = isset( $modules[ $default ] ) ? $default : $active_tab;
        }
        ?>
        <div class="wrap humata-settings-wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <?php settings_errors( 'humata_chatbot_messages' ); ?>
            <?php $tabs->render_tabs_nav( $active_tab ); ?>
            <?php
            if ( isset( $modules[ $active_tab ] ) && is_object( $modules[ $active_tab ] ) && method_exists( $modules[ $active_tab ], 'render' ) ) {
                $modules[ $active_tab ]->render();
            }
            ?>
        </div>
        <?php
    }
}


