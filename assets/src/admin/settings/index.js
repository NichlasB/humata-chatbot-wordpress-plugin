/* global jQuery, wp */

(function($) {
    'use strict';

    $(function() {
        var config = window.humataAdminConfig || {};
        var humataAdminNonce = String(config.nonce || '');

        // Back-compat: the previous inline scripts used `window.humataDocumentTitles`.
        if (config.documentTitles && typeof config.documentTitles === 'object') {
            window.humataDocumentTitles = config.documentTitles;
        } else if (!window.humataDocumentTitles || typeof window.humataDocumentTitles !== 'object') {
            window.humataDocumentTitles = {};
        }

        // Prefer configured ajax URL, fallback to WP admin global.
        var ajaxurl = String(config.ajaxUrl || window.ajaxurl || '');

        // Shared utility: safely parse integer with fallback.
        function humataSafeInt(value, fallback) {
            var n = parseInt(value, 10);
            return (isFinite(n) && n >= 0) ? n : (fallback || 0);
        }

        // ---------------------------
        // Document IDs UI + API tests
        // ---------------------------

        (function() {
            var humataTitles = window.humataDocumentTitles || {};
            var humataTitleNotFetchedText = (config.i18n && typeof config.i18n.titleNotFetchedText === 'string')
                ? config.i18n.titleNotFetchedText
                : "(title not fetched)";
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

                // Fallback: prompt user to manually copy since clipboard API is unavailable
                return humataPromptManualCopy(text);
            }

            function humataPromptManualCopy(text) {
                return new Promise(function(resolve, reject) {
                    var isMac = /Mac|iPhone|iPad|iPod/i.test(navigator.platform || navigator.userAgent);
                    var shortcut = isMac ? "Cmd+C" : "Ctrl+C";
                    var message = "Press " + shortcut + " to copy";

                    // Show toast notification
                    humataShowCopyToast(message);

                    var $temp = $("<textarea></textarea>")
                        .attr("readonly", "")
                        .attr("aria-label", "Text to copy")
                        .css({
                            position: "fixed",
                            top: "50%",
                            left: "50%",
                            transform: "translate(-50%, -50%)",
                            width: "1px",
                            height: "1px",
                            padding: "0",
                            border: "none",
                            outline: "none",
                            opacity: "0",
                            zIndex: "999999"
                        })
                        .val(text || "");

                    $("body").append($temp);
                    $temp[0].focus();
                    $temp[0].select();
                    $temp[0].setSelectionRange(0, $temp[0].value.length);

                    function handleCopy() {
                        cleanup();
                        resolve();
                    }

                    function handleKeydown(e) {
                        if (e.key === "Escape") {
                            cleanup();
                            reject(new Error("copy_cancelled"));
                        }
                    }

                    function cleanup() {
                        $(document).off("copy", handleCopy);
                        $(document).off("keydown", handleKeydown);
                        $temp.remove();
                        humataHideCopyToast();
                    }

                    $(document).on("copy", handleCopy);
                    $(document).on("keydown", handleKeydown);

                    // Auto-cleanup after 10 seconds
                    setTimeout(function() {
                        cleanup();
                        reject(new Error("copy_timeout"));
                    }, 10000);
                });
            }

            function humataShowCopyToast(message) {
                humataHideCopyToast();
                var $toast = $("<div></div>")
                    .attr("id", "humata-admin-copy-toast")
                    .text(message)
                    .css({
                        position: "fixed",
                        top: "20px",
                        left: "50%",
                        transform: "translateX(-50%)",
                        background: "#333",
                        color: "#fff",
                        padding: "12px 24px",
                        borderRadius: "8px",
                        fontSize: "14px",
                        zIndex: "999999",
                        boxShadow: "0 4px 12px rgba(0,0,0,0.3)"
                    });
                $("body").append($toast);
            }

            function humataHideCopyToast() {
                $("#humata-admin-copy-toast").remove();
            }

            function humataGetImportMode() {
                var $checked = $("input[name=\"humata_document_ids_import_mode\"]:checked");
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
                    nonce: humataAdminNonce
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
                    nonce: humataAdminNonce
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
                    nonce: humataAdminNonce
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
                        nonce: humataAdminNonce,
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
        })();

        // ---------------------------
        // Search provider toggle (General tab)
        // ---------------------------

        (function() {
            function humataGetSearchProvider() {
                var $selected = $("input[name=\"humata_search_provider\"]:checked");
                var val = $selected.length ? String($selected.val() || "") : "";
                val = $.trim(val);
                if (!val) {
                    val = "humata";
                }
                return val;
            }

            function humataToggleSearchProviderSections() {
                // Only run on pages that have the search provider radio buttons (General tab)
                if (!$("input[name=\"humata_search_provider\"]").length) {
                    return;
                }

                var provider = humataGetSearchProvider();
                var isHumata = provider === "humata";
                var isLocal = provider === "local";

                // Humata API sections (API config, Humata system prompt, Second-stage prompt)
                $("#humata_api_key").closest("table").closest("tbody").parent().closest("table").prev("h2:contains('Humata API')").toggle(isHumata);
                
                // Find sections by their heading text and toggle visibility
                $(".form-table").each(function() {
                    var $table = $(this);
                    var $heading = $table.prev("h2, p").prev("h2");
                    if (!$heading.length) {
                        $heading = $table.prev("h2");
                    }
                    var headingText = $heading.text() || "";

                    // Humata-specific sections (exclude Local Search sections)
                    if ((headingText.indexOf("Humata API") !== -1 ||
                        headingText.indexOf("Humata System Prompt") !== -1 ||
                        headingText.indexOf("Second-Stage LLM System Prompt") !== -1) &&
                        headingText.indexOf("Local Search") === -1) {
                        $heading.toggle(isHumata);
                        $heading.next("p").toggle(isHumata);
                        $table.toggle(isHumata);
                    }

                    // Local Search sections (first-stage and second-stage prompts)
                    if (headingText.indexOf("Local Search") !== -1) {
                        $heading.toggle(isLocal);
                        $heading.next("p").toggle(isLocal);
                        $table.toggle(isLocal);
                    }
                });
            }

            $(document).on("change", "input[name=\"humata_search_provider\"]", humataToggleSearchProviderSections);
            // Run on page load
            setTimeout(humataToggleSearchProviderSections, 100);
        })();

        // ---------------------------
        // Second-stage provider toggle (Humata Mode)
        // ---------------------------

        (function() {
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
                var showOpenRouter = provider === "openrouter";

                $(".humata-api-key-pool[data-option='humata_straico_api_key']").closest("tr").toggle(showStraico);
                $("#humata_straico_model").closest("tr").toggle(showStraico);

                $(".humata-api-key-pool[data-option='humata_anthropic_api_key']").closest("tr").toggle(showAnthropic);
                $("#humata_anthropic_model").closest("tr").toggle(showAnthropic);
                $("#humata_anthropic_extended_thinking").closest("tr").toggle(showAnthropic);

                $(".humata-api-key-pool[data-option='humata_openrouter_api_key']").closest("tr").toggle(showOpenRouter);
                $("#humata_openrouter_model").closest("tr").toggle(showOpenRouter);
            }

            $(document).on("change", "input[name=\"humata_second_llm_provider\"]", humataToggleSecondLlmFields);
            humataToggleSecondLlmFields();
        })();

        // ---------------------------
        // Local Search first-stage provider toggle
        // ---------------------------

        (function() {
            function humataGetLocalFirstProvider() {
                var $selected = $("input[name=\"humata_local_first_llm_provider\"]:checked");
                var val = $selected.length ? String($selected.val() || "") : "";
                val = $.trim(val);
                if (!val) {
                    val = "straico";
                }
                return val;
            }

            function humataToggleLocalFirstFields() {
                var provider = humataGetLocalFirstProvider();
                var showStraico = provider === "straico";
                var showAnthropic = provider === "anthropic";
                var showOpenRouter = provider === "openrouter";

                $(".humata-api-key-pool[data-option='humata_local_first_straico_api_key']").closest("tr").toggle(showStraico);
                $("#humata_local_first_straico_model").closest("tr").toggle(showStraico);

                $(".humata-api-key-pool[data-option='humata_local_first_anthropic_api_key']").closest("tr").toggle(showAnthropic);
                $("#humata_local_first_anthropic_model").closest("tr").toggle(showAnthropic);
                $("#humata_local_first_anthropic_extended_thinking").closest("tr").toggle(showAnthropic);

                $(".humata-api-key-pool[data-option='humata_local_first_openrouter_api_key']").closest("tr").toggle(showOpenRouter);
                $("#humata_local_first_openrouter_model").closest("tr").toggle(showOpenRouter);
            }

            $(document).on("change", "input[name=\"humata_local_first_llm_provider\"]", humataToggleLocalFirstFields);
            humataToggleLocalFirstFields();
        })();

        // ---------------------------
        // Local Search second-stage provider toggle
        // ---------------------------

        (function() {
            function humataGetLocalSecondProvider() {
                var $selected = $("input[name=\"humata_local_second_llm_provider\"]:checked");
                var val = $selected.length ? String($selected.val() || "") : "";
                val = $.trim(val);
                if (!val) {
                    val = "none";
                }
                return val;
            }

            function humataToggleLocalSecondFields() {
                var provider = humataGetLocalSecondProvider();
                var showStraico = provider === "straico";
                var showAnthropic = provider === "anthropic";
                var showOpenRouter = provider === "openrouter";

                $(".humata-api-key-pool[data-option='humata_local_second_straico_api_key']").closest("tr").toggle(showStraico);
                $("#humata_local_second_straico_model").closest("tr").toggle(showStraico);

                $(".humata-api-key-pool[data-option='humata_local_second_anthropic_api_key']").closest("tr").toggle(showAnthropic);
                $("#humata_local_second_anthropic_model").closest("tr").toggle(showAnthropic);
                $("#humata_local_second_anthropic_extended_thinking").closest("tr").toggle(showAnthropic);

                $(".humata-api-key-pool[data-option='humata_local_second_openrouter_api_key']").closest("tr").toggle(showOpenRouter);
                $("#humata_local_second_openrouter_model").closest("tr").toggle(showOpenRouter);
            }

            $(document).on("change", "input[name=\"humata_local_second_llm_provider\"]", humataToggleLocalSecondFields);
            humataToggleLocalSecondFields();
        })();

        // -----------------------------------------------
        // Floating Help + Auto Links + Intent Links helpers
        // -----------------------------------------------

        (function() {
            function humataBuildExternalLinkRow(idx) {
                return "" +
                    "<tr class=\"humata-repeater-row\">" +
                        "<td class=\"humata-repeater-handle-cell\">" +
                            "<span class=\"dashicons dashicons-move humata-repeater-handle\" aria-hidden=\"true\"></span>" +
                            "<span class=\"screen-reader-text\">Drag to reorder</span>" +
                        "</td>" +
                        "<td style=\"width: 28%\">" +
                            "<input type=\"text\" class=\"regular-text\" name=\"humata_floating_help[external_links][" + idx + "][label]\" value=\"\" placeholder=\"Label\">" +
                        "</td>" +
                        "<td>" +
                            "<input type=\"url\" class=\"regular-text\" name=\"humata_floating_help[external_links][" + idx + "][url]\" value=\"\" placeholder=\"https://example.com/\">" +
                        "</td>" +
                        "<td style=\"width: 90px\">" +
                            "<button type=\"button\" class=\"button link-delete humata-repeater-remove\">Remove</button>" +
                        "</td>" +
                    "</tr>";
            }

            function humataBuildAutoLinkRow(idx) {
                return "" +
                    "<tr class=\"humata-repeater-row\">" +
                        "<td class=\"humata-repeater-handle-cell\">" +
                            "<span class=\"dashicons dashicons-move humata-repeater-handle\" aria-hidden=\"true\"></span>" +
                            "<span class=\"screen-reader-text\">Drag to reorder</span>" +
                        "</td>" +
                        "<td style=\"width: 38%\">" +
                            "<input type=\"text\" class=\"regular-text\" name=\"humata_auto_links[" + idx + "][phrase]\" value=\"\" placeholder=\"Phrase (exact match)\">" +
                        "</td>" +
                        "<td>" +
                            "<input type=\"url\" class=\"regular-text\" name=\"humata_auto_links[" + idx + "][url]\" value=\"\" placeholder=\"https://example.com/\">" +
                        "</td>" +
                        "<td style=\"width: 90px\">" +
                            "<button type=\"button\" class=\"button link-delete humata-repeater-remove\">Remove</button>" +
                        "</td>" +
                    "</tr>";
            }

            function humataBuildFaqRow(idx) {
                return "" +
                    "<tr class=\"humata-repeater-row\">" +
                        "<td class=\"humata-repeater-handle-cell\">" +
                            "<span class=\"dashicons dashicons-move humata-repeater-handle\" aria-hidden=\"true\"></span>" +
                            "<span class=\"screen-reader-text\">Drag to reorder</span>" +
                        "</td>" +
                        "<td style=\"width: 34%\">" +
                            "<input type=\"text\" class=\"regular-text\" name=\"humata_floating_help[faq_items][" + idx + "][question]\" value=\"\" placeholder=\"Question\">" +
                        "</td>" +
                        "<td>" +
                            "<textarea class=\"large-text\" rows=\"3\" name=\"humata_floating_help[faq_items][" + idx + "][answer]\" placeholder=\"Answer\"></textarea>" +
                        "</td>" +
                        "<td style=\"width: 90px\">" +
                            "<button type=\"button\" class=\"button link-delete humata-repeater-remove\">Remove</button>" +
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

            // Avatar/Logo uploader handlers.
            $(document).on("click", ".humata-upload-avatar", function(e) {
                e.preventDefault();
                var targetId = $(this).data("target");
                var isLogo = targetId.indexOf("logo") !== -1;
                var title = isLogo ? "Select Logo Image" : "Select Avatar Image";
                var frame = wp.media({
                    title: title,
                    button: { text: "Use this image" },
                    multiple: false,
                    library: { type: ["image/jpeg", "image/png", "image/gif", "image/webp"] }
                });
                frame.on("select", function() {
                    var attachment = frame.state().get("selection").first().toJSON();
                    $("#" + targetId).val(attachment.url);
                    var imgHtml = isLogo
                        ? "<img src=\"" + attachment.url + "\" alt=\"Logo Preview\" />"
                        : "<img src=\"" + attachment.url + "\" style=\"max-width:64px;max-height:64px;border-radius:6px;\" />";
                    $("#" + targetId + "_preview").html(imgHtml);
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

            // Intent-Based Links repeater handlers.
            function humataBuildIntentLinkRow(intentIdx, linkIdx) {
                return "" +
                    "<tr class=\"humata-intent-link-row\">" +
                        "<td>" +
                            "<input type=\"text\" class=\"regular-text\" name=\"humata_intent_links[" + intentIdx + "][links][" + linkIdx + "][title]\" value=\"\" placeholder=\"Shipping Policy\">" +
                        "</td>" +
                        "<td>" +
                            "<input type=\"url\" class=\"regular-text\" name=\"humata_intent_links[" + intentIdx + "][links][" + linkIdx + "][url]\" value=\"\" placeholder=\"https://example.com/shipping\">" +
                        "</td>" +
                        "<td>" +
                            "<button type=\"button\" class=\"button link-delete humata-intent-link-remove\">Remove</button>" +
                        "</td>" +
                    "</tr>";
            }

            function humataBuildAccordionRow(intentIdx, accIdx) {
                return "" +
                    "<tr class=\"humata-intent-accordion-row\">" +
                        "<td>" +
                            "<input type=\"text\" class=\"regular-text\" name=\"humata_intent_links[" + intentIdx + "][accordions][" + accIdx + "][title]\" value=\"\" placeholder=\"e.g., Shipping Restrictions\">" +
                        "</td>" +
                        "<td>" +
                            "<div class=\"humata-accordion-toolbar\">" +
                                "<button type=\"button\" class=\"button button-small humata-format-bold\" title=\"Bold\"><strong>B</strong></button>" +
                                "<button type=\"button\" class=\"button button-small humata-format-italic\" title=\"Italic\"><em>I</em></button>" +
                                "<button type=\"button\" class=\"button button-small humata-format-link\" title=\"Insert Link\">🔗</button>" +
                            "</div>" +
                            "<textarea class=\"large-text humata-accordion-content-input\" rows=\"3\" name=\"humata_intent_links[" + intentIdx + "][accordions][" + accIdx + "][content]\" placeholder=\"Content shown when expanded... (supports formatting)\" maxlength=\"1000\"></textarea>" +
                        "</td>" +
                        "<td>" +
                            "<button type=\"button\" class=\"button link-delete humata-intent-accordion-remove\">Remove</button>" +
                        "</td>" +
                    "</tr>";
            }

            function humataBuildIntentCard(intentIdx) {
                return "" +
                    "<div class=\"humata-intent-card\" data-intent-index=\"" + intentIdx + "\">" +
                        "<div class=\"humata-intent-card-header\">" +
                            "<button type=\"button\" class=\"humata-intent-toggle\" aria-expanded=\"true\" aria-label=\"Toggle intent\">" +
                                "<span class=\"dashicons dashicons-arrow-down-alt2\"></span>" +
                            "</button>" +
                            "<span class=\"humata-intent-card-title\">Intent #" + (intentIdx + 1) + "</span>" +
                            "<button type=\"button\" class=\"button link-delete humata-intent-remove\">Remove Intent</button>" +
                        "</div>" +
                        "<div class=\"humata-intent-card-body\">" +
                            "<p>" +
                                "<label><strong>Intent Name</strong></label><br>" +
                                "<input type=\"text\" class=\"regular-text\" name=\"humata_intent_links[" + intentIdx + "][intent_name]\" value=\"\" placeholder=\"e.g., shipping_intent\">" +
                                " <span class=\"description\">Internal label (not shown to users).</span>" +
                            "</p>" +
                            "<p>" +
                                "<label><strong>Keywords</strong></label><br>" +
                                "<textarea class=\"large-text\" rows=\"2\" name=\"humata_intent_links[" + intentIdx + "][keywords]\" placeholder=\"shipping, ship, delivery, deliver, track, package\"></textarea>" +
                                " <span class=\"description\">Comma-separated keywords. If any keyword appears in the user's question, the links below are shown.</span>" +
                            "</p>" +
                            "<div class=\"humata-intent-links-sub\" data-links-next-index=\"1\">" +
                                "<label><strong>Resource Links</strong></label>" +
                                "<table class=\"widefat striped humata-intent-links-table\" style=\"max-width: 800px; margin-top: 6px;\">" +
                                    "<thead>" +
                                        "<tr>" +
                                            "<th>Title</th>" +
                                            "<th>URL</th>" +
                                            "<th style=\"width: 80px;\">Actions</th>" +
                                        "</tr>" +
                                    "</thead>" +
                                    "<tbody>" +
                                        "<tr class=\"humata-intent-link-row\">" +
                                            "<td>" +
                                                "<input type=\"text\" class=\"regular-text\" name=\"humata_intent_links[" + intentIdx + "][links][0][title]\" value=\"\" placeholder=\"Shipping Policy\">" +
                                            "</td>" +
                                            "<td>" +
                                                "<input type=\"url\" class=\"regular-text\" name=\"humata_intent_links[" + intentIdx + "][links][0][url]\" value=\"\" placeholder=\"https://example.com/shipping\">" +
                                            "</td>" +
                                            "<td>" +
                                                "<button type=\"button\" class=\"button link-delete humata-intent-link-remove\">Remove</button>" +
                                            "</td>" +
                                        "</tr>" +
                                    "</tbody>" +
                                "</table>" +
                                "<p style=\"margin-top: 8px;\">" +
                                    "<button type=\"button\" class=\"button button-secondary humata-intent-link-add\">Add Link</button>" +
                                "</p>" +
                            "</div>" +
                            "<div class=\"humata-intent-accordions-sub\" data-accordions-next-index=\"1\" style=\"margin-top: 20px;\">" +
                                "<label><strong>Custom Accordions</strong></label>" +
                                "<span class=\"description\" style=\"display: block; margin-bottom: 6px;\">FAQ-style collapsible toggles shown in the chat response.</span>" +
                                "<table class=\"widefat striped humata-intent-accordions-table\" style=\"max-width: 800px; margin-top: 6px;\">" +
                                    "<thead>" +
                                        "<tr>" +
                                            "<th style=\"width: 200px;\">Title</th>" +
                                            "<th>Content</th>" +
                                            "<th style=\"width: 80px;\">Actions</th>" +
                                        "</tr>" +
                                    "</thead>" +
                                    "<tbody>" +
                                        "<tr class=\"humata-intent-accordion-row\">" +
                                            "<td>" +
                                                "<input type=\"text\" class=\"regular-text\" name=\"humata_intent_links[" + intentIdx + "][accordions][0][title]\" value=\"\" placeholder=\"e.g., Shipping Restrictions\">" +
                                            "</td>" +
                                            "<td>" +
                                                "<div class=\"humata-accordion-toolbar\">" +
                                                    "<button type=\"button\" class=\"button button-small humata-format-bold\" title=\"Bold\"><strong>B</strong></button>" +
                                                    "<button type=\"button\" class=\"button button-small humata-format-italic\" title=\"Italic\"><em>I</em></button>" +
                                                    "<button type=\"button\" class=\"button button-small humata-format-link\" title=\"Insert Link\">🔗</button>" +
                                                "</div>" +
                                                "<textarea class=\"large-text humata-accordion-content-input\" rows=\"3\" name=\"humata_intent_links[" + intentIdx + "][accordions][0][content]\" placeholder=\"Content shown when expanded... (supports formatting)\" maxlength=\"1000\"></textarea>" +
                                            "</td>" +
                                            "<td>" +
                                                "<button type=\"button\" class=\"button link-delete humata-intent-accordion-remove\">Remove</button>" +
                                            "</td>" +
                                        "</tr>" +
                                    "</tbody>" +
                                "</table>" +
                                "<p style=\"margin-top: 8px;\">" +
                                    "<button type=\"button\" class=\"button button-secondary humata-intent-accordion-add\">Add Accordion</button>" +
                                "</p>" +
                            "</div>" +
                        "</div>" +
                    "</div>";
            }

            // Add new intent
            $(document).on("click", ".humata-intent-add", function() {
                var $repeater = $(this).closest(".humata-intent-links-repeater");
                var $container = $repeater.find(".humata-intent-links-container");
                var nextIdx = humataSafeInt($repeater.attr("data-next-index"), $container.find(".humata-intent-card").length);

                $container.append(humataBuildIntentCard(nextIdx));
                $repeater.attr("data-next-index", String(nextIdx + 1));
            });

            // Remove intent
            $(document).on("click", ".humata-intent-remove", function() {
                $(this).closest(".humata-intent-card").remove();
            });

            // Add link within an intent
            $(document).on("click", ".humata-intent-link-add", function() {
                var $sub = $(this).closest(".humata-intent-links-sub");
                var $tbody = $sub.find("tbody");
                var $card = $(this).closest(".humata-intent-card");
                var intentIdx = humataSafeInt($card.attr("data-intent-index"), 0);
                var linkIdx = humataSafeInt($sub.attr("data-links-next-index"), $tbody.find("tr").length);

                $tbody.append(humataBuildIntentLinkRow(intentIdx, linkIdx));
                $sub.attr("data-links-next-index", String(linkIdx + 1));
            });

            // Remove link within an intent
            $(document).on("click", ".humata-intent-link-remove", function() {
                var $tbody = $(this).closest("tbody");
                $(this).closest("tr").remove();
                // Ensure at least one row remains
                if ($tbody.find("tr").length === 0) {
                    var $card = $tbody.closest(".humata-intent-card");
                    var $sub = $tbody.closest(".humata-intent-links-sub");
                    var intentIdx = humataSafeInt($card.attr("data-intent-index"), 0);
                    $tbody.append(humataBuildIntentLinkRow(intentIdx, 0));
                    $sub.attr("data-links-next-index", "1");
                }
            });

            // Add accordion within an intent
            $(document).on("click", ".humata-intent-accordion-add", function() {
                var $sub = $(this).closest(".humata-intent-accordions-sub");
                var $tbody = $sub.find("tbody");
                var $card = $(this).closest(".humata-intent-card");
                var intentIdx = humataSafeInt($card.attr("data-intent-index"), 0);
                var accIdx = humataSafeInt($sub.attr("data-accordions-next-index"), $tbody.find("tr").length);

                // Limit to 5 accordions per intent
                if ($tbody.find("tr").length >= 5) {
                    return;
                }

                $tbody.append(humataBuildAccordionRow(intentIdx, accIdx));
                $sub.attr("data-accordions-next-index", String(accIdx + 1));
            });

            // Remove accordion within an intent
            $(document).on("click", ".humata-intent-accordion-remove", function() {
                var $tbody = $(this).closest("tbody");
                $(this).closest("tr").remove();
                // Ensure at least one row remains
                if ($tbody.find("tr").length === 0) {
                    var $card = $tbody.closest(".humata-intent-card");
                    var $sub = $tbody.closest(".humata-intent-accordions-sub");
                    var intentIdx = humataSafeInt($card.attr("data-intent-index"), 0);
                    $tbody.append(humataBuildAccordionRow(intentIdx, 0));
                    $sub.attr("data-accordions-next-index", "1");
                }
            });

            // Accordion content formatting toolbar
            function humataWrapSelection(textarea, before, after) {
                var start = textarea.selectionStart;
                var end = textarea.selectionEnd;
                var text = textarea.value;
                var selected = text.substring(start, end);

                if (!selected) {
                    selected = "text";
                }

                var newText = text.substring(0, start) + before + selected + after + text.substring(end);
                textarea.value = newText;
                textarea.focus();
                textarea.setSelectionRange(start + before.length, start + before.length + selected.length);
            }

            // Format selected text with bold
            $(document).on("click", ".humata-format-bold", function() {
                var $textarea = $(this).closest("td").find("textarea");
                if ($textarea.length) {
                    humataWrapSelection($textarea[0], "<strong>", "</strong>");
                }
            });

            // Format selected text with italic
            $(document).on("click", ".humata-format-italic", function() {
                var $textarea = $(this).closest("td").find("textarea");
                if ($textarea.length) {
                    humataWrapSelection($textarea[0], "<em>", "</em>");
                }
            });

            // Insert link around selected text
            $(document).on("click", ".humata-format-link", function() {
                var $textarea = $(this).closest("td").find("textarea");
                if ($textarea.length) {
                    var url = prompt("Enter URL:", "https://");
                    if (url && url !== "https://") {
                        humataWrapSelection($textarea[0], "<a href=\"" + url + "\" target=\"_blank\">", "</a>");
                    }
                }
            });

            // -----------------------------------------------
            // Trigger Pages repeater handlers
            // -----------------------------------------------

            function humataInitTriggerPagesSortable() {
                var $list = $(".humata-trigger-pages-list");
                if (!$list.length || !$list.sortable) return;
                if ($list.data("humataSortableInit")) return;

                $list.sortable({
                    axis: "y",
                    items: "> .humata-trigger-page-item",
                    handle: ".humata-repeater-handle",
                    placeholder: "humata-repeater-placeholder",
                    forcePlaceholderSize: true,
                    tolerance: "pointer",
                    update: function() {
                        // Reindex the form fields after reordering
                        humataReindexTriggerPages();
                    }
                });

                try { $list.disableSelection(); } catch (e) {}
                $list.data("humataSortableInit", true);
            }

            function humataReindexTriggerPages() {
                $(".humata-trigger-pages-list .humata-trigger-page-item").each(function(idx) {
                    var $item = $(this);
                    // Update input names
                    $item.find("input[name*=\"humata_trigger_pages\"]").each(function() {
                        var name = $(this).attr("name");
                        name = name.replace(/humata_trigger_pages\[\d+\]/, "humata_trigger_pages[" + idx + "]");
                        $(this).attr("name", name);
                    });
                    // Update textarea names (for wp_editor fallback)
                    $item.find("textarea[name*=\"humata_trigger_pages\"]").each(function() {
                        var name = $(this).attr("name");
                        name = name.replace(/humata_trigger_pages\[\d+\]/, "humata_trigger_pages[" + idx + "]");
                        $(this).attr("name", name);
                    });
                });
                // Update next index
                var $repeater = $(".humata-trigger-pages-repeater");
                $repeater.attr("data-next-index", String($(".humata-trigger-pages-list .humata-trigger-page-item").length));
            }

            // Remove trigger page
            $(document).on("click", ".humata-trigger-pages-repeater .humata-repeater-remove", function() {
                var $item = $(this).closest(".humata-trigger-page-item");
                var editorId = $item.find(".wp-editor-area").attr("id");

                // Remove TinyMCE instance if exists
                if (editorId && typeof tinymce !== "undefined" && tinymce.get(editorId)) {
                    tinymce.get(editorId).remove();
                }

                $item.remove();
                humataReindexTriggerPages();
            });

            // Add trigger page - scroll to the last empty row since one is always available
            $(document).on("click", ".humata-trigger-pages-add", function() {
                var $lastItem = $(".humata-trigger-pages-list .humata-trigger-page-item").last();
                if ($lastItem.length) {
                    $lastItem[0].scrollIntoView({ behavior: "smooth", block: "center" });
                    $lastItem.find("input[type=\"text\"]").first().focus();
                }
            });

            // Initialize sortable for trigger pages
            humataInitTriggerPagesSortable();

            // Intent collapse state persistence
            var INTENT_COLLAPSE_KEY = "humata_intent_collapsed";

            function humataGetCollapsedIntents() {
                try {
                    var stored = localStorage.getItem(INTENT_COLLAPSE_KEY);
                    if (stored) {
                        var parsed = JSON.parse(stored);
                        if (Array.isArray(parsed)) {
                            return parsed;
                        }
                    }
                } catch (e) {}
                return [];
            }

            function humataSaveCollapsedIntents(indices) {
                try {
                    localStorage.setItem(INTENT_COLLAPSE_KEY, JSON.stringify(indices));
                } catch (e) {}
            }

            function humataCollapseCard($card) {
                var $btn = $card.find(".humata-intent-toggle");
                $card.addClass("humata-intent-collapsed");
                $btn.attr("aria-expanded", "false");
                $btn.find(".dashicons").removeClass("dashicons-arrow-down-alt2").addClass("dashicons-arrow-right-alt2");
            }

            function humataExpandCard($card) {
                var $btn = $card.find(".humata-intent-toggle");
                $card.removeClass("humata-intent-collapsed");
                $btn.attr("aria-expanded", "true");
                $btn.find(".dashicons").removeClass("dashicons-arrow-right-alt2").addClass("dashicons-arrow-down-alt2");
            }

            // Restore collapsed state on page load
            (function() {
                var collapsed = humataGetCollapsedIntents();
                if (!collapsed.length) return;

                $(".humata-intent-card").each(function() {
                    var idx = humataSafeInt($(this).attr("data-intent-index"), -1);
                    if (idx >= 0 && collapsed.indexOf(idx) !== -1) {
                        humataCollapseCard($(this));
                    }
                });
            })();

            // Toggle intent card collapse/expand
            $(document).on("click", ".humata-intent-toggle", function() {
                var $btn = $(this);
                var $card = $btn.closest(".humata-intent-card");
                var isExpanded = $btn.attr("aria-expanded") === "true";
                var idx = humataSafeInt($card.attr("data-intent-index"), -1);

                if (isExpanded) {
                    humataCollapseCard($card);
                } else {
                    humataExpandCard($card);
                }

                // Persist to localStorage
                var collapsed = humataGetCollapsedIntents();
                var pos = collapsed.indexOf(idx);
                if (isExpanded && pos === -1) {
                    collapsed.push(idx);
                } else if (!isExpanded && pos !== -1) {
                    collapsed.splice(pos, 1);
                }
                humataSaveCollapsedIntents(collapsed);
            });
        })();

        // ------------------------------------------
        // Suggested Questions Tab Handlers
        // ------------------------------------------
        (function() {
            // Mode toggle: show/hide sections based on selected mode
            function humataUpdateSqSections() {
                var mode = $('input[name="humata_suggested_questions[mode]"]:checked').val() || 'fixed';
                var $fixedSection = $('#humata_sq_fixed_questions').closest('tr').parent().closest('table').prev('h2').addBack().add($('#humata_sq_fixed_questions').closest('tr'));
                var $randomizedSection = $('#humata_sq_randomized_categories').closest('tr').parent().closest('table').prev('h2').addBack().add($('#humata_sq_randomized_categories').closest('tr'));

                // Simple approach: toggle visibility of the section wrappers
                $('.humata-sq-fixed-section').closest('tr').toggle(mode === 'fixed');
                $('.humata-sq-randomized-section').closest('tr').toggle(mode === 'randomized');
            }

            $(document).on('change', '.humata-sq-mode-radio', humataUpdateSqSections);
            humataUpdateSqSections();

            // Fixed mode: Add question
            $(document).on('click', '.humata-sq-fixed-add', function() {
                var $section = $(this).closest('.humata-sq-fixed-section');
                var $tbody = $section.find('tbody');
                var rowCount = $tbody.find('tr').length;

                // Max 4 questions
                if (rowCount >= 4) {
                    return;
                }

                var nextIdx = humataSafeInt($section.attr('data-next-index'), rowCount);
                var html = '' +
                    '<tr class="humata-repeater-row humata-sq-fixed-row">' +
                        '<td class="humata-repeater-handle-cell">' +
                            '<span class="dashicons dashicons-move humata-repeater-handle" aria-hidden="true"></span>' +
                            '<span class="screen-reader-text">Drag to reorder</span>' +
                        '</td>' +
                        '<td>' +
                            '<input type="text" class="large-text" name="humata_suggested_questions[fixed_questions][' + nextIdx + '][text]" value="" placeholder="e.g., What are your business hours?" maxlength="150">' +
                        '</td>' +
                        '<td>' +
                            '<button type="button" class="button link-delete humata-repeater-remove">Remove</button>' +
                        '</td>' +
                    '</tr>';

                $tbody.append(html);
                $section.attr('data-next-index', String(nextIdx + 1));
            });

            // Fixed mode: Remove question
            $(document).on('click', '.humata-sq-fixed-section .humata-repeater-remove', function() {
                $(this).closest('tr').remove();
            });

            // Fixed mode: Sortable
            if ($.fn.sortable) {
                $('.humata-sq-fixed-section tbody').sortable({
                    handle: '.humata-repeater-handle',
                    axis: 'y',
                    cursor: 'move',
                    opacity: 0.7
                });
            }

            // Randomized mode: Add category
            $(document).on('click', '.humata-sq-category-add', function() {
                var $section = $(this).closest('.humata-sq-randomized-section');
                var $container = $section.find('.humata-sq-categories-container');
                var catCount = $container.find('.humata-sq-category-card').length;

                // Max 4 categories
                if (catCount >= 4) {
                    return;
                }

                var nextIdx = humataSafeInt($section.attr('data-next-cat-index'), catCount);
                var html = humataBuildSqCategoryCard(nextIdx);

                $container.append(html);
                $section.attr('data-next-cat-index', String(nextIdx + 1));
            });

            // Randomized mode: Remove category
            $(document).on('click', '.humata-sq-category-remove', function() {
                $(this).closest('.humata-sq-category-card').remove();
            });

            // Randomized mode: Add question within category
            $(document).on('click', '.humata-sq-question-add', function() {
                var $sub = $(this).closest('.humata-sq-questions-sub');
                var $tbody = $sub.find('tbody');
                var $card = $(this).closest('.humata-sq-category-card');
                var catIdx = humataSafeInt($card.attr('data-cat-index'), 0);
                var qIdx = humataSafeInt($sub.attr('data-next-q-index'), $tbody.find('tr').length);

                // Max 20 questions per category
                if ($tbody.find('tr').length >= 20) {
                    return;
                }

                var html = humataBuildSqQuestionRow(catIdx, qIdx);
                $tbody.append(html);
                $sub.attr('data-next-q-index', String(qIdx + 1));
            });

            // Randomized mode: Remove question within category
            $(document).on('click', '.humata-sq-question-remove', function() {
                var $tbody = $(this).closest('tbody');
                $(this).closest('tr').remove();

                // Ensure at least one row remains
                if ($tbody.find('tr').length === 0) {
                    var $card = $tbody.closest('.humata-sq-category-card');
                    var $sub = $tbody.closest('.humata-sq-questions-sub');
                    var catIdx = humataSafeInt($card.attr('data-cat-index'), 0);
                    $tbody.append(humataBuildSqQuestionRow(catIdx, 0));
                    $sub.attr('data-next-q-index', '1');
                }
            });

            // Randomized mode: Toggle category collapse/expand
            $(document).on('click', '.humata-sq-category-toggle', function() {
                var $btn = $(this);
                var $card = $btn.closest('.humata-sq-category-card');
                var isExpanded = $btn.attr('aria-expanded') === 'true';

                if (isExpanded) {
                    $card.addClass('humata-sq-category-collapsed');
                    $btn.attr('aria-expanded', 'false');
                    $btn.find('.dashicons').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
                } else {
                    $card.removeClass('humata-sq-category-collapsed');
                    $btn.attr('aria-expanded', 'true');
                    $btn.find('.dashicons').removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2');
                }
            });

            function humataBuildSqQuestionRow(catIdx, qIdx) {
                return '' +
                    '<tr class="humata-sq-question-row">' +
                        '<td>' +
                            '<input type="text" class="large-text" name="humata_suggested_questions[categories][' + catIdx + '][questions][' + qIdx + ']" value="" placeholder="e.g., How do I track my order?" maxlength="150">' +
                        '</td>' +
                        '<td>' +
                            '<button type="button" class="button link-delete humata-sq-question-remove">Remove</button>' +
                        '</td>' +
                    '</tr>';
            }

            function humataBuildSqCategoryCard(catIdx) {
                return '' +
                    '<div class="humata-sq-category-card" data-cat-index="' + catIdx + '">' +
                        '<div class="humata-sq-category-header">' +
                            '<button type="button" class="humata-sq-category-toggle" aria-expanded="true" aria-label="Toggle category">' +
                                '<span class="dashicons dashicons-arrow-down-alt2"></span>' +
                            '</button>' +
                            '<span class="humata-sq-category-title">Category #' + (catIdx + 1) + '</span>' +
                            '<button type="button" class="button link-delete humata-sq-category-remove">Remove Category</button>' +
                        '</div>' +
                        '<div class="humata-sq-category-body">' +
                            '<p>' +
                                '<label><strong>Category Name</strong> <span class="description">(optional)</span></label><br>' +
                                '<input type="text" class="regular-text" name="humata_suggested_questions[categories][' + catIdx + '][name]" value="" placeholder="e.g., Product Questions" maxlength="50">' +
                            '</p>' +
                            '<div class="humata-sq-questions-sub" data-next-q-index="1">' +
                                '<label><strong>Questions</strong></label>' +
                                '<p class="description">At least 1 question required per category.</p>' +
                                '<table class="widefat striped humata-sq-questions-table" style="max-width: 650px; margin-top: 6px;">' +
                                    '<thead>' +
                                        '<tr>' +
                                            '<th>Question Text</th>' +
                                            '<th style="width: 80px;">Actions</th>' +
                                        '</tr>' +
                                    '</thead>' +
                                    '<tbody>' +
                                        '<tr class="humata-sq-question-row">' +
                                            '<td>' +
                                                '<input type="text" class="large-text" name="humata_suggested_questions[categories][' + catIdx + '][questions][0]" value="" placeholder="e.g., How do I track my order?" maxlength="150">' +
                                            '</td>' +
                                            '<td>' +
                                                '<button type="button" class="button link-delete humata-sq-question-remove">Remove</button>' +
                                            '</td>' +
                                        '</tr>' +
                                    '</tbody>' +
                                '</table>' +
                                '<p style="margin-top: 8px;">' +
                                    '<button type="button" class="button button-secondary humata-sq-question-add">Add Question</button>' +
                                '</p>' +
                            '</div>' +
                        '</div>' +
                    '</div>';
            }
        })();

        // ---------------------------
        // API Key Pool Repeater Handlers
        // ---------------------------

        (function() {
            /**
             * Build a new API key row HTML.
             *
             * @param {string} optionName The option name for the field.
             * @param {number} index The row index.
             * @returns {string} HTML string.
             */
            function buildApiKeyRow(optionName, index) {
                return '' +
                    '<div class="humata-api-key-pool__row">' +
                        '<input' +
                            ' type="password"' +
                            ' id="' + optionName + '_' + index + '"' +
                            ' name="' + optionName + '[]"' +
                            ' value=""' +
                            ' class="regular-text humata-api-key-pool__input"' +
                            ' autocomplete="off"' +
                            ' placeholder="API Key"' +
                        '/>' +
                        '<button type="button" class="button humata-api-key-pool__remove" title="Remove">' +
                            '<span class="dashicons dashicons-trash"></span>' +
                        '</button>' +
                    '</div>';
            }

            /**
             * Update the key count display for a pool.
             *
             * @param {jQuery} $pool The pool container element.
             */
            function updateKeyCount($pool) {
                var $count = $pool.find('.humata-api-key-pool__count');
                var filledKeys = $pool.find('.humata-api-key-pool__input').filter(function() {
                    return $.trim($(this).val()) !== '';
                }).length;

                if (filledKeys > 1) {
                    if (!$count.length) {
                        $count = $('<span class="humata-api-key-pool__count"></span>');
                        $pool.find('.humata-api-key-pool__add').after($count);
                    }
                    $count.text(filledKeys + ' keys configured (rotation enabled)').show();
                } else {
                    $count.hide();
                }
            }

            // Add new API key row.
            $(document).on('click', '.humata-api-key-pool__add', function() {
                var $pool = $(this).closest('.humata-api-key-pool');
                var optionName = $pool.data('option') || '';
                var $keys = $pool.find('.humata-api-key-pool__keys');
                var nextIndex = $keys.find('.humata-api-key-pool__row').length;

                $keys.append(buildApiKeyRow(optionName, nextIndex));
                updateKeyCount($pool);

                // Focus the new input.
                $keys.find('.humata-api-key-pool__row').last().find('input').focus();
            });

            // Remove API key row.
            $(document).on('click', '.humata-api-key-pool__remove', function() {
                var $pool = $(this).closest('.humata-api-key-pool');
                var $row = $(this).closest('.humata-api-key-pool__row');
                var $rows = $pool.find('.humata-api-key-pool__row');

                // Keep at least one empty row.
                if ($rows.length <= 1) {
                    $row.find('input').val('');
                } else {
                    $row.remove();
                }

                updateKeyCount($pool);
            });

            // Update count on input change.
            $(document).on('input', '.humata-api-key-pool__input', function() {
                var $pool = $(this).closest('.humata-api-key-pool');
                updateKeyCount($pool);
            });

            // Initialize counts on page load.
            $('.humata-api-key-pool').each(function() {
                updateKeyCount($(this));
            });
        })();

        // ------------------------------------------
        // Follow-Up Questions Provider Toggle
        // ------------------------------------------
        (function() {
            var $providerRadios = $('.humata-followup-provider-radio');
            if (!$providerRadios.length) {
                return;
            }

            function updateFollowupProviderFields() {
                var selectedProvider = $('input[name="humata_followup_questions[provider]"]:checked').val() || 'straico';

                var $straicoFields = $('.humata-followup-straico-fields');
                var $anthropicFields = $('.humata-followup-anthropic-fields');
                var $openrouterFields = $('.humata-followup-openrouter-fields');

                $straicoFields.closest('tr').toggle(selectedProvider === 'straico');
                $anthropicFields.closest('tr').toggle(selectedProvider === 'anthropic');
                $openrouterFields.closest('tr').toggle(selectedProvider === 'openrouter');
            }

            // Bind change event.
            $providerRadios.on('change', updateFollowupProviderFields);

            // Initialize on page load.
            updateFollowupProviderFields();
        })();
    });
})(jQuery);


