<?php
/**
 * Provider-related sanitizers
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

trait Humata_Chatbot_Admin_Settings_Sanitize_Providers_Trait {

    private function get_anthropic_model_options() {
        $models = array(
            'claude-opus-4-5-20251101'   => 'Claude Opus 4.5 (Nov 2025)',
            'claude-sonnet-4-5-20250929' => 'Claude Sonnet 4.5 (Sep 2025)',
            'claude-haiku-4-5-20251001'  => 'Claude Haiku 4.5 (Oct 2025)',
            // Legacy/older models below
            'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet (Oct 2024)',
            'claude-3-5-haiku-20241022'  => 'Claude 3.5 Haiku (Oct 2024)',
            'claude-3-opus-20240229'     => 'Claude 3 Opus (Feb 2024)',
        );

        /**
         * Allow overriding the Claude model dropdown options.
         *
         * @since 1.0.0
         * @param array $models Map of model_id => label.
         */
        $models = apply_filters( 'humata_chatbot_anthropic_models', $models );

        if ( ! is_array( $models ) ) {
            $models = array();
        }

        $clean = array();
        foreach ( $models as $model_id => $label ) {
            $model_id = sanitize_text_field( (string) $model_id );
            if ( '' === $model_id ) {
                continue;
            }

            $label = is_string( $label ) ? $label : (string) $label;
            $label = sanitize_text_field( $label );
            if ( '' === $label ) {
                $label = $model_id;
            }

            $clean[ $model_id ] = $label;
        }

        if ( empty( $clean ) ) {
            $clean = array( 'claude-3-5-sonnet-20241022' => 'claude-3-5-sonnet-20241022' );
        }

        return $clean;
    }

    public function sanitize_anthropic_model( $value ) {
        $value  = sanitize_text_field( (string) $value );
        $models = $this->get_anthropic_model_options();

        if ( isset( $models[ $value ] ) ) {
            return $value;
        }

        $keys = array_keys( $models );
        return isset( $keys[0] ) ? $keys[0] : 'claude-3-5-sonnet-20241022';
    }

    /**
     * Get available OpenRouter model options.
     *
     * @since 1.0.0
     * @return array Map of model_id => label.
     */
    public function get_openrouter_model_options() {
        $models = array(
            HUMATA_DEFAULT_OPENROUTER_MODEL    => 'Mistral Medium 3.1',
            'z-ai/glm-4.7'                    => 'Z.AI: GLM 4.7',
            'google/gemini-3-flash-preview'   => 'Gemini 3 Flash Preview',
        );

        /**
         * Allow overriding the OpenRouter model dropdown options.
         *
         * @since 1.0.0
         * @param array $models Map of model_id => label.
         */
        $models = apply_filters( 'humata_chatbot_openrouter_models', $models );

        if ( ! is_array( $models ) ) {
            $models = array();
        }

        $clean = array();
        foreach ( $models as $model_id => $label ) {
            $model_id = sanitize_text_field( (string) $model_id );
            if ( '' === $model_id ) {
                continue;
            }

            $label = is_string( $label ) ? $label : (string) $label;
            $label = sanitize_text_field( $label );
            if ( '' === $label ) {
                $label = $model_id;
            }

            $clean[ $model_id ] = $label;
        }

        if ( empty( $clean ) ) {
            $clean = array( HUMATA_DEFAULT_OPENROUTER_MODEL => 'Mistral Medium 3.1' );
        }

        return $clean;
    }

    /**
     * Sanitize OpenRouter model selection.
     *
     * @since 1.0.0
     * @param string $value Model ID.
     * @return string Sanitized model ID.
     */
    public function sanitize_openrouter_model( $value ) {
        $value  = sanitize_text_field( (string) $value );
        $models = $this->get_openrouter_model_options();

        if ( isset( $models[ $value ] ) ) {
            return $value;
        }

        $keys = array_keys( $models );
        return isset( $keys[0] ) ? $keys[0] : HUMATA_DEFAULT_OPENROUTER_MODEL;
    }
}


