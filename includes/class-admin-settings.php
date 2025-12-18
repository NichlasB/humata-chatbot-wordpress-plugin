<?php
/**
 * Admin Settings Class
 *
 * Handles the plugin settings page in WordPress admin.
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Humata_Chatbot_Admin_Settings
 *
 * @since 1.0.0
 */
class Humata_Chatbot_Admin_Settings {

    /**
     * Humata API base URL.
     *
     * @var string
     */
    const HUMATA_API_BASE = 'https://app.humata.ai/api/v1';

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'wp_ajax_humata_test_api', array( $this, 'ajax_test_api' ) );
        add_action( 'wp_ajax_humata_test_ask', array( $this, 'ajax_test_ask' ) );
        add_action( 'wp_ajax_humata_fetch_titles', array( $this, 'ajax_fetch_titles' ) );
        add_action( 'wp_ajax_humata_clear_cache', array( $this, 'ajax_clear_cache' ) );
    }

    /**
     * Add settings page to WordPress admin menu.
     *
     * @since 1.0.0
     * @return void
     */
    public function add_settings_page() {
        add_options_page(
            __( 'Humata Chatbot Settings', 'humata-chatbot' ),
            __( 'Humata Chatbot', 'humata-chatbot' ),
            'manage_options',
            'humata-chatbot',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @since 1.0.0
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_admin_assets( $hook ) {
        if ( 'settings_page_humata-chatbot' !== $hook ) {
            return;
        }

        wp_enqueue_script( 'jquery' );

        $titles_for_js = get_option( 'humata_document_titles', array() );
        if ( ! is_array( $titles_for_js ) ) {
            $titles_for_js = array();
        }
        wp_add_inline_script( 'jquery', 'window.humataDocumentTitles = ' . wp_json_encode( $titles_for_js ) . ';', 'before' );

        wp_add_inline_script( 'jquery', '
            jQuery(document).ready(function($) {
                var humataTitles = window.humataDocumentTitles || {};
                var humataTitleNotFetchedText = "' . esc_js( __( '(title not fetched)', 'humata-chatbot' ) ) . '";
                var humataDocsPerPage = 100;
                var humataDocsPage = 0;

                function humataExtractIds(text) {
                    var matches = (text || "").match(/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/ig) || [];
                    var seen = {};
                    var unique = [];
                    $.each(matches, function(_, id) {
                        id = id.toLowerCase();
                        if (!seen[id]) {
                            seen[id] = true;
                            unique.push(id);
                        }
                    });
                    return unique;
                }

                function humataGetTokenIds() {
                    return $("#humata-document-ids-tokens .humata-token").map(function() {
                        return ($(this).attr("data-id") || "").toLowerCase();
                    }).get();
                }

                function humataGetLabelForId(id) {
                    id = (id || "").toLowerCase();
                    if (humataTitles[id]) {
                        return humataTitles[id];
                    }
                    return id;
                }

                function humataSortTokens() {
                    var $container = $("#humata-document-ids-tokens");
                    var tokens = $container.children(".humata-token").get();
                    tokens.sort(function(a, b) {
                        var aId = ($(a).attr("data-id") || "").toLowerCase();
                        var bId = ($(b).attr("data-id") || "").toLowerCase();
                        var aLabel = $.trim(humataGetLabelForId(aId) || aId).toLowerCase();
                        var bLabel = $.trim(humataGetLabelForId(bId) || bId).toLowerCase();

                        if (aLabel === bLabel) {
                            return bId.localeCompare(aId);
                        }
                        return bLabel.localeCompare(aLabel);
                    });
                    $.each(tokens, function(_, el) {
                        $container.append(el);
                    });
                }

                function humataAddToken(id) {
                    id = (id || "").toLowerCase();
                    if (!id) {
                        return;
                    }

                    if ($("#humata-document-ids-tokens .humata-token[data-id=\"" + id + "\"]").length) {
                        return;
                    }

                    var $token = $("<span></span>")
                        .addClass("humata-token")
                        .attr("data-id", id)
                        .attr("title", humataGetLabelForId(id));

                    var $label = $("<span></span>")
                        .addClass("humata-token-label")
                        .text(humataGetLabelForId(id));

                    var $remove = $("<button></button>")
                        .attr("type", "button")
                        .addClass("humata-token-remove")
                        .attr("aria-label", "Remove")
                        .text("×");

                    $token.append($label).append($remove);
                    $("#humata-document-ids-tokens").append($token);
                }

                function humataSyncHiddenAndCount() {
                    var ids = humataGetTokenIds();
                    $("#humata_document_ids").val(ids.join(", "));
                    $("#humata-document-count").text(ids.length);
                }

                function humataRenderDocumentsPagination(total) {
                    var $pager = $("#humata-documents-pagination");
                    if (!$pager.length) {
                        return;
                    }

                    $pager.empty();

                    if (!total || total <= humataDocsPerPage) {
                        return;
                    }

                    var totalPages = Math.max(1, Math.ceil(total / humataDocsPerPage));
                    var currentPage = humataDocsPage + 1;
                    var start = (humataDocsPage * humataDocsPerPage) + 1;
                    var end = Math.min((humataDocsPage + 1) * humataDocsPerPage, total);

                    var $prev = $("<button></button>")
                        .attr("type", "button")
                        .addClass("button button-secondary")
                        .attr("id", "humata-documents-page-prev")
                        .text("Previous")
                        .prop("disabled", humataDocsPage <= 0);

                    var $next = $("<button></button>")
                        .attr("type", "button")
                        .addClass("button button-secondary")
                        .attr("id", "humata-documents-page-next")
                        .text("Next")
                        .prop("disabled", humataDocsPage >= totalPages - 1);

                    var $info = $("<span></span>")
                        .addClass("humata-documents-page-info")
                        .text("Showing " + start + "–" + end + " of " + total + " (Page " + currentPage + "/" + totalPages + ")");

                    $pager.append($prev).append($info).append($next);
                }

                function humataSyncDocumentsTable() {
                    var $tbody = $("#humata-documents-table-body");
                    if (!$tbody.length) {
                        return;
                    }

                    var ids = humataGetTokenIds();
                    var total = ids.length;

                    if (!total) {
                        $tbody.empty();
                        humataRenderDocumentsPagination(0);
                        return;
                    }

                    var totalPages = Math.max(1, Math.ceil(total / humataDocsPerPage));
                    if (humataDocsPage >= totalPages) {
                        humataDocsPage = totalPages - 1;
                    }
                    if (humataDocsPage < 0) {
                        humataDocsPage = 0;
                    }

                    var startIndex = humataDocsPage * humataDocsPerPage;
                    var endIndex = Math.min(startIndex + humataDocsPerPage, total);
                    var pageIds = ids.slice(startIndex, endIndex);

                    $tbody.empty();

                    $.each(pageIds, function(_, id) {
                        var title = humataTitles[id] ? humataTitles[id] : humataTitleNotFetchedText;
                        var $row = $("<tr></tr>").attr("data-doc-id", id);
                        $row.append($("<td></td>").addClass("humata-doc-title").text(title));
                        $row.append($("<td></td>").append($("<code></code>").text(id)));
                        $tbody.append($row);
                    });

                    humataRenderDocumentsPagination(total);
                }

                function humataAddTokensFromText(text) {
                    var ids = humataExtractIds(text);
                    if (!ids.length) {
                        return false;
                    }

                    $.each(ids, function(_, id) {
                        humataAddToken(id);
                    });

                    humataSortTokens();
                    humataSyncHiddenAndCount();
                    humataSyncDocumentsTable();
                    return true;
                }

                function humataInitTokensFromHidden() {
                    var ids = humataExtractIds($("#humata_document_ids").val());
                    $("#humata-document-ids-tokens").empty();
                    $.each(ids, function(_, id) {
                        humataAddToken(id);
                    });

                    humataSortTokens();
                    humataSyncHiddenAndCount();
                    humataSyncDocumentsTable();
                }

                humataInitTokensFromHidden();

                $(document).on("click", "#humata-documents-page-prev", function() {
                    if (humataDocsPage > 0) {
                        humataDocsPage--;
                        humataSyncDocumentsTable();
                    }
                });

                $(document).on("click", "#humata-documents-page-next", function() {
                    var total = humataGetTokenIds().length;
                    var totalPages = Math.max(1, Math.ceil(total / humataDocsPerPage));
                    if (humataDocsPage < totalPages - 1) {
                        humataDocsPage++;
                        humataSyncDocumentsTable();
                    }
                });

                $(document).on("click", "#humata-document-ids-control, #humata-document-ids-tokens", function(e) {
                    if ($(e.target).closest(".humata-token-remove").length) {
                        return;
                    }
                    $("#humata_document_ids_input").trigger("focus");
                });

                $(document).on("click", ".humata-token-remove", function() {
                    $(this).closest(".humata-token").remove();
                    humataSyncHiddenAndCount();
                    humataSyncDocumentsTable();
                });

                $(document).on("keydown", "#humata_document_ids_input", function(e) {
                    var key = e.key;
                    if (key === "," || key === "Enter" || key === "Tab") {
                        var val = $(this).val();
                        var added = humataAddTokensFromText(val);

                        if (key === "Enter" || key === ",") {
                            e.preventDefault();
                            if (added) {
                                $(this).val("");
                            }
                            return;
                        }

                        if (key === "Tab" && added) {
                            e.preventDefault();
                            $(this).val("");
                            return;
                        }
                    }

                    if (key === "Backspace" && $(this).val() === "") {
                        var $last = $("#humata-document-ids-tokens .humata-token").last();
                        if ($last.length) {
                            $last.remove();
                            humataSyncHiddenAndCount();
                            humataSyncDocumentsTable();
                            e.preventDefault();
                        }
                    }
                });

                $(document).on("paste", "#humata_document_ids_input", function() {
                    var $input = $(this);
                    setTimeout(function() {
                        var added = humataAddTokensFromText($input.val());
                        if (added) {
                            $input.val("");
                        }
                    }, 0);
                });

                $(document).on("blur", "#humata_document_ids_input", function() {
                    var val = $(this).val();
                    var added = humataAddTokensFromText(val);
                    if (added) {
                        $(this).val("");
                    }
                });

                $("#humata-test-api").on("click", function() {
                    var $btn = $(this);
                    var $result = $("#humata-test-result");
                    $btn.prop("disabled", true).text("Testing...");
                    $result.html("");
                    $.post(ajaxurl, {
                        action: "humata_test_api",
                        nonce: "' . wp_create_nonce( 'humata_admin_nonce' ) . '"
                    }, function(response) {
                        $btn.prop("disabled", false).text("Test API Connection");
                        if (response.success) {
                            $result.html("<span style=\"color:green;\">✓ " + response.data.message + "</span>");
                        } else {
                            $result.html("<span style=\"color:red;\">✗ " + response.data.message + "</span>");
                        }
                    }).fail(function() {
                        $btn.prop("disabled", false).text("Test API Connection");
                        $result.html("<span style=\"color:red;\">✗ Request failed</span>");
                    });
                });
                $("#humata-clear-cache").on("click", function() {
                    var $btn = $(this);
                    $btn.prop("disabled", true).text("Clearing...");
                    $.post(ajaxurl, {
                        action: "humata_clear_cache",
                        nonce: "' . wp_create_nonce( 'humata_admin_nonce' ) . '"
                    }, function(response) {
                        $btn.prop("disabled", false).text("Clear Cache");
                        if (response.success) {
                            alert("Cache cleared!");
                            location.reload();
                        } else {
                            alert("Failed to clear cache.");
                        }
                    });
                });
                $("#humata-test-ask").on("click", function() {
                    var $btn = $(this);
                    var $result = $("#humata-test-result");
                    $btn.prop("disabled", true).text("Testing Ask...");
                    $result.html("");
                    $.post(ajaxurl, {
                        action: "humata_test_ask",
                        nonce: "' . wp_create_nonce( 'humata_admin_nonce' ) . '"
                    }, function(response) {
                        $btn.prop("disabled", false).text("Test Ask");
                        if (response.success) {
                            $result.html("<span style=\"color:green;\">✓ " + response.data.message + "</span>");
                        } else {
                            $result.html("<span style=\"color:red;\">✗ " + response.data.message + "</span>");
                        }
                    }).fail(function() {
                        $btn.prop("disabled", false).text("Test Ask");
                        $result.html("<span style=\"color:red;\">✗ Request failed</span>");
                    });
                });

                $("#humata-fetch-titles").on("click", function() {
                    var $btn = $(this);
                    var $result = $("#humata-test-result");
                    var offset = 0;
                    var limit = 5;
                    var delayMs = 2000;
                    var documentIds = $("#humata_document_ids").val();

                    $btn.prop("disabled", true).text("Fetching...");
                    $result.html("");

                    function fetchBatch() {
                        $.post(ajaxurl, {
                            action: "humata_fetch_titles",
                            nonce: "' . wp_create_nonce( 'humata_admin_nonce' ) . '",
                            offset: offset,
                            limit: limit,
                            document_ids: documentIds
                        }, function(response) {
                            if (!response.success) {
                                $btn.prop("disabled", false).text("Fetch Titles");
                                var msg = response.data && response.data.message ? response.data.message : "Request failed";
                                if (response.data && response.data.errorMessage) {
                                    msg = msg + " (" + response.data.errorMessage + ")";
                                }
                                $result.html("<span style=\"color:red;\">✗ " + msg + "</span>");
                                return;
                            }

                            if (response.data && response.data.titles) {
                                $.each(response.data.titles, function(docId, title) {
                                    docId = (docId || "").toLowerCase();
                                    if (!docId || !title) {
                                        return;
                                    }

                                    humataTitles[docId] = title;

                                    var $token = $("#humata-document-ids-tokens .humata-token[data-id=\"" + docId + "\"]");
                                    $token.find(".humata-token-label").text(title);
                                    $token.attr("title", title);

                                    $("#humata-documents-table-body tr[data-doc-id=\"" + docId + "\"] .humata-doc-title").text(title);
                                });
                            }

                            humataSortTokens();
                            humataSyncHiddenAndCount();
                            humataSyncDocumentsTable();

                            offset = response.data.nextOffset;
                            var successMsg = response.data.message || "";
                            if (response.data.errors && response.data.errorMessage) {
                                successMsg = successMsg + " (" + response.data.errorMessage + ")";
                            }
                            $result.html("<span style=\"color:green;\">✓ " + successMsg + "</span>");

                            if (response.data.done) {
                                $btn.prop("disabled", false).text("Fetch Titles");
                                return;
                            }

                            setTimeout(fetchBatch, delayMs);
                        }).fail(function() {
                            $btn.prop("disabled", false).text("Fetch Titles");
                            $result.html("<span style=\"color:red;\">✗ Request failed</span>");
                        });
                    }

                    fetchBatch();
                });
            });
        ' );

        wp_add_inline_script( 'jquery', '
            jQuery(function($) {
                function humataGetSecondLlmProvider() {
                    var $selected = $("input[name=\"humata_second_llm_provider\"]:checked");
                    var val = $selected.length ? String($selected.val() || "") : "";
                    val = $.trim(val);
                    if (!val) {
                        val = "none";
                    }
                    return val;
                }

                function humataToggleSecondLlmFields() {
                    var provider = humataGetSecondLlmProvider();
                    var showStraico = provider === "straico";
                    var showAnthropic = provider === "anthropic";
                    var showAny = provider !== "none";

                    $("#humata_straico_api_key").closest("tr").toggle(showStraico);
                    $("#humata_straico_model").closest("tr").toggle(showStraico);

                    $("#humata_anthropic_api_key").closest("tr").toggle(showAnthropic);
                    $("#humata_anthropic_model").closest("tr").toggle(showAnthropic);
                    $("#humata_anthropic_extended_thinking").closest("tr").toggle(showAnthropic);

                    $("#humata_straico_system_prompt").closest("tr").toggle(showAny);
                }

                $(document).on("change", "input[name=\"humata_second_llm_provider\"]", humataToggleSecondLlmFields);
                humataToggleSecondLlmFields();
            });
        ' );

        wp_add_inline_style( 'wp-admin', '
            .humata-settings-wrap .form-table th {
                width: 250px;
            }
            .humata-settings-wrap .slug-input-wrapper {
                display: flex;
                align-items: center;
                gap: 5px;
            }
            .humata-settings-wrap .slug-prefix {
                color: #666;
            }
            .humata-settings-wrap .description {
                margin-top: 8px;
            }
            .humata-settings-wrap .radio-group label {
                display: block;
                margin-bottom: 8px;
            }
            .humata-settings-wrap .conditional-field {
                margin-left: 25px;
                margin-top: 5px;
            }
            .humata-api-actions {
                margin-top: 15px;
                display: flex;
                gap: 10px;
                align-items: center;
            }
            .humata-documents-details {
                margin-top: 10px;
            }
            .humata-documents-pagination {
                margin-top: 10px;
                display: flex;
                align-items: center;
                gap: 8px;
                max-width: 900px;
            }
            .humata-documents-page-info {
                color: #646970;
            }
            .humata-documents-table-wrapper {
                max-width: 900px;
                max-height: 360px;
                overflow: auto;
            }
            .humata-documents-table-wrapper thead th {
                position: sticky;
                top: 0;
                z-index: 1;
                background: #fff;
            }
            #humata-test-result {
                margin-left: 10px;
            }
            .humata-document-ids-control {
                border: 1px solid #8c8f94;
                border-radius: 4px;
                padding: 6px;
                max-width: 900px;
                background: #fff;
                display: flex;
                align-items: flex-start;
                flex-wrap: wrap;
                gap: 6px;
                min-height: 38px;
                box-sizing: border-box;
            }
            .humata-document-ids-control:focus-within {
                border-color: #2271b1;
                box-shadow: 0 0 0 1px #2271b1;
            }
            .humata-document-ids-tokens {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
                flex: 1 1 auto;
                max-height: 180px;
                overflow-y: auto;
                align-content: flex-start;
            }
            #humata_document_ids_input {
                flex: 1 1 220px;
                min-width: 220px;
                border: none;
                align-self: flex-end;
                box-shadow: none;
                padding: 2px 4px;
                margin: 0;
            }
            #humata_document_ids_input:focus {
                outline: none;
                box-shadow: none;
            }
            .humata-token {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 2px 8px;
                border: 1px solid #c3c4c7;
                border-radius: 14px;
                background: #f6f7f7;
                font-size: 12px;
                line-height: 20px;
                max-width: 100%;
                box-sizing: border-box;
            }
            .humata-token-label {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                max-width: 260px;
            }
            .humata-token-remove {
                border: none;
                background: transparent;
                cursor: pointer;
                padding: 0;
                margin: 0;
                color: #646970;
                line-height: 20px;
            }
            .humata-token-remove:hover {
                color: #d63638;
            }
        ' );
    }

    /**
     * Register plugin settings.
     *
     * @since 1.0.0
     * @return void
     */
    public function register_settings() {
        // Register settings
        register_setting(
            'humata_chatbot_settings',
            'humata_api_key',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_document_ids',
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_document_ids' ),
                'default'           => '',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_chat_location',
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_location' ),
                'default'           => 'dedicated',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_chat_page_slug',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_title',
                'default'           => 'chat',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_chat_theme',
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_theme' ),
                'default'           => 'auto',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_system_prompt',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
                'default'           => '',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_rate_limit',
            array(
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'default'           => 50,
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_max_prompt_chars',
            array(
                'type'              => 'integer',
                'sanitize_callback' => array( $this, 'sanitize_max_prompt_chars' ),
                'default'           => 3000,
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_second_llm_provider',
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_second_llm_provider' ),
                'default'           => 'none',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_straico_review_enabled',
            array(
                'type'              => 'integer',
                'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
                'default'           => 0,
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_straico_api_key',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_straico_model',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_straico_system_prompt',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
                'default'           => '',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_anthropic_api_key',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_anthropic_model',
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_anthropic_model' ),
                'default'           => 'claude-3-5-sonnet-20241022',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_anthropic_extended_thinking',
            array(
                'type'              => 'integer',
                'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
                'default'           => 0,
            )
        );

        // API Settings Section
        add_settings_section(
            'humata_api_section',
            __( 'API Configuration', 'humata-chatbot' ),
            array( $this, 'render_api_section' ),
            'humata-chatbot'
        );

        add_settings_field(
            'humata_api_key',
            __( 'API Key', 'humata-chatbot' ),
            array( $this, 'render_api_key_field' ),
            'humata-chatbot',
            'humata_api_section'
        );

        add_settings_field(
            'humata_document_ids',
            __( 'Document IDs', 'humata-chatbot' ),
            array( $this, 'render_document_ids_field' ),
            'humata-chatbot',
            'humata_api_section'
        );

        add_settings_section(
            'humata_prompt_section',
            __( 'System Prompt', 'humata-chatbot' ),
            array( $this, 'render_prompt_section' ),
            'humata-chatbot'
        );

        add_settings_field(
            'humata_system_prompt',
            __( 'System Prompt', 'humata-chatbot' ),
            array( $this, 'render_system_prompt_field' ),
            'humata-chatbot',
            'humata_prompt_section'
        );

        // Second LLM Processing Settings Section
        add_settings_section(
            'humata_straico_section',
            __( 'Second LLM Processing', 'humata-chatbot' ),
            array( $this, 'render_straico_section' ),
            'humata-chatbot'
        );

        add_settings_field(
            'humata_second_llm_provider',
            __( 'Second-stage Provider', 'humata-chatbot' ),
            array( $this, 'render_second_llm_provider_field' ),
            'humata-chatbot',
            'humata_straico_section'
        );

        add_settings_field(
            'humata_straico_api_key',
            __( 'Straico API Key', 'humata-chatbot' ),
            array( $this, 'render_straico_api_key_field' ),
            'humata-chatbot',
            'humata_straico_section'
        );

        add_settings_field(
            'humata_straico_model',
            __( 'Straico Model', 'humata-chatbot' ),
            array( $this, 'render_straico_model_field' ),
            'humata-chatbot',
            'humata_straico_section'
        );

        add_settings_field(
            'humata_anthropic_api_key',
            __( 'Anthropic API Key', 'humata-chatbot' ),
            array( $this, 'render_anthropic_api_key_field' ),
            'humata-chatbot',
            'humata_straico_section'
        );

        add_settings_field(
            'humata_anthropic_model',
            __( 'Claude Model Selection', 'humata-chatbot' ),
            array( $this, 'render_anthropic_model_field' ),
            'humata-chatbot',
            'humata_straico_section'
        );

        add_settings_field(
            'humata_anthropic_extended_thinking',
            __( 'Extended Thinking', 'humata-chatbot' ),
            array( $this, 'render_anthropic_extended_thinking_field' ),
            'humata-chatbot',
            'humata_straico_section'
        );

        add_settings_field(
            'humata_straico_system_prompt',
            __( 'System Prompt', 'humata-chatbot' ),
            array( $this, 'render_straico_system_prompt_field' ),
            'humata-chatbot',
            'humata_straico_section'
        );

        // Display Settings Section
        add_settings_section(
            'humata_display_section',
            __( 'Display Settings', 'humata-chatbot' ),
            array( $this, 'render_display_section' ),
            'humata-chatbot'
        );

        add_settings_field(
            'humata_chat_location',
            __( 'Display Location', 'humata-chatbot' ),
            array( $this, 'render_location_field' ),
            'humata-chatbot',
            'humata_display_section'
        );

        add_settings_field(
            'humata_chat_theme',
            __( 'Interface Theme', 'humata-chatbot' ),
            array( $this, 'render_theme_field' ),
            'humata-chatbot',
            'humata_display_section'
        );

        // Security Settings Section
        add_settings_section(
            'humata_security_section',
            __( 'Security Settings', 'humata-chatbot' ),
            array( $this, 'render_security_section' ),
            'humata-chatbot'
        );

        add_settings_field(
            'humata_max_prompt_chars',
            __( 'Max Prompt Characters', 'humata-chatbot' ),
            array( $this, 'render_max_prompt_chars_field' ),
            'humata-chatbot',
            'humata_security_section'
        );

        add_settings_field(
            'humata_rate_limit',
            __( 'Rate Limit', 'humata-chatbot' ),
            array( $this, 'render_rate_limit_field' ),
            'humata-chatbot',
            'humata_security_section'
        );
    }

    /**
     * Sanitize location option.
     *
     * @since 1.0.0
     * @param string $value Input value.
     * @return string Sanitized value.
     */
    public function sanitize_location( $value ) {
        $valid = array( 'homepage', 'dedicated', 'shortcode' );
        return in_array( $value, $valid, true ) ? $value : 'dedicated';
    }

    /**
     * Sanitize theme option.
     *
     * @since 1.0.0
     * @param string $value Input value.
     * @return string Sanitized value.
     */
    public function sanitize_theme( $value ) {
        $valid = array( 'dark', 'light', 'auto' );
        return in_array( $value, $valid, true ) ? $value : 'auto';
    }

    /**
     * Sanitize a checkbox value to 0/1.
     *
     * @since 1.0.0
     * @param mixed $value Input value.
     * @return int 0 or 1.
     */
    public function sanitize_checkbox( $value ) {
        return empty( $value ) ? 0 : 1;
    }

    public function sanitize_max_prompt_chars( $value ) {
        $value = absint( $value );

        if ( $value <= 0 ) {
            return 3000;
        }

        if ( $value > 100000 ) {
            return 100000;
        }

        return $value;
    }

    /**
     * Sanitize second-stage LLM provider selection.
     *
     * @since 1.0.0
     * @param string $value Input value.
     * @return string Sanitized provider value.
     */
    public function sanitize_second_llm_provider( $value ) {
        $value = sanitize_text_field( (string) $value );
        $valid = array( 'none', 'straico', 'anthropic' );
        return in_array( $value, $valid, true ) ? $value : 'none';
    }

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
        ?>
        <div class="wrap humata-settings-wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <?php settings_errors( 'humata_chatbot_messages' ); ?>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'humata_chatbot_settings' );
                do_settings_sections( 'humata-chatbot' );
                submit_button( __( 'Save Settings', 'humata-chatbot' ) );
                ?>
            </form>

            <hr>
            <h2><?php esc_html_e( 'Usage Information', 'humata-chatbot' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Shortcode', 'humata-chatbot' ); ?></th>
                    <td>
                        <code>[humata_chat]</code>
                        <p class="description">
                            <?php esc_html_e( 'Use this shortcode to embed the chat interface on any page or post.', 'humata-chatbot' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Chat Page URL', 'humata-chatbot' ); ?></th>
                    <td>
                        <?php
                        $location = get_option( 'humata_chat_location', 'dedicated' );
                        if ( 'dedicated' === $location ) {
                            $slug = get_option( 'humata_chat_page_slug', 'chat' );
                            $url  = home_url( '/' . $slug . '/' );
                            echo '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $url ) . '</a>';
                        } elseif ( 'homepage' === $location ) {
                            echo '<a href="' . esc_url( home_url( '/' ) ) . '" target="_blank">' . esc_html( home_url( '/' ) ) . '</a>';
                            echo '<p class="description">' . esc_html__( 'Chat is displayed on the homepage.', 'humata-chatbot' ) . '</p>';
                        } else {
                            echo '<em>' . esc_html__( 'Use the shortcode to display the chat.', 'humata-chatbot' ) . '</em>';
                        }
                        ?>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Render API section description.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_api_section() {
        echo '<p>' . esc_html__( 'Enter your Humata API credentials. You can find these in your Humata account settings.', 'humata-chatbot' ) . '</p>';
    }

    public function render_prompt_section() {
        echo '<p>' . esc_html__( 'Optional instructions that will be prepended to every user message sent to Humata.', 'humata-chatbot' ) . '</p>';
    }

    /**
     * Render Straico section description.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_straico_section() {
        echo '<p>' . esc_html__( 'Optionally send Humata responses to a second LLM provider for a second-pass review before returning them to users.', 'humata-chatbot' ) . '</p>';
    }

    /**
     * Render display section description.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_display_section() {
        echo '<p>' . esc_html__( 'Configure how and where the chat interface is displayed.', 'humata-chatbot' ) . '</p>';
    }

    /**
     * Render security section description.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_security_section() {
        echo '<p>' . esc_html__( 'Configure security settings to protect against abuse.', 'humata-chatbot' ) . '</p>';
    }

    /**
     * Render API key field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_api_key_field() {
        $value = get_option( 'humata_api_key', '' );
        ?>
        <input
            type="password"
            id="humata_api_key"
            name="humata_api_key"
            value="<?php echo esc_attr( $value ); ?>"
            class="regular-text"
            autocomplete="off"
        />
        <p class="description">
            <?php esc_html_e( 'Your Humata API key. This will be stored securely and never exposed to the frontend.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

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

    /**
     * Render document IDs field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_document_ids_field() {
        $value = get_option( 'humata_document_ids', '' );
        $doc_ids_array = $this->parse_document_ids( $value );
        $titles = get_option( 'humata_document_titles', array() );
        if ( ! is_array( $titles ) ) {
            $titles = array();
        }
        $titles_cached = 0;
        foreach ( $doc_ids_array as $doc_id ) {
            if ( isset( $titles[ $doc_id ] ) && '' !== $titles[ $doc_id ] ) {
                $titles_cached++;
            }
        }

        if ( count( $doc_ids_array ) > 1 ) {
            usort(
                $doc_ids_array,
                function( $a, $b ) use ( $titles ) {
                    $label_a = ( isset( $titles[ $a ] ) && '' !== $titles[ $a ] ) ? $titles[ $a ] : $a;
                    $label_b = ( isset( $titles[ $b ] ) && '' !== $titles[ $b ] ) ? $titles[ $b ] : $b;
                    $cmp     = strcasecmp( $label_a, $label_b );
                    if ( 0 !== $cmp ) {
                        return -$cmp;
                    }
                    return -strcasecmp( $a, $b );
                }
            );
        }
        ?>
        <div id="humata-document-ids-control" class="humata-document-ids-control">
            <div id="humata-document-ids-tokens" class="humata-document-ids-tokens"></div>
            <input
                type="text"
                id="humata_document_ids_input"
                class="regular-text"
                placeholder="<?php esc_attr_e( 'e.g., 63ea1432-d6aa-49d4-81ef-07cb1692f2ee, cbec39d9-1e01-409c-9fc2-f19f5d01005c', 'humata-chatbot' ); ?>"
                autocomplete="off"
            />
        </div>
        <input
            type="hidden"
            id="humata_document_ids"
            name="humata_document_ids"
            value="<?php echo esc_attr( $value ); ?>"
        />
        <p class="description">
            <strong><?php esc_html_e( 'Document count:', 'humata-chatbot' ); ?></strong>
            <span id="humata-document-count"><?php echo esc_html( (string) count( $doc_ids_array ) ); ?></span>
            <?php if ( $titles_cached > 0 ) : ?>
                <span><?php echo esc_html( sprintf( __( '(%d titles cached)', 'humata-chatbot' ), $titles_cached ) ); ?></span>
            <?php endif; ?>
        </p>
        <?php if ( ! empty( $doc_ids_array ) ) : ?>
            <details class="humata-documents-details">
                <summary><?php esc_html_e( 'Show documents', 'humata-chatbot' ); ?></summary>
                <div id="humata-documents-pagination" class="humata-documents-pagination"></div>
                <div class="humata-documents-table-wrapper">
                    <table class="widefat striped" style="margin-top:10px; max-width: 900px;">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Title', 'humata-chatbot' ); ?></th>
                                <th><?php esc_html_e( 'Document ID', 'humata-chatbot' ); ?></th>
                            </tr>
                        </thead>
                        <tbody id="humata-documents-table-body">
                            <?php foreach ( array_slice( $doc_ids_array, 0, 100 ) as $doc_id ) : ?>
                                <?php $title = isset( $titles[ $doc_id ] ) ? $titles[ $doc_id ] : ''; ?>
                                <tr data-doc-id="<?php echo esc_attr( $doc_id ); ?>">
                                    <td class="humata-doc-title"><?php echo esc_html( $title ? $title : __( '(title not fetched)', 'humata-chatbot' ) ); ?></td>
                                    <td><code><?php echo esc_html( $doc_id ); ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        <?php endif; ?>
        <p class="description">
            <?php esc_html_e( 'Enter Humata document IDs separated by commas. Each ID must be a UUID (e.g., 63ea1432-d6aa-49d4-81ef-07cb1692f2ee).', 'humata-chatbot' ); ?>
        </p>
        <p class="description">
            <strong><?php esc_html_e( 'How to find document IDs:', 'humata-chatbot' ); ?></strong><br>
            <?php esc_html_e( '1. Open your Humata dashboard and click on a document', 'humata-chatbot' ); ?><br>
            <?php esc_html_e( '2. Look at the URL - the document ID is the UUID after /pdf/ (e.g., app.humata.ai/pdf/YOUR-DOC-ID-HERE)', 'humata-chatbot' ); ?><br>
            <?php esc_html_e( '3. Or use browser DevTools (F12) → Network tab to see document IDs in API requests', 'humata-chatbot' ); ?>
        </p>
        <div class="humata-api-actions">
            <button type="button" id="humata-test-api" class="button button-secondary">
                <?php esc_html_e( 'Test Connection', 'humata-chatbot' ); ?>
            </button>
            <button type="button" id="humata-test-ask" class="button button-secondary">
                <?php esc_html_e( 'Test Ask', 'humata-chatbot' ); ?>
            </button>
            <button type="button" id="humata-fetch-titles" class="button button-secondary">
                <?php esc_html_e( 'Fetch Titles', 'humata-chatbot' ); ?>
            </button>
            <button type="button" id="humata-clear-cache" class="button button-secondary">
                <?php esc_html_e( 'Clear Cache', 'humata-chatbot' ); ?>
            </button>
            <span id="humata-test-result"></span>
        </div>
        <?php
    }

    public function render_system_prompt_field() {
        $value = get_option( 'humata_system_prompt', '' );
        ?>
        <textarea
            id="humata_system_prompt"
            name="humata_system_prompt"
            rows="4"
            class="large-text"
        ><?php echo esc_textarea( $value ); ?></textarea>
        <p class="description">
            <?php esc_html_e( 'Leave blank to disable. If set, this text is prepended to every user message before sending it to Humata.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    public function render_second_llm_provider_field() {
        $provider = get_option( 'humata_second_llm_provider', '' );
        if ( ! is_string( $provider ) ) {
            $provider = '';
        }
        $provider = trim( $provider );

        // Back-compat: if new provider option is unset, reflect the legacy Straico enabled flag.
        if ( '' === $provider ) {
            $legacy_enabled = (int) get_option( 'humata_straico_review_enabled', 0 );
            $provider       = ( 1 === $legacy_enabled ) ? 'straico' : 'none';
        }

        $provider = $this->sanitize_second_llm_provider( $provider );
        ?>
        <fieldset class="radio-group">
            <label>
                <input
                    type="radio"
                    id="humata_second_llm_provider_none"
                    name="humata_second_llm_provider"
                    value="none"
                    <?php checked( $provider, 'none' ); ?>
                />
                <?php esc_html_e( 'None (skip second processing)', 'humata-chatbot' ); ?>
            </label>

            <label>
                <input
                    type="radio"
                    id="humata_second_llm_provider_straico"
                    name="humata_second_llm_provider"
                    value="straico"
                    <?php checked( $provider, 'straico' ); ?>
                />
                <?php esc_html_e( 'Straico', 'humata-chatbot' ); ?>
            </label>

            <label>
                <input
                    type="radio"
                    id="humata_second_llm_provider_anthropic"
                    name="humata_second_llm_provider"
                    value="anthropic"
                    <?php checked( $provider, 'anthropic' ); ?>
                />
                <?php esc_html_e( 'Anthropic Claude', 'humata-chatbot' ); ?>
            </label>
        </fieldset>
        <p class="description">
            <?php esc_html_e( 'When enabled, responses may take longer because the plugin waits for both Humata and the selected provider.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    /**
     * Render Straico enabled field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_straico_enabled_field() {
        $enabled = (int) get_option( 'humata_straico_review_enabled', 0 );
        ?>
        <label>
            <input
                type="checkbox"
                id="humata_straico_review_enabled"
                name="humata_straico_review_enabled"
                value="1"
                <?php checked( $enabled, 1 ); ?>
            />
            <?php esc_html_e( 'Send Humata answers to Straico for a second-pass review before returning them to users.', 'humata-chatbot' ); ?>
        </label>
        <p class="description">
            <?php esc_html_e( 'When enabled, responses may take longer because the plugin waits for both Humata and Straico.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    /**
     * Render Straico API key field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_straico_api_key_field() {
        $value = get_option( 'humata_straico_api_key', '' );
        ?>
        <input
            type="password"
            id="humata_straico_api_key"
            name="humata_straico_api_key"
            value="<?php echo esc_attr( $value ); ?>"
            class="regular-text"
            autocomplete="off"
        />
        <p class="description">
            <?php esc_html_e( 'Your Straico API key. This is stored server-side and never exposed to the frontend.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    /**
     * Render Straico model field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_straico_model_field() {
        $value = get_option( 'humata_straico_model', '' );
        ?>
        <input
            type="text"
            id="humata_straico_model"
            name="humata_straico_model"
            value="<?php echo esc_attr( $value ); ?>"
            class="regular-text"
            placeholder="<?php esc_attr_e( 'e.g., anthropic/claude-sonnet-4.5', 'humata-chatbot' ); ?>"
            autocomplete="off"
        />
        <p class="description">
            <?php esc_html_e( 'Enter the Straico model identifier to use for the review step.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    public function render_anthropic_api_key_field() {
        $value = get_option( 'humata_anthropic_api_key', '' );
        ?>
        <input
            type="password"
            id="humata_anthropic_api_key"
            name="humata_anthropic_api_key"
            value="<?php echo esc_attr( $value ); ?>"
            class="regular-text"
            autocomplete="off"
        />
        <p class="description">
            <?php esc_html_e( 'Your Anthropic API key. This is stored server-side and never exposed to the frontend.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    public function render_anthropic_model_field() {
        $value = get_option( 'humata_anthropic_model', '' );
        if ( ! is_string( $value ) ) {
            $value = '';
        }

        $models = $this->get_anthropic_model_options();
        if ( '' === $value || ! isset( $models[ $value ] ) ) {
            $keys  = array_keys( $models );
            $value = isset( $keys[0] ) ? $keys[0] : '';
        }
        ?>
        <select id="humata_anthropic_model" name="humata_anthropic_model" class="regular-text">
            <?php foreach ( $models as $model_id => $label ) : ?>
                <option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $value, $model_id ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e( 'Select the Claude model to use for the second-stage review step.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    public function render_anthropic_extended_thinking_field() {
        $enabled = (int) get_option( 'humata_anthropic_extended_thinking', 0 );
        ?>
        <label>
            <input
                type="checkbox"
                id="humata_anthropic_extended_thinking"
                name="humata_anthropic_extended_thinking"
                value="1"
                <?php checked( $enabled, 1 ); ?>
            />
            <?php esc_html_e( 'Enable Extended Thinking Mode', 'humata-chatbot' ); ?>
        </label>
        <p class="description">
            <?php esc_html_e( 'Uses more tokens but significantly improves instruction adherence.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    /**
     * Render Straico system prompt field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_straico_system_prompt_field() {
        $value = get_option( 'humata_straico_system_prompt', '' );
        ?>
        <textarea
            id="humata_straico_system_prompt"
            name="humata_straico_system_prompt"
            rows="6"
            class="large-text"
        ><?php echo esc_textarea( $value ); ?></textarea>
        <p class="description">
            <?php esc_html_e( 'Optional instructions for the second-stage model. This prompt is sent as the system message alongside the Humata answer.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

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

    /**
     * Render rate limit field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_rate_limit_field() {
        $value = get_option( 'humata_rate_limit', 50 );
        ?>
        <input
            type="number"
            id="humata_rate_limit"
            name="humata_rate_limit"
            value="<?php echo esc_attr( $value ); ?>"
            min="1"
            max="1000"
            class="small-text"
        />
        <span><?php esc_html_e( 'requests per hour per IP address', 'humata-chatbot' ); ?></span>
        <p class="description">
            <?php esc_html_e( 'Limit the number of API requests to prevent abuse. Default is 50.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    public function ajax_fetch_titles() {
        if ( ! check_ajax_referer( 'humata_admin_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'humata-chatbot' ) ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'humata-chatbot' ) ) );
        }

        $api_key = get_option( 'humata_api_key', '' );
        $document_ids = isset( $_POST['document_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['document_ids'] ) ) : get_option( 'humata_document_ids', '' );

        if ( empty( $api_key ) ) {
            wp_send_json_error( array( 'message' => __( 'API key not configured.', 'humata-chatbot' ) ) );
        }

        if ( empty( $document_ids ) ) {
            wp_send_json_error( array( 'message' => __( 'Document IDs not configured.', 'humata-chatbot' ) ) );
        }

        $doc_ids_array = $this->parse_document_ids( $document_ids );
        $total         = count( $doc_ids_array );

        if ( 0 === $total ) {
            wp_send_json_error( array( 'message' => __( 'No valid document IDs found.', 'humata-chatbot' ) ) );
        }

        $offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
        $limit  = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 20;
        $limit  = max( 1, min( 50, $limit ) );

        $batch = array_slice( $doc_ids_array, $offset, $limit );

        $titles = get_option( 'humata_document_titles', array() );
        if ( ! is_array( $titles ) ) {
            $titles = array();
        }

        $fetched = 0;
        $errors  = 0;
        $updated_titles = array();
        $first_error_message = '';

        foreach ( $batch as $doc_id ) {
            if ( isset( $titles[ $doc_id ] ) && '' !== $titles[ $doc_id ] ) {
                continue;
            }

            $response = wp_remote_get(
                self::HUMATA_API_BASE . '/pdf/' . $doc_id,
                array(
                    'timeout' => 30,
                    'httpversion' => '1.1',
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $api_key,
                        'Accept'        => '*/*',
                    ),
                )
            );

            if ( is_wp_error( $response ) ) {
                if ( '' === $first_error_message ) {
                    $first_error_message = $response->get_error_message();
                }
                $errors++;
                continue;
            }

            $code = wp_remote_retrieve_response_code( $response );
            if ( 401 === $code ) {
                wp_send_json_error( array( 'message' => __( 'Invalid API key.', 'humata-chatbot' ) ) );
            }

            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );

            $pdf_title = '';
            if ( is_array( $data ) ) {
                if ( ! empty( $data['name'] ) ) {
                    $pdf_title = $data['name'];
                } elseif ( isset( $data['pdf'] ) && is_array( $data['pdf'] ) && ! empty( $data['pdf']['name'] ) ) {
                    $pdf_title = $data['pdf']['name'];
                }
            }

            if ( 200 !== $code || ! is_array( $data ) || '' === $pdf_title ) {
                if ( '' === $first_error_message ) {
                    $first_error_message = 'HTTP ' . (int) $code;
                    if ( is_string( $body ) && '' !== $body ) {
                        $body = wp_strip_all_tags( $body );
                        $first_error_message .= ': ' . substr( $body, 0, 200 );
                    }
                }
                $errors++;
                continue;
            }

            $titles[ $doc_id ] = sanitize_text_field( $pdf_title );
            $updated_titles[ $doc_id ] = $titles[ $doc_id ];
            $fetched++;
        }

        update_option( 'humata_document_titles', $titles );

        $next_offset = $offset + $limit;
        $done        = $next_offset >= $total;
        $processed   = min( $next_offset, $total );

        wp_send_json_success(
            array(
                'total'      => $total,
                'nextOffset' => $done ? $total : $next_offset,
                'done'       => $done,
                'fetched'    => $fetched,
                'errors'     => $errors,
                'titles'     => $updated_titles,
                'errorMessage' => $first_error_message,
                'message'    => sprintf( __( 'Titles updated. Processed %1$d/%2$d. Fetched %3$d, errors %4$d.', 'humata-chatbot' ), $processed, $total, $fetched, $errors ),
            )
        );
    }

    /**
     * AJAX handler for testing API connection.
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_test_api() {
        if ( ! check_ajax_referer( 'humata_admin_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'humata-chatbot' ) ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'humata-chatbot' ) ) );
        }

        $api_key      = get_option( 'humata_api_key', '' );
        $document_ids = get_option( 'humata_document_ids', '' );

        if ( empty( $api_key ) ) {
            wp_send_json_error( array( 'message' => __( 'API key not configured.', 'humata-chatbot' ) ) );
        }

        if ( empty( $document_ids ) ) {
            wp_send_json_error( array( 'message' => __( 'Document IDs not configured.', 'humata-chatbot' ) ) );
        }

        $doc_ids_array = $this->parse_document_ids( $document_ids );
        if ( empty( $doc_ids_array ) ) {
            wp_send_json_error( array( 'message' => __( 'No valid document IDs found.', 'humata-chatbot' ) ) );
        }

        // Try to create a conversation
        $response = wp_remote_post(
            self::HUMATA_API_BASE . '/conversations',
            array(
                'timeout'     => 30,
                'httpversion' => '1.1',
                'headers'     => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ),
                'body'        => wp_json_encode( array(
                    'documentIds' => $doc_ids_array,
                ) ),
            )
        );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => __( 'Connection failed: ', 'humata-chatbot' ) . $response->get_error_message() ) );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $data          = json_decode( $response_body, true );

        if ( 401 === $response_code ) {
            wp_send_json_error( array( 'message' => __( 'Invalid API key.', 'humata-chatbot' ) ) );
        }

        if ( $response_code >= 400 ) {
            $error_msg = isset( $data['message'] ) ? $data['message'] : sprintf( __( 'API error (HTTP %d)', 'humata-chatbot' ), $response_code );
            wp_send_json_error( array( 'message' => $error_msg ) );
        }

        if ( isset( $data['id'] ) ) {
            // Cache the conversation ID
            $cache_key = 'humata_conversation_' . md5( implode( ',', $doc_ids_array ) );
            set_transient( $cache_key, $data['id'], DAY_IN_SECONDS );
            
            wp_send_json_success( array( 
                'message' => sprintf( 
                    __( 'Success! Conversation created (ID: %s)', 'humata-chatbot' ),
                    substr( $data['id'], 0, 8 ) . '...'
                ) 
            ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Unexpected response from API.', 'humata-chatbot' ) ) );
        }
    }

    /**
     * AJAX handler for testing ask functionality.
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_test_ask() {
        if ( ! check_ajax_referer( 'humata_admin_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'humata-chatbot' ) ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'humata-chatbot' ) ) );
        }

        $api_key      = get_option( 'humata_api_key', '' );
        $document_ids = get_option( 'humata_document_ids', '' );

        if ( empty( $api_key ) || empty( $document_ids ) ) {
            wp_send_json_error( array( 'message' => __( 'API key or document IDs not configured.', 'humata-chatbot' ) ) );
        }

        $doc_ids_array = $this->parse_document_ids( $document_ids );
        if ( empty( $doc_ids_array ) ) {
            wp_send_json_error( array( 'message' => __( 'No valid document IDs found.', 'humata-chatbot' ) ) );
        }

        // Step 1: Create conversation
        $conv_response = wp_remote_post(
            self::HUMATA_API_BASE . '/conversations',
            array(
                'timeout'     => 30,
                'headers'     => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ),
                'body'        => wp_json_encode( array( 'documentIds' => $doc_ids_array ) ),
            )
        );

        if ( is_wp_error( $conv_response ) ) {
            wp_send_json_error( array( 'message' => 'Connection error: ' . $conv_response->get_error_message() ) );
        }

        $conv_code = wp_remote_retrieve_response_code( $conv_response );
        $conv_body = wp_remote_retrieve_body( $conv_response );
        $conv_data = json_decode( $conv_body, true );

        if ( $conv_code >= 400 || ! isset( $conv_data['id'] ) ) {
            wp_send_json_error( array( 'message' => 'Conversation failed: ' . $conv_body ) );
        }

        $conversation_id = $conv_data['id'];

        // Step 2: Ask a test question
        $ask_response = wp_remote_post(
            self::HUMATA_API_BASE . '/ask',
            array(
                'timeout'     => 60,
                'headers'     => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'text/event-stream',
                ),
                'body'        => wp_json_encode( array(
                    'conversationId' => $conversation_id,
                    'question'       => 'Hello, what is this document about?',
                    'model'          => 'gpt-4o',
                ) ),
            )
        );

        if ( is_wp_error( $ask_response ) ) {
            wp_send_json_error( array( 'message' => 'Ask connection error: ' . $ask_response->get_error_message() ) );
        }

        $ask_code = wp_remote_retrieve_response_code( $ask_response );
        $ask_body = wp_remote_retrieve_body( $ask_response );

        if ( $ask_code >= 400 ) {
            wp_send_json_error( array( 'message' => 'Ask failed (HTTP ' . $ask_code . '): ' . substr( $ask_body, 0, 200 ) ) );
        }

        // Try to parse SSE response
        $answer = '';
        $lines = explode( "\n", $ask_body );
        foreach ( $lines as $line ) {
            if ( strpos( $line, 'data: ' ) === 0 ) {
                $json_str = substr( $line, 6 );
                if ( ! empty( $json_str ) && $json_str !== '[DONE]' ) {
                    $data = json_decode( $json_str, true );
                    if ( isset( $data['content'] ) ) {
                        $answer .= $data['content'];
                    }
                }
            }
        }

        if ( ! empty( $answer ) ) {
            wp_send_json_success( array( 'message' => 'Ask works! Response: ' . substr( $answer, 0, 100 ) . '...' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Empty response. Raw: ' . substr( $ask_body, 0, 300 ) ) );
        }
    }

    /**
     * AJAX handler for clearing cache.
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_clear_cache() {
        if ( ! check_ajax_referer( 'humata_admin_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'humata-chatbot' ) ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'humata-chatbot' ) ) );
        }

        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_humata_conversation_%' OR option_name LIKE '_transient_timeout_humata_conversation_%'"
        );

        delete_option( 'humata_document_titles' );

        wp_send_json_success( array( 'message' => __( 'Cache cleared.', 'humata-chatbot' ) ) );
    }
}
