<?php
/**
 * Suggested Questions Settings Tab
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

class Humata_Chatbot_Settings_Tab_Suggested_Questions extends Humata_Chatbot_Settings_Tab_Base {

	public function get_key() {
		return 'suggested_questions';
	}

	public function get_label() {
		return __( 'Suggested Questions', 'humata-chatbot' );
	}

	public function get_page_id() {
		return 'humata-chatbot-tab-suggested-questions';
	}

	public function register() {
		$page_id = $this->get_page_id();

		// Main section.
		add_settings_section(
			'humata_suggested_questions_section',
			__( 'Suggested Questions', 'humata-chatbot' ),
			array( $this->admin, 'render_suggested_questions_section' ),
			$page_id
		);

		add_settings_field(
			'humata_suggested_questions_enabled',
			__( 'Enable', 'humata-chatbot' ),
			array( $this->admin, 'render_suggested_questions_enabled_field' ),
			$page_id,
			'humata_suggested_questions_section'
		);

		add_settings_field(
			'humata_suggested_questions_mode',
			__( 'Mode', 'humata-chatbot' ),
			array( $this->admin, 'render_suggested_questions_mode_field' ),
			$page_id,
			'humata_suggested_questions_section'
		);

		// Fixed mode section.
		add_settings_section(
			'humata_sq_fixed_section',
			__( 'Fixed Mode Questions', 'humata-chatbot' ),
			'__return_null',
			$page_id
		);

		add_settings_field(
			'humata_sq_fixed_questions',
			__( 'Questions', 'humata-chatbot' ),
			array( $this->admin, 'render_fixed_questions_field' ),
			$page_id,
			'humata_sq_fixed_section'
		);

		// Randomized mode section.
		add_settings_section(
			'humata_sq_randomized_section',
			__( 'Randomized Mode Categories', 'humata-chatbot' ),
			'__return_null',
			$page_id
		);

		add_settings_field(
			'humata_sq_randomized_categories',
			__( 'Categories', 'humata-chatbot' ),
			array( $this->admin, 'render_randomized_categories_field' ),
			$page_id,
			'humata_sq_randomized_section'
		);

		// AI Follow-Up Questions section.
		add_settings_section(
			'humata_followup_questions_section',
			__( 'AI Follow-Up Questions', 'humata-chatbot' ),
			array( $this->admin, 'render_followup_questions_section' ),
			$page_id
		);

		add_settings_field(
			'humata_followup_enabled',
			__( 'Enable', 'humata-chatbot' ),
			array( $this->admin, 'render_followup_questions_enabled_field' ),
			$page_id,
			'humata_followup_questions_section'
		);

		add_settings_field(
			'humata_followup_provider',
			__( 'LLM Provider', 'humata-chatbot' ),
			array( $this->admin, 'render_followup_questions_provider_field' ),
			$page_id,
			'humata_followup_questions_section'
		);

		add_settings_field(
			'humata_followup_straico_api_keys',
			__( 'Straico API Keys', 'humata-chatbot' ),
			array( $this->admin, 'render_followup_straico_api_keys_field' ),
			$page_id,
			'humata_followup_questions_section'
		);

		add_settings_field(
			'humata_followup_straico_model',
			__( 'Straico Model', 'humata-chatbot' ),
			array( $this->admin, 'render_followup_straico_model_field' ),
			$page_id,
			'humata_followup_questions_section'
		);

		add_settings_field(
			'humata_followup_anthropic_api_keys',
			__( 'Anthropic API Keys', 'humata-chatbot' ),
			array( $this->admin, 'render_followup_anthropic_api_keys_field' ),
			$page_id,
			'humata_followup_questions_section'
		);

		add_settings_field(
			'humata_followup_anthropic_model',
			__( 'Anthropic Model', 'humata-chatbot' ),
			array( $this->admin, 'render_followup_anthropic_model_field' ),
			$page_id,
			'humata_followup_questions_section'
		);

		add_settings_field(
			'humata_followup_anthropic_extended_thinking',
			__( 'Extended Thinking', 'humata-chatbot' ),
			array( $this->admin, 'render_followup_anthropic_extended_thinking_field' ),
			$page_id,
			'humata_followup_questions_section'
		);

		add_settings_field(
			'humata_followup_openrouter_api_keys',
			__( 'OpenRouter API Keys', 'humata-chatbot' ),
			array( $this->admin, 'render_followup_openrouter_api_keys_field' ),
			$page_id,
			'humata_followup_questions_section'
		);

		add_settings_field(
			'humata_followup_openrouter_model',
			__( 'OpenRouter Model', 'humata-chatbot' ),
			array( $this->admin, 'render_followup_openrouter_model_field' ),
			$page_id,
			'humata_followup_questions_section'
		);

		add_settings_field(
			'humata_followup_max_question_length',
			__( 'Max Question Length', 'humata-chatbot' ),
			array( $this->admin, 'render_followup_max_question_length_field' ),
			$page_id,
			'humata_followup_questions_section'
		);

		add_settings_field(
			'humata_followup_topic_scope',
			__( 'Topic Scope', 'humata-chatbot' ),
			array( $this->admin, 'render_followup_topic_scope_field' ),
			$page_id,
			'humata_followup_questions_section'
		);

		add_settings_field(
			'humata_followup_custom_instructions',
			__( 'Custom Instructions', 'humata-chatbot' ),
			array( $this->admin, 'render_followup_custom_instructions_field' ),
			$page_id,
			'humata_followup_questions_section'
		);
	}
}
