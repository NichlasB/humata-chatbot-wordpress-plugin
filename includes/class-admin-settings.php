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

// Admin settings tabs (WooCommerce-style navigation).
require_once __DIR__ . '/admin-tabs/class-humata-settings-tabs.php';
require_once __DIR__ . '/admin-tabs/tabs/class-tab-base.php';
require_once __DIR__ . '/admin-tabs/tabs/class-tab-general.php';
require_once __DIR__ . '/admin-tabs/tabs/class-tab-providers.php';
require_once __DIR__ . '/admin-tabs/tabs/class-tab-display.php';
require_once __DIR__ . '/admin-tabs/tabs/class-tab-security.php';
require_once __DIR__ . '/admin-tabs/tabs/class-tab-floating-help.php';
require_once __DIR__ . '/admin-tabs/tabs/class-tab-auto-links.php';
require_once __DIR__ . '/admin-tabs/tabs/class-tab-usage.php';

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
     * Settings tabs controller.
     *
     * @since 1.0.0
     * @var Humata_Chatbot_Settings_Tabs|null
     */
    private $tabs_controller = null;

    /**
     * Tab module instances keyed by tab key.
     *
     * @since 1.0.0
     * @var array|null
     */
    private $tab_modules = null;

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_filter( 'wp_redirect', array( $this, 'preserve_tab_on_settings_redirect' ), 10, 2 );
        // Ensure tabbed settings only update options for the active tab (prevents other tabs being wiped).
        add_filter( 'allowed_options', array( $this, 'filter_allowed_options_for_active_tab' ) );
        // Back-compat for older WP versions that still use the legacy filter name.
        add_filter( 'whitelist_options', array( $this, 'filter_allowed_options_for_active_tab' ) );
        // Hard-stop guard: even if WordPress attempts to update all options in the group, prevent cross-tab wipes.
        add_filter( 'pre_update_option', array( $this, 'prevent_cross_tab_option_wipe' ), 9999, 3 );
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
        wp_enqueue_script( 'jquery-ui-sortable' );
        wp_enqueue_media();

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
                    var $hidden = $("#humata_document_ids");
                    var $tokens = $("#humata-document-ids-tokens");
                    if (!$hidden.length || !$tokens.length) {
                        return;
                    }

                    var ids = humataExtractIds($hidden.val());
                    $tokens.empty();
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

                // Document IDs: Import / Export
                function humataSetIoStatus(message, kind) {
                    var $status = $("#humata-document-ids-io-status");
                    if (!$status.length) {
                        return;
                    }

                    $status.removeClass("humata-io-status-success humata-io-status-error humata-io-status-info");
                    if (kind === "success") {
                        $status.addClass("humata-io-status-success");
                    } else if (kind === "error") {
                        $status.addClass("humata-io-status-error");
                    } else if (kind === "info") {
                        $status.addClass("humata-io-status-info");
                    }

                    $status.text(message || "");
                }

                function humataExportIdsToText() {
                    var ids = humataGetTokenIds();
                    if (!ids || !ids.length) {
                        return "";
                    }
                    return ids.join("\\n");
                }

                function humataDownloadFile(filename, content, mimeType) {
                    try {
                        var mime = mimeType || "text/plain;charset=utf-8";
                        var blob = new Blob([content || ""], { type: mime });
                        var url = URL.createObjectURL(blob);
                        var a = document.createElement("a");
                        a.href = url;
                        a.download = filename || "humata-document-ids.txt";
                        document.body.appendChild(a);
                        a.click();
                        setTimeout(function() {
                            try { document.body.removeChild(a); } catch (e) {}
                            try { URL.revokeObjectURL(url); } catch (e) {}
                        }, 0);
                    } catch (e) {
                        humataSetIoStatus("Download failed.", "error");
                    }
                }

                function humataCopyToClipboard(text) {
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        return navigator.clipboard.writeText(text);
                    }

                    return new Promise(function(resolve, reject) {
                        try {
                            var $temp = $("<textarea></textarea>")
                                .css({ position: "absolute", left: "-9999px", top: "0" })
                                .val(text || "");

                            $("body").append($temp);
                            $temp[0].focus();
                            $temp[0].select();

                            var ok = false;
                            try {
                                ok = document.execCommand("copy");
                            } catch (e) {
                                ok = false;
                            }

                            $temp.remove();

                            if (ok) {
                                resolve();
                            } else {
                                reject(new Error("copy_failed"));
                            }
                        } catch (e) {
                            reject(e);
                        }
                    });
                }

                function humataGetImportMode() {
                    var $checked = $("input[name=\\"humata_document_ids_import_mode\\"]:checked");
                    var mode = $checked.length ? String($checked.val() || "") : "";
                    mode = $.trim(mode);
                    return mode === "replace" ? "replace" : "merge";
                }

                function humataApplyImportedText(text, mode) {
                    var importText = String(text || "");
                    var importMode = mode === "replace" ? "replace" : "merge";

                    var existingCount = humataGetTokenIds().length;
                    var ids = humataExtractIds(importText);

                    if (!ids.length) {
                        humataSetIoStatus("No valid UUIDs found to import.", "error");
                        return false;
                    }

                    var beforeCount = existingCount;
                    if (importMode === "replace") {
                        $("#humata-document-ids-tokens").empty();
                        humataDocsPage = 0;
                        beforeCount = 0;
                    }

                    $.each(ids, function(_, id) {
                        humataAddToken(id);
                    });

                    humataSortTokens();
                    humataSyncHiddenAndCount();
                    humataSyncDocumentsTable();

                    var afterCount = humataGetTokenIds().length;
                    if (importMode === "merge") {
                        var addedCount = Math.max(0, afterCount - beforeCount);
                        var dupCount = Math.max(0, ids.length - addedCount);
                        humataSetIoStatus("Imported " + addedCount + " IDs (" + dupCount + " duplicates ignored). Click Save Settings to persist.", "success");
                    } else {
                        humataSetIoStatus("Replaced " + existingCount + " IDs with " + afterCount + " imported IDs. Click Save Settings to persist.", "success");
                    }

                    return true;
                }

                $(document).on("click", "#humata-document-ids-export-copy", function() {
                    var text = humataExportIdsToText();
                    var count = humataGetTokenIds().length;
                    if (!text) {
                        humataSetIoStatus("No document IDs to export.", "error");
                        return;
                    }

                    humataCopyToClipboard(text).then(function() {
                        humataSetIoStatus("Copied " + count + " IDs to clipboard.", "success");
                    }).catch(function() {
                        humataSetIoStatus("Copy failed. Try Download TXT instead.", "error");
                    });
                });

                $(document).on("click", "#humata-document-ids-export-download", function() {
                    var ids = humataGetTokenIds();
                    if (!ids.length) {
                        humataSetIoStatus("No document IDs to export.", "error");
                        return;
                    }
                    var content = ids.join("\\n") + "\\n";
                    humataDownloadFile("humata-document-ids.txt", content, "text/plain;charset=utf-8");
                    humataSetIoStatus("Downloaded TXT for " + ids.length + " IDs.", "info");
                });

                $(document).on("click", "#humata-document-ids-export-download-json", function() {
                    var ids = humataGetTokenIds();
                    if (!ids.length) {
                        humataSetIoStatus("No document IDs to export.", "error");
                        return;
                    }
                    var json = JSON.stringify({ document_ids: ids }, null, 2);
                    humataDownloadFile("humata-document-ids.json", json + "\\n", "application/json;charset=utf-8");
                    humataSetIoStatus("Downloaded JSON for " + ids.length + " IDs.", "info");
                });

                $(document).on("change", "#humata-document-ids-import-file", function() {
                    var input = this;
                    var file = input && input.files && input.files[0] ? input.files[0] : null;
                    if (!file) {
                        return;
                    }

                    var reader = new FileReader();
                    reader.onload = function(e) {
                        var text = (e && e.target && e.target.result) ? String(e.target.result) : "";
                        $("#humata-document-ids-import-text").val(text);
                        humataSetIoStatus("Loaded file. Choose Merge/Replace and click Apply Import.", "info");
                    };
                    reader.onerror = function() {
                        humataSetIoStatus("Failed to read file.", "error");
                    };

                    try {
                        reader.readAsText(file);
                    } catch (e) {
                        humataSetIoStatus("Failed to read file.", "error");
                    }
                });

                $(document).on("click", "#humata-document-ids-import-apply", function() {
                    var text = $("#humata-document-ids-import-text").val();
                    var mode = humataGetImportMode();
                    humataApplyImportedText(text, mode);
                });

                $(document).on("click", "#humata-document-ids-clear-all", function() {
                    var count = humataGetTokenIds().length;
                    if (!count) {
                        humataSetIoStatus("No document IDs to clear.", "info");
                        return;
                    }

                    if (!window.confirm("Remove all configured Document IDs?")) {
                        return;
                    }

                    $("#humata-document-ids-tokens").empty();
                    humataDocsPage = 0;
                    humataSyncHiddenAndCount();
                    humataSyncDocumentsTable();
                    humataSetIoStatus("Cleared all Document IDs. Click Save Settings to persist.", "success");
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

            .humata-document-ids-io {
                max-width: 900px;
                margin-top: 10px;
                padding: 10px 12px;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                background: #fff;
                box-sizing: border-box;
            }
            .humata-document-ids-io-row {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 8px;
                margin-top: 8px;
            }
            .humata-document-ids-io-row:first-child {
                margin-top: 0;
            }
            .humata-document-ids-io-label {
                min-width: 60px;
                font-weight: 600;
                color: #1d2327;
            }
            .humata-document-ids-import-mode {
                display: flex;
                gap: 12px;
                margin: 0;
                padding: 0;
                border: 0;
            }
            .humata-document-ids-import-file-label {
                color: #646970;
            }
            .humata-document-ids-import-file {
                max-width: 100%;
            }
            #humata-document-ids-import-text {
                margin-top: 8px;
                max-width: 900px;
            }
            .humata-document-ids-io-status {
                flex: 1 1 100%;
                margin-top: 6px;
            }
            .humata-io-status-success {
                color: #1d6f42;
            }
            .humata-io-status-error {
                color: #b32d2e;
            }
            .humata-io-status-info {
                color: #646970;
            }

            .humata-floating-help-repeater .humata-repeater-handle-cell {
                width: 36px;
                text-align: center;
                vertical-align: middle;
            }

            .humata-floating-help-repeater .humata-repeater-handle {
                cursor: move;
                color: #646970;
            }

            .humata-floating-help-repeater .humata-repeater-handle:hover {
                color: #2271b1;
            }

            .humata-floating-help-repeater .humata-repeater-placeholder {
                height: 44px;
                background: rgba(34, 113, 177, 0.08);
                border: 1px dashed rgba(34, 113, 177, 0.45);
            }
        ' );

        // Floating Help Menu admin UI helpers (repeaters).
        wp_add_inline_script( 'jquery', '
            jQuery(function($) {
                function humataSafeInt(value, fallback) {
                    var n = parseInt(value, 10);
                    return (isFinite(n) && n >= 0) ? n : (fallback || 0);
                }

                function humataBuildExternalLinkRow(idx) {
                    return "" +
                        "<tr class=\\"humata-repeater-row\\">" +
                            "<td class=\\"humata-repeater-handle-cell\\">" +
                                "<span class=\\"dashicons dashicons-move humata-repeater-handle\\" aria-hidden=\\"true\\"></span>" +
                                "<span class=\\"screen-reader-text\\">Drag to reorder</span>" +
                            "</td>" +
                            "<td style=\\"width: 28%\\">" +
                                "<input type=\\"text\\" class=\\"regular-text\\" name=\\"humata_floating_help[external_links][" + idx + "][label]\\" value=\\"\\" placeholder=\\"Label\\">" +
                            "</td>" +
                            "<td>" +
                                "<input type=\\"url\\" class=\\"regular-text\\" name=\\"humata_floating_help[external_links][" + idx + "][url]\\" value=\\"\\" placeholder=\\"https://example.com/\\">" +
                            "</td>" +
                            "<td style=\\"width: 90px\\">" +
                                "<button type=\\"button\\" class=\\"button link-delete humata-repeater-remove\\">Remove</button>" +
                            "</td>" +
                        "</tr>";
                }

                function humataBuildAutoLinkRow(idx) {
                    return "" +
                        "<tr class=\\"humata-repeater-row\\">" +
                            "<td class=\\"humata-repeater-handle-cell\\">" +
                                "<span class=\\"dashicons dashicons-move humata-repeater-handle\\" aria-hidden=\\"true\\"></span>" +
                                "<span class=\\"screen-reader-text\\">Drag to reorder</span>" +
                            "</td>" +
                            "<td style=\\"width: 38%\\">" +
                                "<input type=\\"text\\" class=\\"regular-text\\" name=\\"humata_auto_links[" + idx + "][phrase]\\" value=\\"\\" placeholder=\\"Phrase (exact match)\\">" +
                            "</td>" +
                            "<td>" +
                                "<input type=\\"url\\" class=\\"regular-text\\" name=\\"humata_auto_links[" + idx + "][url]\\" value=\\"\\" placeholder=\\"https://example.com/\\">" +
                            "</td>" +
                            "<td style=\\"width: 90px\\">" +
                                "<button type=\\"button\\" class=\\"button link-delete humata-repeater-remove\\">Remove</button>" +
                            "</td>" +
                        "</tr>";
                }

                function humataBuildFaqRow(idx) {
                    return "" +
                        "<tr class=\\"humata-repeater-row\\">" +
                            "<td class=\\"humata-repeater-handle-cell\\">" +
                                "<span class=\\"dashicons dashicons-move humata-repeater-handle\\" aria-hidden=\\"true\\"></span>" +
                                "<span class=\\"screen-reader-text\\">Drag to reorder</span>" +
                            "</td>" +
                            "<td style=\\"width: 34%\\">" +
                                "<input type=\\"text\\" class=\\"regular-text\\" name=\\"humata_floating_help[faq_items][" + idx + "][question]\\" value=\\"\\" placeholder=\\"Question\\">" +
                            "</td>" +
                            "<td>" +
                                "<textarea class=\\"large-text\\" rows=\\"3\\" name=\\"humata_floating_help[faq_items][" + idx + "][answer]\\" placeholder=\\"Answer\\"></textarea>" +
                            "</td>" +
                            "<td style=\\"width: 90px\\">" +
                                "<button type=\\"button\\" class=\\"button link-delete humata-repeater-remove\\">Remove</button>" +
                            "</td>" +
                        "</tr>";
                }

                function humataInitRepeaterSortable($container) {
                    if (!$container || !$container.length) return;
                    var $tbody = $container.find("tbody");
                    if (!$tbody.length) return;
                    if (!$tbody.sortable) return;
                    if ($tbody.data("humataSortableInit")) return;

                    $tbody.sortable({
                        axis: "y",
                        items: "> tr",
                        handle: ".humata-repeater-handle",
                        placeholder: "humata-repeater-placeholder",
                        forcePlaceholderSize: true,
                        tolerance: "pointer"
                    });

                    try { $tbody.disableSelection(); } catch (e) {}
                    $tbody.data("humataSortableInit", true);
                }

                function humataAddRepeaterRow($container) {
                    if (!$container || !$container.length) return;

                    var type = String($container.data("humataRepeater") || "");
                    var nextIdx = humataSafeInt($container.attr("data-next-index"), $container.find("tbody tr").length);

                    var html = "";
                    if (type === "external_links") {
                        html = humataBuildExternalLinkRow(nextIdx);
                    } else if (type === "auto_links") {
                        html = humataBuildAutoLinkRow(nextIdx);
                    } else if (type === "faq_items") {
                        html = humataBuildFaqRow(nextIdx);
                    }

                    if (!html) return;

                    var $tbody = $container.find("tbody");
                    if (!$tbody.length) return;

                    $tbody.append(html);
                    $container.attr("data-next-index", String(nextIdx + 1));
                    humataInitRepeaterSortable($container);
                }

                $(document).on("click", ".humata-floating-help-repeater .humata-repeater-add", function() {
                    humataAddRepeaterRow($(this).closest(".humata-floating-help-repeater"));
                });

                $(document).on("click", ".humata-floating-help-repeater .humata-repeater-remove", function() {
                    $(this).closest("tr").remove();
                });

                // Initialize sortable for existing repeaters.
                $(".humata-floating-help-repeater").each(function() {
                    humataInitRepeaterSortable($(this));
                });

                // Avatar uploader handlers.
                $(document).on("click", ".humata-upload-avatar", function(e) {
                    e.preventDefault();
                    var targetId = $(this).data("target");
                    var frame = wp.media({
                        title: "Select Avatar Image",
                        button: { text: "Use this image" },
                        multiple: false,
                        library: { type: ["image/jpeg", "image/png"] }
                    });
                    frame.on("select", function() {
                        var attachment = frame.state().get("selection").first().toJSON();
                        $("#" + targetId).val(attachment.url);
                        $("#" + targetId + "_preview").html("<img src=\"" + attachment.url + "\" style=\"max-width:64px;max-height:64px;border-radius:6px;\" />");
                        $(".humata-remove-avatar[data-target=\"" + targetId + "\"]").show();
                    });
                    frame.open();
                });

                $(document).on("click", ".humata-remove-avatar", function(e) {
                    e.preventDefault();
                    var targetId = $(this).data("target");
                    $("#" + targetId).val("");
                    $("#" + targetId + "_preview").empty();
                    $(this).hide();
                });
            });
        ' );
    }

    /**
     * Get settings tabs controller.
     *
     * @since 1.0.0
     * @return Humata_Chatbot_Settings_Tabs
     */
    private function get_tabs_controller() {
        if ( null === $this->tabs_controller ) {
            $this->tabs_controller = new Humata_Chatbot_Settings_Tabs();
        }

        return $this->tabs_controller;
    }

    /**
     * Get tab module instances keyed by tab key.
     *
     * @since 1.0.0
     * @return array
     */
    private function get_tab_modules() {
        if ( null !== $this->tab_modules ) {
            return $this->tab_modules;
        }

        $this->tab_modules = array(
            'general'       => new Humata_Chatbot_Settings_Tab_General( $this ),
            'providers'     => new Humata_Chatbot_Settings_Tab_Providers( $this ),
            'display'       => new Humata_Chatbot_Settings_Tab_Display( $this ),
            'security'      => new Humata_Chatbot_Settings_Tab_Security( $this ),
            'floating_help' => new Humata_Chatbot_Settings_Tab_Floating_Help( $this ),
            'auto_links'    => new Humata_Chatbot_Settings_Tab_Auto_Links( $this ),
            'usage'         => new Humata_Chatbot_Settings_Tab_Usage( $this ),
        );

        return $this->tab_modules;
    }

    /**
     * Preserve the active settings tab on Settings API redirects.
     *
     * WordPress redirects back to the settings page after saving via `options.php`.
     * We ensure `tab=` is preserved even if the referer is stripped/modified.
     *
     * @since 1.0.0
     * @param string $location Redirect location.
     * @param int    $status   HTTP status code.
     * @return string
     */
    public function preserve_tab_on_settings_redirect( $location, $status ) {
        if ( ! is_admin() ) {
            return $location;
        }

        if ( empty( $_POST['option_page'] ) ) {
            return $location;
        }

        $option_page = sanitize_key( (string) wp_unslash( $_POST['option_page'] ) );
        if ( 'humata_chatbot_settings' !== $option_page ) {
            return $location;
        }

        if ( empty( $_POST['humata_active_tab'] ) ) {
            return $location;
        }

        $tab = sanitize_key( (string) wp_unslash( $_POST['humata_active_tab'] ) );
        if ( '' === $tab ) {
            return $location;
        }

        $location_str = (string) $location;
        if ( false === strpos( $location_str, 'options-general.php' ) || false === strpos( $location_str, 'page=humata-chatbot' ) ) {
            return $location;
        }

        $tabs = $this->get_tabs_controller();
        if ( ! $tabs->is_valid_tab( $tab ) ) {
            $tab = $tabs->get_default_tab();
        }

        if ( '' === $tab ) {
            return $location;
        }

        return add_query_arg( 'tab', $tab, $location_str );
    }

    /**
     * Filter which options are updated on submit when using tabbed Settings API forms.
     *
     * WordPress updates every option registered to a given Settings API group when posting to `options.php`.
     * Since this plugin uses tabs (each tab shows only a subset of fields), we must restrict updates to
     * the options that belong to the submitted tab. Otherwise, fields not present in the form can be
     * saved as empty, wiping settings from other tabs.
     *
     * @since 1.0.0
     * @param array $allowed_options Map of option_page => option names.
     * @return array
     */
    public function filter_allowed_options_for_active_tab( $allowed_options ) {
        if ( ! is_admin() || ! is_array( $allowed_options ) ) {
            return $allowed_options;
        }

        if ( empty( $_POST['option_page'] ) ) {
            return $allowed_options;
        }

        $option_page = sanitize_key( (string) wp_unslash( $_POST['option_page'] ) );
        if ( 'humata_chatbot_settings' !== $option_page ) {
            return $allowed_options;
        }

        if ( empty( $_POST['humata_active_tab'] ) ) {
            return $allowed_options;
        }

        $tab = sanitize_key( (string) wp_unslash( $_POST['humata_active_tab'] ) );
        if ( '' === $tab ) {
            return $allowed_options;
        }

        $tab_to_options = array(
            'general'       => array(
                'humata_api_key',
                'humata_document_ids',
                'humata_system_prompt',
            ),
            'providers'     => array(
                'humata_second_llm_provider',
                'humata_straico_api_key',
                'humata_straico_model',
                'humata_anthropic_api_key',
                'humata_anthropic_model',
                'humata_anthropic_extended_thinking',
                'humata_straico_system_prompt',
            ),
            'display'       => array(
                'humata_chat_location',
                'humata_chat_page_slug',
                'humata_chat_theme',
                'humata_medical_disclaimer_text',
                'humata_footer_copyright_text',
                'humata_bot_response_disclaimer',
                'humata_user_avatar_url',
                'humata_bot_avatar_url',
                'humata_avatar_size',
            ),
            'security'      => array(
                'humata_max_prompt_chars',
                'humata_rate_limit',
            ),
            'floating_help' => array(
                'humata_floating_help',
            ),
            'auto_links'    => array(
                'humata_auto_links',
            ),
        );

        if ( isset( $tab_to_options[ $tab ] ) && is_array( $tab_to_options[ $tab ] ) ) {
            $allowed_options['humata_chatbot_settings'] = $tab_to_options[ $tab ];
        }

        return $allowed_options;
    }

    /**
     * Prevent options from other tabs being wiped when saving a single tab.
     *
     * Some WordPress setups still end up calling update_option() for every option in a settings group,
     * even when only one tab's fields are present in the submitted form. This guard preserves the old
     * value for plugin options that do not belong to the active tab being saved.
     *
     * @since 1.0.0
     * @param mixed  $value     The new, unsanitized option value.
     * @param string $option    Option name.
     * @param mixed  $old_value The old option value.
     * @return mixed
     */
    public function prevent_cross_tab_option_wipe( $value, $option, $old_value ) {
        if ( ! is_admin() ) {
            return $value;
        }

        if ( empty( $_POST['option_page'] ) ) {
            return $value;
        }

        $option_page = sanitize_key( (string) wp_unslash( $_POST['option_page'] ) );
        if ( 'humata_chatbot_settings' !== $option_page ) {
            return $value;
        }

        // Determine active tab: prefer explicit hidden field, fallback to referer querystring.
        $tab = '';
        if ( ! empty( $_POST['humata_active_tab'] ) ) {
            $tab = sanitize_key( (string) wp_unslash( $_POST['humata_active_tab'] ) );
        } elseif ( ! empty( $_POST['_wp_http_referer'] ) ) {
            $ref = (string) wp_unslash( $_POST['_wp_http_referer'] );
            $parts = wp_parse_url( $ref );
            if ( is_array( $parts ) && ! empty( $parts['query'] ) ) {
                $query_vars = array();
                wp_parse_str( (string) $parts['query'], $query_vars );
                if ( isset( $query_vars['tab'] ) ) {
                    $tab = sanitize_key( (string) $query_vars['tab'] );
                }
            }
        }

        if ( '' === $tab ) {
            return $value;
        }

        // Only apply to this plugin's option names.
        $plugin_options = array(
            'humata_api_key',
            'humata_document_ids',
            'humata_document_titles',
            'humata_folder_id',
            'humata_chat_location',
            'humata_chat_page_slug',
            'humata_chat_theme',
            'humata_system_prompt',
            'humata_rate_limit',
            'humata_max_prompt_chars',
            'humata_medical_disclaimer_text',
            'humata_footer_copyright_text',
            'humata_second_llm_provider',
            'humata_straico_review_enabled',
            'humata_straico_api_key',
            'humata_straico_model',
            'humata_straico_system_prompt',
            'humata_anthropic_api_key',
            'humata_anthropic_model',
            'humata_anthropic_extended_thinking',
            'humata_floating_help',
            'humata_auto_links',
            'humata_user_avatar_url',
            'humata_bot_avatar_url',
            'humata_avatar_size',
            'humata_bot_response_disclaimer',
        );

        if ( ! in_array( (string) $option, $plugin_options, true ) ) {
            return $value;
        }

        $tab_to_options = array(
            'general'       => array(
                'humata_api_key',
                'humata_document_ids',
                'humata_system_prompt',
            ),
            'providers'     => array(
                'humata_second_llm_provider',
                'humata_straico_api_key',
                'humata_straico_model',
                'humata_anthropic_api_key',
                'humata_anthropic_model',
                'humata_anthropic_extended_thinking',
                'humata_straico_system_prompt',
            ),
            'display'       => array(
                'humata_chat_location',
                'humata_chat_page_slug',
                'humata_chat_theme',
                'humata_medical_disclaimer_text',
                'humata_footer_copyright_text',
                'humata_bot_response_disclaimer',
                'humata_user_avatar_url',
                'humata_bot_avatar_url',
                'humata_avatar_size',
            ),
            'security'      => array(
                'humata_max_prompt_chars',
                'humata_rate_limit',
            ),
            'floating_help' => array(
                'humata_floating_help',
            ),
            'auto_links'    => array(
                'humata_auto_links',
            ),
        );

        if ( ! isset( $tab_to_options[ $tab ] ) || ! is_array( $tab_to_options[ $tab ] ) ) {
            return $value;
        }

        // If this option isn't part of the active tab, preserve it.
        if ( ! in_array( (string) $option, $tab_to_options[ $tab ], true ) ) {
            return $old_value;
        }

        return $value;
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
            'humata_medical_disclaimer_text',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
                'default'           => '',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_footer_copyright_text',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_bot_response_disclaimer',
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_bot_response_disclaimer' ),
                'default'           => '',
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

        register_setting(
            'humata_chatbot_settings',
            'humata_floating_help',
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_floating_help' ),
                'default'           => self::get_default_floating_help_option(),
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_auto_links',
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_auto_links' ),
                'default'           => array(),
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_user_avatar_url',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default'           => '',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_bot_avatar_url',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default'           => '',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_avatar_size',
            array(
                'type'              => 'integer',
                'sanitize_callback' => array( $this, 'sanitize_avatar_size' ),
                'default'           => 40,
            )
        );

        // Register per-tab sections/fields.
        foreach ( $this->get_tab_modules() as $module ) {
            if ( is_object( $module ) && method_exists( $module, 'register' ) ) {
                $module->register();
            }
        }
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
     * Sanitize bot response disclaimer HTML.
     * Allows limited HTML: links, bold, italic, line breaks.
     *
     * @since 1.0.0
     * @param string $value Input value.
     * @return string Sanitized HTML.
     */
    public function sanitize_bot_response_disclaimer( $value ) {
        if ( ! is_string( $value ) ) {
            return '';
        }

        $allowed_html = array(
            'a'      => array(
                'href'   => array(),
                'target' => array(),
                'rel'    => array(),
                'title'  => array(),
            ),
            'strong' => array(),
            'b'      => array(),
            'em'     => array(),
            'i'      => array(),
            'br'     => array(),
        );

        return wp_kses( $value, $allowed_html );
    }

    /**
     * Sanitize avatar size option.
     *
     * @since 1.0.0
     * @param mixed $value Input value.
     * @return int Clamped value between 32 and 64.
     */
    public function sanitize_avatar_size( $value ) {
        $value = absint( $value );
        if ( $value < 32 ) {
            $value = 32;
        }
        if ( $value > 64 ) {
            $value = 64;
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

    /**
     * Get default floating help menu option shape/content.
     *
     * @since 1.0.0
     * @return array
     */
    public static function get_default_floating_help_option() {
        $contact_html = ''
            . '<h2>Dr. Morse Office Contact Information</h2>'
            . '<h3>Handcrafted Botanical Formulas</h3>'
            . '<p><strong>Function:</strong> Sells Dr. Morse&#8217;s &ldquo;Handcrafted Botanical Formulas&rdquo; herbal formula line and &ldquo;Doctor Morse&#8217;s&rdquo; herbal formula line</p>'
            . '<p><strong>Website:</strong> <a href="https://handcraftedbotanicalformulas.com/" target="_blank" rel="noopener noreferrer">https://handcraftedbotanicalformulas.com/</a></p>'
            . '<p><strong>Phone:</strong> <a href="tel:+19416231313">+1 (941) 623-1313</a></p>'
            . '<p><strong>Email:</strong> <a href="mailto:info@handcraftedbotanicals.com">info@handcraftedbotanicals.com</a></p>'
            . '<p><strong>Contact page:</strong> <a href="https://handcraftedbotanicalformulas.com/contact/" target="_blank" rel="noopener noreferrer">https://handcraftedbotanicalformulas.com/contact/</a></p>'
            . '<hr>'
            . '<h3>Morse&#8217;s Health Center</h3>'
            . '<p><strong>Function:</strong> Provides detoxification consultation services with a qualified Detoxification Specialist, sells Dr. Morse&#8217;s &ldquo;Handcrafted Botanical Formulas&rdquo; herbal formula line and &ldquo;Doctor Morse&#8217;s&rdquo; herbal formula line, sells glandular therapy supplements</p>'
            . '<p><strong>Website:</strong> <a href="https://morseshealthcenter.com" target="_blank" rel="noopener noreferrer">https://morseshealthcenter.com</a></p>'
            . '<p><strong>Phone:</strong> <a href="tel:+19412551970">+1 (941) 255-1970</a></p>'
            . '<p><strong>Email:</strong> <a href="mailto:info@morseshealthcenter.com">info@morseshealthcenter.com</a></p>'
            . '<hr>'
            . '<h3>International School of the Healing Arts (&#8220;ISHA&#8221;)</h3>'
            . '<p><strong>Function:</strong> Sells access to Dr. Morse&#8217;s online courses (including &ldquo;Level One&rdquo;, &ldquo;Level Two&rdquo;, and &ldquo;Lymphatic Iridology&rdquo;) as well as courses by other ISHA professors</p>'
            . '<p><strong>Website:</strong> <a href="https://internationalschoolofthehealingarts.com/" target="_blank" rel="noopener noreferrer">https://internationalschoolofthehealingarts.com/</a></p>'
            . '<p><strong>Contact page:</strong> <a href="https://internationalschoolofthehealingarts.com/contact/" target="_blank" rel="noopener noreferrer">https://internationalschoolofthehealingarts.com/contact/</a></p>'
            . '<hr>'
            . '<h3>Handcrafted.Health</h3>'
            . '<p><strong>Function:</strong> Web directory of all of Dr. Morse&#8217;s websites</p>'
            . '<p><strong>Website:</strong> <a href="https://handcrafted.health/" target="_blank" rel="noopener noreferrer">https://handcrafted.health/</a></p>'
            . '<hr>'
            . '<h3>DrMorses.tv</h3>'
            . '<p><strong>Function:</strong> Dr. Morse&#8217;s own video streaming platform where users can watch all his videos and submit health-related questions</p>'
            . '<p><strong>Website:</strong> <a href="https://drmorses.tv/" target="_blank" rel="noopener noreferrer">https://drmorses.tv/</a></p>'
            . '<p><strong>Email:</strong> <a href="mailto:questions@morses.tv">questions@morses.tv</a></p>'
            . '<p><strong>Contact page:</strong> <a href="https://drmorses.tv/contact/" target="_blank" rel="noopener noreferrer">https://drmorses.tv/contact/</a></p>'
            . '<p><strong>Ask a question form:</strong> <a href="https://drmorses.tv/ask/" target="_blank" rel="noopener noreferrer">https://drmorses.tv/ask/</a></p>'
            . '<hr>'
            . '<h3>GrapeGate</h3>'
            . '<p><strong>Function:</strong> International practitioner directory for certified Detoxification Specialists where visitors can find a Dr. Morse-trained practitioner to work with</p>'
            . '<p><strong>Website:</strong> <a href="https://grapegate.com/" target="_blank" rel="noopener noreferrer">https://grapegate.com/</a></p>'
            . '<p><strong>Directory page:</strong> <a href="https://grapegate.com/list-of-isod-practitioners/" target="_blank" rel="noopener noreferrer">https://grapegate.com/list-of-isod-practitioners/</a></p>'
            . '<hr>'
            . '<h3>Best places to contact</h3>'
            . '<ul>'
            . '<li><strong>Non-urgent health questions for Dr. Morse:</strong> Use <a href="https://drmorses.tv/ask/" target="_blank" rel="noopener noreferrer">https://drmorses.tv/ask/</a> or email <a href="mailto:questions@morses.tv">questions@morses.tv</a></li>'
            . '<li><strong>Questions about herbal formulas:</strong> Use <a href="https://handcraftedbotanicalformulas.com/contact/" target="_blank" rel="noopener noreferrer">https://handcraftedbotanicalformulas.com/contact/</a> or email <a href="mailto:info@handcraftedbotanicals.com">info@handcraftedbotanicals.com</a></li>'
            . '<li><strong>Consultation appointments:</strong> Use <a href="https://morseshealthcenter.com/contact/" target="_blank" rel="noopener noreferrer">https://morseshealthcenter.com/contact/</a> or email <a href="mailto:info@morseshealthcenter.com">info@morseshealthcenter.com</a></li>'
            . '<li><strong>Questions about ISHA courses:</strong> Use <a href="https://internationalschoolofthehealingarts.com/contact/" target="_blank" rel="noopener noreferrer">https://internationalschoolofthehealingarts.com/contact/</a></li>'
            . '</ul>'
            . '<p><strong>Urgent health situations:</strong> Contact your local emergency services.</p>';

        return array(
            'enabled'        => 0,
            'button_label'   => 'Help',
            'external_links' => array(),
            'show_faq'       => 1,
            'faq_label'      => 'FAQs',
            'show_contact'   => 1,
            'contact_label'  => 'Contact',
            'social'         => array(
                'facebook'  => '',
                'instagram' => '',
                'youtube'   => '',
                'x'         => '',
                'tiktok'    => '',
            ),
            'footer_text'   => '',
            'faq_items'     => array(
                array(
                    'question' => 'How do I use the Help menu?',
                    'answer'   => 'On desktop, hover over the Help button to open the menu. On mobile or touch devices, tap the Help button to open or close it.',
                ),
                array(
                    'question' => 'Where can I find answers to common questions?',
                    'answer'   => 'Open the Help menu and click the FAQs link to view common questions and answers in a full-screen popup.',
                ),
                array(
                    'question' => 'How do I contact support?',
                    'answer'   => 'Open the Help menu and click Contact to view the best ways to reach the appropriate team for your question.',
                ),
            ),
            'contact_html'  => $contact_html,
        );
    }

    /**
     * Get floating help settings merged with defaults.
     *
     * @since 1.0.0
     * @return array
     */
    private function get_floating_help_settings() {
        $value = get_option( 'humata_floating_help', array() );
        if ( ! is_array( $value ) ) {
            $value = array();
        }

        $defaults = self::get_default_floating_help_option();
        $value    = wp_parse_args( $value, $defaults );

        if ( ! isset( $value['social'] ) || ! is_array( $value['social'] ) ) {
            $value['social'] = array();
        }
        $value['social'] = wp_parse_args( $value['social'], $defaults['social'] );

        if ( ! isset( $value['external_links'] ) || ! is_array( $value['external_links'] ) ) {
            $value['external_links'] = array();
        }

        if ( ! isset( $value['faq_items'] ) || ! is_array( $value['faq_items'] ) ) {
            $value['faq_items'] = array();
        }

        return $value;
    }

    /**
     * Sanitize floating help settings.
     *
     * @since 1.0.0
     * @param mixed $value
     * @return array
     */
    public function sanitize_floating_help( $value ) {
        $defaults = self::get_default_floating_help_option();

        if ( ! is_array( $value ) ) {
            $value = array();
        }

        $clean = array();

        $clean['enabled']      = empty( $value['enabled'] ) ? 0 : 1;
        $clean['show_faq']     = empty( $value['show_faq'] ) ? 0 : 1;
        $clean['show_contact'] = empty( $value['show_contact'] ) ? 0 : 1;

        $button_label = isset( $value['button_label'] ) ? sanitize_text_field( (string) $value['button_label'] ) : $defaults['button_label'];
        $clean['button_label'] = '' !== $button_label ? $button_label : $defaults['button_label'];

        $faq_label = isset( $value['faq_label'] ) ? sanitize_text_field( (string) $value['faq_label'] ) : $defaults['faq_label'];
        $clean['faq_label'] = '' !== $faq_label ? $faq_label : $defaults['faq_label'];

        $contact_label = isset( $value['contact_label'] ) ? sanitize_text_field( (string) $value['contact_label'] ) : $defaults['contact_label'];
        $clean['contact_label'] = '' !== $contact_label ? $contact_label : $defaults['contact_label'];

        // External links
        $external_links = array();
        if ( isset( $value['external_links'] ) && is_array( $value['external_links'] ) ) {
            foreach ( $value['external_links'] as $row ) {
                if ( ! is_array( $row ) ) {
                    continue;
                }

                $label = isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '';
                $url   = isset( $row['url'] ) ? esc_url_raw( (string) $row['url'] ) : '';

                if ( '' === $label || '' === $url ) {
                    continue;
                }

                $external_links[] = array(
                    'label' => $label,
                    'url'   => $url,
                );

                if ( count( $external_links ) >= 50 ) {
                    break;
                }
            }
        }
        $clean['external_links'] = array_values( $external_links );

        // Social links
        $social_in = isset( $value['social'] ) && is_array( $value['social'] ) ? $value['social'] : array();
        $clean['social'] = array();
        foreach ( array( 'facebook', 'instagram', 'youtube', 'x', 'tiktok' ) as $key ) {
            $clean['social'][ $key ] = isset( $social_in[ $key ] ) ? esc_url_raw( (string) $social_in[ $key ] ) : '';
        }

        // Footer text (allow basic formatting/links).
        $footer_text = isset( $value['footer_text'] ) ? trim( (string) $value['footer_text'] ) : '';
        $clean['footer_text'] = '' === $footer_text ? '' : wp_kses_post( $footer_text );

        // FAQ items (plain text answers).
        $faq_items = array();
        if ( isset( $value['faq_items'] ) && is_array( $value['faq_items'] ) ) {
            foreach ( $value['faq_items'] as $row ) {
                if ( ! is_array( $row ) ) {
                    continue;
                }

                $question = isset( $row['question'] ) ? sanitize_text_field( (string) $row['question'] ) : '';
                $answer   = isset( $row['answer'] ) ? sanitize_textarea_field( (string) $row['answer'] ) : '';

                if ( '' === $question || '' === $answer ) {
                    continue;
                }

                $faq_items[] = array(
                    'question' => $question,
                    'answer'   => $answer,
                );

                if ( count( $faq_items ) >= 50 ) {
                    break;
                }
            }
        }
        $clean['faq_items'] = array_values( $faq_items );

        // Contact HTML (allow safe HTML).
        $contact_html = isset( $value['contact_html'] ) ? trim( (string) $value['contact_html'] ) : '';
        $clean['contact_html'] = '' === $contact_html ? '' : wp_kses_post( $contact_html );

        return $clean;
    }

    /**
     * Sanitize auto-link phrase→URL mappings.
     *
     * Stored as an ordered array of rows: [ [ 'phrase' => string, 'url' => string ], ... ].
     *
     * @since 1.0.0
     * @param mixed $value
     * @return array
     */
    public function sanitize_auto_links( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }

        $rows = array();
        foreach ( $value as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $phrase = isset( $row['phrase'] ) ? trim( sanitize_text_field( (string) $row['phrase'] ) ) : '';
            $url    = isset( $row['url'] ) ? trim( esc_url_raw( (string) $row['url'] ) ) : '';

            if ( '' === $phrase || '' === $url ) {
                continue;
            }

            $rows[] = array(
                'phrase' => $phrase,
                'url'    => $url,
            );

            if ( count( $rows ) >= 200 ) {
                break;
            }
        }

        return array_values( $rows );
    }

    /**
     * Get auto-link rules (phrase → URL), sanitized and normalized.
     *
     * @since 1.0.0
     * @return array
     */
    private function get_auto_links_settings() {
        $value = get_option( 'humata_auto_links', array() );
        if ( ! is_array( $value ) ) {
            $value = array();
        }

        // Reuse the sanitizer defensively to guarantee a stable shape.
        return $this->sanitize_auto_links( $value );
    }

    /**
     * Auto-Links section description.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_auto_links_section() {
        echo '<p>' . esc_html__( 'Define phrase → URL rules. When a phrase appears in bot messages, it will be automatically linked.', 'humata-chatbot' ) . '</p>';
    }

    /**
     * Render the auto-links repeater UI.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_auto_links_field() {
        $rules = $this->get_auto_links_settings();

        // Show at least one empty row for UX.
        if ( empty( $rules ) ) {
            $rules = array(
                array( 'phrase' => '', 'url' => '' ),
            );
        }

        $next_index = count( $rules );
        ?>
        <div class="humata-floating-help-repeater" data-humata-repeater="auto_links" data-next-index="<?php echo esc_attr( (string) $next_index ); ?>">
            <p class="description" style="margin-top: 0;">
                <?php esc_html_e( 'Drag and drop rows to reorder. Matching is case-insensitive and prefers longer phrases when rules overlap.', 'humata-chatbot' ); ?>
            </p>
            <table class="widefat striped" style="max-width: 900px;">
                <thead>
                    <tr>
                        <th style="width: 36px;"><span class="screen-reader-text"><?php esc_html_e( 'Order', 'humata-chatbot' ); ?></span></th>
                        <th><?php esc_html_e( 'Phrase', 'humata-chatbot' ); ?></th>
                        <th><?php esc_html_e( 'URL (opens in a new tab)', 'humata-chatbot' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'humata-chatbot' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $rules as $i => $row ) : ?>
                        <?php
                        $row = is_array( $row ) ? $row : array();
                        $phrase = isset( $row['phrase'] ) ? (string) $row['phrase'] : '';
                        $url    = isset( $row['url'] ) ? (string) $row['url'] : '';
                        ?>
                        <tr class="humata-repeater-row">
                            <td class="humata-repeater-handle-cell">
                                <span class="dashicons dashicons-move humata-repeater-handle" aria-hidden="true"></span>
                                <span class="screen-reader-text"><?php esc_html_e( 'Drag to reorder', 'humata-chatbot' ); ?></span>
                            </td>
                            <td style="width: 38%;">
                                <input
                                    type="text"
                                    class="regular-text"
                                    name="humata_auto_links[<?php echo esc_attr( (string) $i ); ?>][phrase]"
                                    value="<?php echo esc_attr( $phrase ); ?>"
                                    placeholder="<?php esc_attr_e( 'Phrase (exact match)', 'humata-chatbot' ); ?>"
                                />
                            </td>
                            <td>
                                <input
                                    type="url"
                                    class="regular-text"
                                    name="humata_auto_links[<?php echo esc_attr( (string) $i ); ?>][url]"
                                    value="<?php echo esc_attr( $url ); ?>"
                                    placeholder="<?php esc_attr_e( 'https://example.com/', 'humata-chatbot' ); ?>"
                                />
                            </td>
                            <td style="width: 90px;">
                                <button type="button" class="button link-delete humata-repeater-remove"><?php esc_html_e( 'Remove', 'humata-chatbot' ); ?></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="margin-top: 10px;">
                <button type="button" class="button button-secondary humata-repeater-add"><?php esc_html_e( 'Add Rule', 'humata-chatbot' ); ?></button>
            </p>
            <p class="description">
                <?php esc_html_e( 'Auto-links are applied to bot messages only. Existing Markdown links and code blocks/spans are not modified.', 'humata-chatbot' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Floating Help Menu section description.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_floating_help_section() {
        echo '<p>' . esc_html__( 'Configure an optional floating Help button that reveals a menu on hover (desktop) or tap (touch devices).', 'humata-chatbot' ) . '</p>';
    }

    public function render_floating_help_enabled_field() {
        $settings = $this->get_floating_help_settings();
        $enabled  = ! empty( $settings['enabled'] ) ? 1 : 0;
        $label    = isset( $settings['button_label'] ) ? (string) $settings['button_label'] : 'Help';
        ?>
        <label>
            <input
                type="checkbox"
                name="humata_floating_help[enabled]"
                value="1"
                <?php checked( $enabled, 1 ); ?>
            />
            <?php esc_html_e( 'Enable the floating Help button and hover menu.', 'humata-chatbot' ); ?>
        </label>
        <p class="description">
            <?php esc_html_e( 'When disabled, no button, menu, or assets are rendered on the frontend.', 'humata-chatbot' ); ?>
        </p>
        <p>
            <label for="humata_floating_help_button_label"><strong><?php esc_html_e( 'Button label', 'humata-chatbot' ); ?></strong></label><br>
            <input
                type="text"
                id="humata_floating_help_button_label"
                name="humata_floating_help[button_label]"
                value="<?php echo esc_attr( $label ); ?>"
                class="regular-text"
            />
        </p>
        <?php
    }

    public function render_floating_help_external_links_field() {
        $settings = $this->get_floating_help_settings();
        $links    = isset( $settings['external_links'] ) && is_array( $settings['external_links'] ) ? $settings['external_links'] : array();

        // Show at least one empty row for UX.
        if ( empty( $links ) ) {
            $links = array(
                array( 'label' => '', 'url' => '' ),
            );
        }

        $next_index = count( $links );
        ?>
        <div class="humata-floating-help-repeater" data-humata-repeater="external_links" data-next-index="<?php echo esc_attr( (string) $next_index ); ?>">
            <p class="description" style="margin-top: 0;">
                <?php esc_html_e( 'Drag and drop rows to reorder.', 'humata-chatbot' ); ?>
            </p>
            <table class="widefat striped" style="max-width: 900px;">
                <thead>
                    <tr>
                        <th style="width: 36px;"><span class="screen-reader-text"><?php esc_html_e( 'Order', 'humata-chatbot' ); ?></span></th>
                        <th><?php esc_html_e( 'Label', 'humata-chatbot' ); ?></th>
                        <th><?php esc_html_e( 'URL (opens in a new tab)', 'humata-chatbot' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'humata-chatbot' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $links as $i => $row ) : ?>
                        <?php
                        $row = is_array( $row ) ? $row : array();
                        $label = isset( $row['label'] ) ? (string) $row['label'] : '';
                        $url   = isset( $row['url'] ) ? (string) $row['url'] : '';
                        ?>
                        <tr class="humata-repeater-row">
                            <td class="humata-repeater-handle-cell">
                                <span class="dashicons dashicons-move humata-repeater-handle" aria-hidden="true"></span>
                                <span class="screen-reader-text"><?php esc_html_e( 'Drag to reorder', 'humata-chatbot' ); ?></span>
                            </td>
                            <td style="width: 28%;">
                                <input
                                    type="text"
                                    class="regular-text"
                                    name="humata_floating_help[external_links][<?php echo esc_attr( (string) $i ); ?>][label]"
                                    value="<?php echo esc_attr( $label ); ?>"
                                    placeholder="<?php esc_attr_e( 'Label', 'humata-chatbot' ); ?>"
                                />
                            </td>
                            <td>
                                <input
                                    type="url"
                                    class="regular-text"
                                    name="humata_floating_help[external_links][<?php echo esc_attr( (string) $i ); ?>][url]"
                                    value="<?php echo esc_attr( $url ); ?>"
                                    placeholder="<?php esc_attr_e( 'https://example.com/', 'humata-chatbot' ); ?>"
                                />
                            </td>
                            <td style="width: 90px;">
                                <button type="button" class="button link-delete humata-repeater-remove"><?php esc_html_e( 'Remove', 'humata-chatbot' ); ?></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="margin-top: 10px;">
                <button type="button" class="button button-secondary humata-repeater-add"><?php esc_html_e( 'Add Link', 'humata-chatbot' ); ?></button>
            </p>
            <p class="description">
                <?php esc_html_e( 'These links appear in the top section of the menu and open in a new tab.', 'humata-chatbot' ); ?>
            </p>
        </div>
        <?php
    }

    public function render_floating_help_modals_field() {
        $settings = $this->get_floating_help_settings();

        $show_faq     = ! empty( $settings['show_faq'] ) ? 1 : 0;
        $faq_label    = isset( $settings['faq_label'] ) ? (string) $settings['faq_label'] : 'FAQs';
        $show_contact = ! empty( $settings['show_contact'] ) ? 1 : 0;
        $contact_label = isset( $settings['contact_label'] ) ? (string) $settings['contact_label'] : 'Contact';
        ?>
        <fieldset>
            <label style="display:block; margin-bottom: 10px;">
                <input
                    type="checkbox"
                    name="humata_floating_help[show_faq]"
                    value="1"
                    <?php checked( $show_faq, 1 ); ?>
                />
                <?php esc_html_e( 'Show FAQ modal trigger', 'humata-chatbot' ); ?>
            </label>
            <p style="margin: 0 0 16px 0;">
                <label for="humata_floating_help_faq_label"><strong><?php esc_html_e( 'FAQ link label', 'humata-chatbot' ); ?></strong></label><br>
                <input
                    type="text"
                    id="humata_floating_help_faq_label"
                    name="humata_floating_help[faq_label]"
                    value="<?php echo esc_attr( $faq_label ); ?>"
                    class="regular-text"
                />
            </p>

            <label style="display:block; margin-bottom: 10px;">
                <input
                    type="checkbox"
                    name="humata_floating_help[show_contact]"
                    value="1"
                    <?php checked( $show_contact, 1 ); ?>
                />
                <?php esc_html_e( 'Show Contact modal trigger', 'humata-chatbot' ); ?>
            </label>
            <p style="margin: 0;">
                <label for="humata_floating_help_contact_label"><strong><?php esc_html_e( 'Contact link label', 'humata-chatbot' ); ?></strong></label><br>
                <input
                    type="text"
                    id="humata_floating_help_contact_label"
                    name="humata_floating_help[contact_label]"
                    value="<?php echo esc_attr( $contact_label ); ?>"
                    class="regular-text"
                />
            </p>
        </fieldset>
        <p class="description">
            <?php esc_html_e( 'These links open full-screen modal overlays.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    public function render_floating_help_social_field() {
        $settings = $this->get_floating_help_settings();
        $social   = isset( $settings['social'] ) && is_array( $settings['social'] ) ? $settings['social'] : array();
        $keys     = array(
            'facebook'  => __( 'Facebook', 'humata-chatbot' ),
            'instagram' => __( 'Instagram', 'humata-chatbot' ),
            'youtube'   => __( 'YouTube', 'humata-chatbot' ),
            'x'         => __( 'X', 'humata-chatbot' ),
            'tiktok'    => __( 'TikTok', 'humata-chatbot' ),
        );
        ?>
        <table class="form-table" role="presentation" style="margin-top: 0;">
            <tbody>
                <?php foreach ( $keys as $key => $label ) : ?>
                    <?php $url = isset( $social[ $key ] ) ? (string) $social[ $key ] : ''; ?>
                    <tr>
                        <th scope="row" style="padding: 10px 0 10px 0;"><?php echo esc_html( $label ); ?></th>
                        <td style="padding: 10px 0 10px 0;">
                            <input
                                type="url"
                                class="regular-text"
                                name="humata_floating_help[social][<?php echo esc_attr( $key ); ?>]"
                                value="<?php echo esc_attr( $url ); ?>"
                                placeholder="<?php esc_attr_e( 'https://', 'humata-chatbot' ); ?>"
                            />
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description">
            <?php esc_html_e( 'Only social icons with a URL set will be displayed.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    public function render_floating_help_footer_text_field() {
        $settings = $this->get_floating_help_settings();
        $text     = isset( $settings['footer_text'] ) ? (string) $settings['footer_text'] : '';
        ?>
        <textarea
            class="large-text"
            rows="4"
            name="humata_floating_help[footer_text]"
        ><?php echo esc_textarea( $text ); ?></textarea>
        <p class="description">
            <?php esc_html_e( 'Optional text shown at the bottom of the menu. Basic formatting and links are allowed.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    public function render_floating_help_faq_items_field() {
        $settings = $this->get_floating_help_settings();
        $items    = isset( $settings['faq_items'] ) && is_array( $settings['faq_items'] ) ? $settings['faq_items'] : array();

        if ( empty( $items ) ) {
            $items = array(
                array( 'question' => '', 'answer' => '' ),
            );
        }

        $next_index = count( $items );
        ?>
        <div class="humata-floating-help-repeater" data-humata-repeater="faq_items" data-next-index="<?php echo esc_attr( (string) $next_index ); ?>">
            <p class="description" style="margin-top: 0;">
                <?php esc_html_e( 'Drag and drop rows to reorder.', 'humata-chatbot' ); ?>
            </p>
            <table class="widefat striped" style="max-width: 900px;">
                <thead>
                    <tr>
                        <th style="width: 36px;"><span class="screen-reader-text"><?php esc_html_e( 'Order', 'humata-chatbot' ); ?></span></th>
                        <th><?php esc_html_e( 'Question', 'humata-chatbot' ); ?></th>
                        <th><?php esc_html_e( 'Answer', 'humata-chatbot' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'humata-chatbot' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $items as $i => $row ) : ?>
                        <?php
                        $row = is_array( $row ) ? $row : array();
                        $q = isset( $row['question'] ) ? (string) $row['question'] : '';
                        $a = isset( $row['answer'] ) ? (string) $row['answer'] : '';
                        ?>
                        <tr class="humata-repeater-row">
                            <td class="humata-repeater-handle-cell">
                                <span class="dashicons dashicons-move humata-repeater-handle" aria-hidden="true"></span>
                                <span class="screen-reader-text"><?php esc_html_e( 'Drag to reorder', 'humata-chatbot' ); ?></span>
                            </td>
                            <td style="width: 34%;">
                                <input
                                    type="text"
                                    class="regular-text"
                                    name="humata_floating_help[faq_items][<?php echo esc_attr( (string) $i ); ?>][question]"
                                    value="<?php echo esc_attr( $q ); ?>"
                                    placeholder="<?php esc_attr_e( 'Question', 'humata-chatbot' ); ?>"
                                />
                            </td>
                            <td>
                                <textarea
                                    class="large-text"
                                    rows="3"
                                    name="humata_floating_help[faq_items][<?php echo esc_attr( (string) $i ); ?>][answer]"
                                    placeholder="<?php esc_attr_e( 'Answer', 'humata-chatbot' ); ?>"
                                ><?php echo esc_textarea( $a ); ?></textarea>
                            </td>
                            <td style="width: 90px;">
                                <button type="button" class="button link-delete humata-repeater-remove"><?php esc_html_e( 'Remove', 'humata-chatbot' ); ?></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="margin-top: 10px;">
                <button type="button" class="button button-secondary humata-repeater-add"><?php esc_html_e( 'Add Q&A', 'humata-chatbot' ); ?></button>
            </p>
            <p class="description">
                <?php esc_html_e( 'If no FAQ items are configured, the FAQ trigger will be hidden on the frontend.', 'humata-chatbot' ); ?>
            </p>
        </div>
        <?php
    }

    public function render_floating_help_contact_html_field() {
        $settings = $this->get_floating_help_settings();
        $content  = isset( $settings['contact_html'] ) ? (string) $settings['contact_html'] : '';

        $editor_id = 'humata_floating_help_contact_html';
        $args = array(
            'textarea_name' => 'humata_floating_help[contact_html]',
            'textarea_rows' => 12,
            'media_buttons' => false,
            'teeny'         => true,
            'quicktags'     => true,
        );

        wp_editor( $content, $editor_id, $args );
        ?>
        <p class="description">
            <?php esc_html_e( 'Content shown inside the Contact modal. Links and basic formatting are supported.', 'humata-chatbot' ); ?>
        </p>
        <?php
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

    public function render_disclaimer_section() {
        echo '<p>' . esc_html__( 'Configure disclaimer text shown in the chat interface.', 'humata-chatbot' ) . '</p>';
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
        <div class="humata-document-ids-io">
            <div class="humata-document-ids-io-row">
                <span class="humata-document-ids-io-label"><?php esc_html_e( 'Export', 'humata-chatbot' ); ?></span>
                <button type="button" id="humata-document-ids-export-copy" class="button button-secondary">
                    <?php esc_html_e( 'Copy IDs', 'humata-chatbot' ); ?>
                </button>
                <button type="button" id="humata-document-ids-export-download" class="button button-secondary">
                    <?php esc_html_e( 'Download TXT', 'humata-chatbot' ); ?>
                </button>
                <button type="button" id="humata-document-ids-export-download-json" class="button button-secondary">
                    <?php esc_html_e( 'Download JSON', 'humata-chatbot' ); ?>
                </button>
            </div>

            <div class="humata-document-ids-io-row humata-document-ids-io-row-import">
                <span class="humata-document-ids-io-label"><?php esc_html_e( 'Import', 'humata-chatbot' ); ?></span>
                <fieldset class="humata-document-ids-import-mode">
                    <label>
                        <input type="radio" name="humata_document_ids_import_mode" value="merge" checked />
                        <?php esc_html_e( 'Merge', 'humata-chatbot' ); ?>
                    </label>
                    <label>
                        <input type="radio" name="humata_document_ids_import_mode" value="replace" />
                        <?php esc_html_e( 'Replace', 'humata-chatbot' ); ?>
                    </label>
                </fieldset>
                <label class="humata-document-ids-import-file-label" for="humata-document-ids-import-file">
                    <?php esc_html_e( 'File:', 'humata-chatbot' ); ?>
                </label>
                <input
                    type="file"
                    id="humata-document-ids-import-file"
                    class="humata-document-ids-import-file"
                    accept=".txt,.csv,.json,text/plain,application/json,text/csv"
                />
            </div>

            <textarea
                id="humata-document-ids-import-text"
                class="large-text code"
                rows="4"
                placeholder="<?php esc_attr_e( 'Paste document IDs here (one per line, comma-separated, CSV, JSON, URLs, etc.)', 'humata-chatbot' ); ?>"
            ></textarea>

            <div class="humata-document-ids-io-row humata-document-ids-io-actions">
                <button type="button" id="humata-document-ids-import-apply" class="button button-primary">
                    <?php esc_html_e( 'Apply Import', 'humata-chatbot' ); ?>
                </button>
                <button type="button" id="humata-document-ids-clear-all" class="button button-secondary">
                    <?php esc_html_e( 'Clear All', 'humata-chatbot' ); ?>
                </button>
                <span id="humata-document-ids-io-status" class="humata-document-ids-io-status" aria-live="polite"></span>
            </div>

            <p class="description">
                <?php esc_html_e( 'Tip: valid UUIDs will be extracted and deduplicated. Click “Save Settings” to persist changes.', 'humata-chatbot' ); ?>
            </p>
        </div>
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
     * Render avatar settings section description.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_avatar_section() {
        echo '<p>' . esc_html__( 'Customize the avatar images displayed for user and bot messages. Upload square images (e.g., 512×512) for best results.', 'humata-chatbot' ) . '</p>';
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
