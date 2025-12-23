<?php
/**
 * Display/UX-related field renderers
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

trait Humata_Chatbot_Admin_Settings_Render_Display_Trait {

    /**
     * Render location field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_location_field() {
        $location = get_option( 'humata_chat_location', 'dedicated' );
        $slug     = get_option( 'humata_chat_page_slug', 'chat' );
        ?>
        <fieldset class="radio-group">
            <label>
                <input
                    type="radio"
                    name="humata_chat_location"
                    value="homepage"
                    <?php checked( $location, 'homepage' ); ?>
                />
                <?php esc_html_e( 'Replace Homepage', 'humata-chatbot' ); ?>
                <span class="description"><?php esc_html_e( '— Chat interface replaces your site homepage', 'humata-chatbot' ); ?></span>
            </label>

            <label>
                <input
                    type="radio"
                    name="humata_chat_location"
                    value="dedicated"
                    <?php checked( $location, 'dedicated' ); ?>
                />
                <?php esc_html_e( 'Dedicated Page', 'humata-chatbot' ); ?>
                <span class="description"><?php esc_html_e( '— Chat available at a custom URL', 'humata-chatbot' ); ?></span>
            </label>

            <div class="conditional-field">
                <div class="slug-input-wrapper">
                    <span class="slug-prefix"><?php echo esc_html( home_url( '/' ) ); ?></span>
                    <input
                        type="text"
                        id="humata_chat_page_slug"
                        name="humata_chat_page_slug"
                        value="<?php echo esc_attr( $slug ); ?>"
                        class="regular-text"
                        style="width: 150px;"
                    />
                </div>
            </div>

            <label>
                <input
                    type="radio"
                    name="humata_chat_location"
                    value="shortcode"
                    <?php checked( $location, 'shortcode' ); ?>
                />
                <?php esc_html_e( 'Shortcode Only', 'humata-chatbot' ); ?>
                <span class="description"><?php esc_html_e( '— Embed via [humata_chat] shortcode', 'humata-chatbot' ); ?></span>
            </label>
        </fieldset>
        <?php
    }

    /**
     * Render theme field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_theme_field() {
        $theme = get_option( 'humata_chat_theme', 'auto' );
        ?>
        <fieldset class="radio-group">
            <label>
                <input
                    type="radio"
                    name="humata_chat_theme"
                    value="light"
                    <?php checked( $theme, 'light' ); ?>
                />
                <?php esc_html_e( 'Light Mode', 'humata-chatbot' ); ?>
            </label>

            <label>
                <input
                    type="radio"
                    name="humata_chat_theme"
                    value="dark"
                    <?php checked( $theme, 'dark' ); ?>
                />
                <?php esc_html_e( 'Dark Mode', 'humata-chatbot' ); ?>
            </label>

            <label>
                <input
                    type="radio"
                    name="humata_chat_theme"
                    value="auto"
                    <?php checked( $theme, 'auto' ); ?>
                />
                <?php esc_html_e( 'Auto (System Preference)', 'humata-chatbot' ); ?>
            </label>
        </fieldset>
        <?php
    }

    /**
     * Render SEO indexing field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_allow_seo_indexing_field() {
        $value = get_option( 'humata_allow_seo_indexing', false );
        ?>
        <label>
            <input
                type="checkbox"
                name="humata_allow_seo_indexing"
                value="1"
                <?php checked( $value, true ); ?>
            />
            <?php esc_html_e( 'Allow search engines to index the chat page', 'humata-chatbot' ); ?>
        </label>
        <p class="description">
            <?php esc_html_e( 'When enabled and using "Replace Homepage" mode, search engines can index the page and SEO plugins can control meta tags. The dedicated chat page URL always remains noindexed.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    public function render_max_prompt_chars_field() {
        $value = get_option( 'humata_max_prompt_chars', 3000 );
        ?>
        <input
            type="number"
            id="humata_max_prompt_chars"
            name="humata_max_prompt_chars"
            value="<?php echo esc_attr( $value ); ?>"
            min="1"
            max="100000"
            class="small-text"
        />
        <span><?php esc_html_e( 'characters per message', 'humata-chatbot' ); ?></span>
        <p class="description">
            <?php esc_html_e( 'Limit how many characters a user can type into the chat input before sending. Default is 3000.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    public function render_medical_disclaimer_text_field() {
        $value = get_option( 'humata_medical_disclaimer_text', '' );
        if ( ! is_string( $value ) ) {
            $value = '';
        }
        ?>
        <textarea
            id="humata_medical_disclaimer_text"
            name="humata_medical_disclaimer_text"
            rows="6"
            class="large-text"
        ><?php echo esc_textarea( $value ); ?></textarea>
        <p class="description">
            <?php esc_html_e( 'Shown at the bottom of the dedicated chat page. Leave blank to disable. Use blank lines to separate paragraphs.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    public function render_footer_copyright_text_field() {
        $value = get_option( 'humata_footer_copyright_text', '' );
        if ( ! is_string( $value ) ) {
            $value = '';
        }
        ?>
        <input
            type="text"
            id="humata_footer_copyright_text"
            name="humata_footer_copyright_text"
            value="<?php echo esc_attr( $value ); ?>"
            class="regular-text"
        />
        <p class="description">
            <?php esc_html_e( 'Shown at the bottom of the dedicated chat page footer (below the medical disclaimer). Leave blank to disable.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    /**
     * Render bot response disclaimer field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_bot_response_disclaimer_field() {
        $value = get_option( 'humata_bot_response_disclaimer', '' );
        if ( ! is_string( $value ) ) {
            $value = '';
        }
        ?>
        <textarea
            id="humata_bot_response_disclaimer"
            name="humata_bot_response_disclaimer"
            rows="4"
            class="large-text"
        ><?php echo esc_textarea( $value ); ?></textarea>
        <p class="description">
            <?php esc_html_e( 'Displayed in a styled box below the latest AI response. Leave blank to disable.', 'humata-chatbot' ); ?><br>
            <?php esc_html_e( 'Supports: <strong>, <em>, <a href="..."> for links, bold, and italic text.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    /**
     * Render logo image field for light theme.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_logo_field() {
        $url = get_option( 'humata_logo_url', '' );
        ?>
        <div class="humata-logo-uploader">
            <input type="hidden" id="humata_logo_url" name="humata_logo_url" value="<?php echo esc_attr( $url ); ?>" />
            <button type="button" class="button humata-upload-avatar" data-target="humata_logo_url"><?php esc_html_e( 'Select Image', 'humata-chatbot' ); ?></button>
            <button type="button" class="button humata-remove-avatar" data-target="humata_logo_url" <?php echo empty( $url ) ? 'style="display:none;"' : ''; ?>><?php esc_html_e( 'Remove', 'humata-chatbot' ); ?></button>
            <div class="humata-logo-preview humata-logo-preview--light" id="humata_logo_url_preview">
                <?php if ( ! empty( $url ) ) : ?>
                    <img src="<?php echo esc_url( $url ); ?>" alt="<?php esc_attr_e( 'Logo Preview', 'humata-chatbot' ); ?>" />
                <?php endif; ?>
            </div>
        </div>
        <p class="description"><?php esc_html_e( 'Logo shown when the chat interface is in light mode. Use a dark-colored logo for best contrast.', 'humata-chatbot' ); ?></p>
        <?php
    }

    /**
     * Render logo image field for dark theme.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_logo_dark_field() {
        $url = get_option( 'humata_logo_url_dark', '' );
        ?>
        <div class="humata-logo-uploader">
            <input type="hidden" id="humata_logo_url_dark" name="humata_logo_url_dark" value="<?php echo esc_attr( $url ); ?>" />
            <button type="button" class="button humata-upload-avatar" data-target="humata_logo_url_dark"><?php esc_html_e( 'Select Image', 'humata-chatbot' ); ?></button>
            <button type="button" class="button humata-remove-avatar" data-target="humata_logo_url_dark" <?php echo empty( $url ) ? 'style="display:none;"' : ''; ?>><?php esc_html_e( 'Remove', 'humata-chatbot' ); ?></button>
            <div class="humata-logo-preview humata-logo-preview--dark" id="humata_logo_url_dark_preview">
                <?php if ( ! empty( $url ) ) : ?>
                    <img src="<?php echo esc_url( $url ); ?>" alt="<?php esc_attr_e( 'Logo Preview', 'humata-chatbot' ); ?>" />
                <?php endif; ?>
            </div>
        </div>
        <p class="description"><?php esc_html_e( 'Logo shown when the chat interface is in dark mode. Use a light-colored or white logo for best contrast.', 'humata-chatbot' ); ?></p>
        <?php
    }

    /**
     * Render user avatar image field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_user_avatar_field() {
        $url = get_option( 'humata_user_avatar_url', '' );
        ?>
        <div class="humata-avatar-uploader">
            <input type="hidden" id="humata_user_avatar_url" name="humata_user_avatar_url" value="<?php echo esc_attr( $url ); ?>" />
            <button type="button" class="button humata-upload-avatar" data-target="humata_user_avatar_url"><?php esc_html_e( 'Select Image', 'humata-chatbot' ); ?></button>
            <button type="button" class="button humata-remove-avatar" data-target="humata_user_avatar_url" <?php echo empty( $url ) ? 'style="display:none;"' : ''; ?>><?php esc_html_e( 'Remove', 'humata-chatbot' ); ?></button>
            <div class="humata-avatar-preview" id="humata_user_avatar_url_preview">
                <?php if ( ! empty( $url ) ) : ?>
                    <img src="<?php echo esc_url( $url ); ?>" alt="<?php esc_attr_e( 'User Avatar Preview', 'humata-chatbot' ); ?>" style="max-width:64px;max-height:64px;border-radius:6px;" />
                <?php endif; ?>
            </div>
        </div>
        <p class="description"><?php esc_html_e( 'Upload a custom avatar for user messages. Leave empty for default icon.', 'humata-chatbot' ); ?></p>
        <?php
    }

    /**
     * Render bot avatar image field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_bot_avatar_field() {
        $url = get_option( 'humata_bot_avatar_url', '' );
        ?>
        <div class="humata-avatar-uploader">
            <input type="hidden" id="humata_bot_avatar_url" name="humata_bot_avatar_url" value="<?php echo esc_attr( $url ); ?>" />
            <button type="button" class="button humata-upload-avatar" data-target="humata_bot_avatar_url"><?php esc_html_e( 'Select Image', 'humata-chatbot' ); ?></button>
            <button type="button" class="button humata-remove-avatar" data-target="humata_bot_avatar_url" <?php echo empty( $url ) ? 'style="display:none;"' : ''; ?>><?php esc_html_e( 'Remove', 'humata-chatbot' ); ?></button>
            <div class="humata-avatar-preview" id="humata_bot_avatar_url_preview">
                <?php if ( ! empty( $url ) ) : ?>
                    <img src="<?php echo esc_url( $url ); ?>" alt="<?php esc_attr_e( 'Bot Avatar Preview', 'humata-chatbot' ); ?>" style="max-width:64px;max-height:64px;border-radius:6px;" />
                <?php endif; ?>
            </div>
        </div>
        <p class="description"><?php esc_html_e( 'Upload a custom avatar for bot messages. Leave empty for default icon.', 'humata-chatbot' ); ?></p>
        <?php
    }

    /**
     * Render avatar size field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_avatar_size_field() {
        $size = absint( get_option( 'humata_avatar_size', 40 ) );
        if ( $size < 32 ) {
            $size = 32;
        }
        if ( $size > 64 ) {
            $size = 64;
        }
        ?>
        <input type="number" id="humata_avatar_size" name="humata_avatar_size" value="<?php echo esc_attr( $size ); ?>" min="32" max="64" step="1" class="small-text" />
        <span><?php esc_html_e( 'pixels', 'humata-chatbot' ); ?></span>
        <p class="description"><?php esc_html_e( 'Avatar display size on desktop (32–64 px). Mobile uses a fixed smaller size.', 'humata-chatbot' ); ?></p>
        <?php
    }
}


