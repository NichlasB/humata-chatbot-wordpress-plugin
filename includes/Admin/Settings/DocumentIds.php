<?php
/**
 * Document IDs helpers + sanitizers
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

trait Humata_Chatbot_Admin_Settings_DocumentIds_Trait {

    private function parse_document_ids( $value ) {
        if ( ! is_string( $value ) ) {
            return array();
        }

        preg_match_all( '/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/i', $value, $matches );

        if ( empty( $matches[0] ) ) {
            return array();
        }

        $ids   = array_map( 'strtolower', $matches[0] );
        $seen  = array();
        $clean = array();

        foreach ( $ids as $id ) {
            if ( isset( $seen[ $id ] ) ) {
                continue;
            }

            $seen[ $id ] = true;
            $clean[]     = $id;
        }

        return $clean;
    }

    /**
     * Sanitize document IDs.
     *
     * @since 1.0.0
     * @param string $value Input value.
     * @return string Sanitized value.
     */
    public function sanitize_document_ids( $value ) {
        $ids = $this->parse_document_ids( $value );
        $ids = array_map( 'sanitize_text_field', $ids );

        $titles = get_option( 'humata_document_titles', array() );
        if ( is_array( $titles ) ) {
            $titles = array_intersect_key( $titles, array_flip( $ids ) );
            update_option( 'humata_document_titles', $titles );
        }

        return implode( ', ', $ids );
    }
}


