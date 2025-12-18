/**
 * Humata Chat Widget
 *
 * Frontend JavaScript for the chat interface.
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

(function() {
    'use strict';

    // Configuration from WordPress
    const config = window.humataConfig || {};
    const STORAGE_KEY = 'humata_chat_history';
    const CONVERSATION_KEY = 'humata_conversation_id';
    const THEME_KEY = 'humata_theme_preference';

    // DOM Elements
    let elements = {};

    // State
    let isLoading = false;
    let conversationId = null;
    let activeEdit = null;
    let maxPromptChars = 3000;

    /**
     * Initialize the chat widget.
     */
    function init() {
        // Cache DOM elements
        elements = {
            container: document.getElementById('humata-chat-container'),
            messages: document.getElementById('humata-chat-messages'),
            input: document.getElementById('humata-chat-input'),
            inputCounter: document.getElementById('humata-chat-input-counter'),
            sendButton: document.getElementById('humata-send-button'),
            scrollToggle: document.getElementById('humata-scroll-toggle'),
            clearButton: document.getElementById('humata-clear-chat'),
            themeToggle: document.getElementById('humata-theme-toggle'),
            welcomeMessage: document.getElementById('humata-welcome-message')
        };

        if (!elements.container || !elements.messages || !elements.input) {
            return;
        }

        maxPromptChars = getMaxPromptChars();
        if (elements.input && maxPromptChars > 0) {
            elements.input.maxLength = maxPromptChars;
        }

        // Initialize theme
        initTheme();

        if (elements.welcomeMessage) {
            ensureCopyButton(elements.welcomeMessage.querySelector('.humata-message-content'));
        }

        // Load conversation history
        loadHistory();

        // Bind events
        bindEvents();

        handleInput();

        // Initialize scroll toggle state
        updateScrollToggleState();

        // Focus input
        if (elements.input) {
            elements.input.focus();
        }

        updateScrollToggleState();
    }

    /**
     * Initialize theme based on settings.
     */
    function initTheme() {
        const savedTheme = localStorage.getItem(THEME_KEY);
        const configTheme = config.theme || 'auto';

        let theme = savedTheme || configTheme;

        if (theme === 'auto') {
            theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }

        applyTheme(theme);

        // Listen for system theme changes
        if (configTheme === 'auto' && !savedTheme) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
                if (!localStorage.getItem(THEME_KEY)) {
                    applyTheme(e.matches ? 'dark' : 'light');
                }
            });
        }
    }

    /**
     * Apply theme to the document.
     *
     * @param {string} theme - Theme name ('dark' or 'light').
     */
    function applyTheme(theme) {
        const html = document.documentElement;
        const body = document.body;

        html.classList.remove('humata-theme-dark', 'humata-theme-light');
        body.classList.remove('humata-theme-dark', 'humata-theme-light');

        if (theme === 'dark') {
            html.classList.add('humata-theme-dark');
            body.classList.add('humata-theme-dark');
        } else {
            html.classList.add('humata-theme-light');
            body.classList.add('humata-theme-light');
        }
    }

    /**
     * Toggle theme between dark and light.
     */
    function toggleTheme() {
        const isDark = document.documentElement.classList.contains('humata-theme-dark');
        const newTheme = isDark ? 'light' : 'dark';

        localStorage.setItem(THEME_KEY, newTheme);
        applyTheme(newTheme);
    }

    /**
     * Bind event listeners.
     */
    function bindEvents() {
        // Send button click
        if (elements.sendButton) {
            elements.sendButton.addEventListener('click', handleSend);
        }

        // Input keydown
        if (elements.input) {
            elements.input.addEventListener('keydown', handleKeyDown);
            elements.input.addEventListener('input', handleInput);
        }

        // Scroll toggle
        if (elements.scrollToggle) {
            elements.scrollToggle.addEventListener('click', handleScrollToggle);
        }

        // Messages scroll
        if (elements.messages) {
            elements.messages.addEventListener('scroll', updateScrollToggleState, { passive: true });
            elements.messages.addEventListener('click', handleMessagesClick);
        }

        // Clear button
        if (elements.clearButton) {
            elements.clearButton.addEventListener('click', handleClear);
        }

        // Theme toggle
        if (elements.themeToggle) {
            elements.themeToggle.addEventListener('click', toggleTheme);
        }
    }

    function getMaxPromptChars() {
        const fromConfig = parseInt(config.maxPromptChars, 10);
        if (Number.isFinite(fromConfig) && fromConfig > 0) {
            return fromConfig;
        }

        if (elements.input) {
            const fromAttr = parseInt(elements.input.getAttribute('maxlength'), 10);
            if (Number.isFinite(fromAttr) && fromAttr > 0) {
                return fromAttr;
            }
        }

        return 3000;
    }

    function updatePromptCounter() {
        if (!elements.input || !elements.inputCounter) {
            return;
        }

        const current = (elements.input.value || '').length;
        const max = maxPromptChars || 3000;

        elements.inputCounter.textContent = current + '/' + max;
        elements.inputCounter.classList.toggle('humata-input-counter-over', current > max);
    }

    function handleMessagesClick(event) {
        const regenerateButton = event.target.closest('.humata-regenerate-button');
        if (regenerateButton) {
            const messageEl = regenerateButton.closest('.humata-message');
            if (!messageEl || isLoading || activeEdit) {
                return;
            }

            let userMessageEl = messageEl.previousElementSibling;
            while (userMessageEl && !userMessageEl.classList.contains('humata-message-user')) {
                userMessageEl = userMessageEl.previousElementSibling;
            }

            if (!userMessageEl) {
                return;
            }

            const contentEl = userMessageEl.querySelector('.humata-message-content');
            if (!contentEl) {
                return;
            }

            const text = getMessagePlainText(contentEl);
            if (!text) {
                return;
            }

            const history = getChatHistoryForRequestBefore(userMessageEl);
            truncateConversationAfter(userMessageEl);
            saveHistory();
            sendMessage(text, history);
            return;
        }

        const copyButton = event.target.closest('.humata-copy-button');
        if (copyButton) {
            const messageEl = copyButton.closest('.humata-message');
            if (!messageEl) {
                return;
            }

            const contentEl = messageEl.querySelector('.humata-message-content');
            if (!contentEl) {
                return;
            }

            const payload = getMessageClipboardPayload(contentEl);
            if (!payload || (!payload.text && !payload.html)) {
                return;
            }

            copyToClipboard(payload)
                .then(function() {
                    indicateCopySuccess(copyButton);
                })
                .catch(function() {
                    indicateCopyFailure(copyButton);
                });
            return;
        }

        const editButton = event.target.closest('.humata-edit-button');
        if (editButton) {
            const messageEl = editButton.closest('.humata-message');
            if (!messageEl || isLoading) {
                return;
            }
            startEditMessage(messageEl);
            return;
        }

        const cancelButton = event.target.closest('.humata-edit-cancel');
        if (cancelButton) {
            cancelActiveEdit();
            return;
        }

        const saveButton = event.target.closest('.humata-edit-save');
        if (saveButton) {
            saveActiveEdit();
        }
    }

    function setComposerEnabled(enabled) {
        if (!elements.input) {
            return;
        }

        elements.input.disabled = !enabled;

        if (elements.sendButton) {
            if (!enabled) {
                elements.sendButton.disabled = true;
            } else {
                const value = elements.input.value || '';
                elements.sendButton.disabled = !value.trim() || value.length > maxPromptChars;
            }
        }

        updatePromptCounter();
    }

    function startEditMessage(messageEl) {
        if (!messageEl || !messageEl.classList.contains('humata-message-user')) {
            return;
        }

        const contentEl = messageEl.querySelector('.humata-message-content');
        if (!contentEl) {
            return;
        }

        if (activeEdit && activeEdit.messageEl === messageEl) {
            return;
        }

        cancelActiveEdit();

        const originalText = getMessagePlainText(contentEl);
        if (!originalText) {
            return;
        }

        activeEdit = {
            messageEl: messageEl,
            contentEl: contentEl,
            originalText: originalText
        };

        messageEl.classList.add('humata-message-editing');
        contentEl.classList.add('humata-message-content-editing');
        contentEl.textContent = '';

        const textarea = document.createElement('textarea');
        textarea.className = 'humata-edit-textarea';
        textarea.value = originalText;
        textarea.rows = 1;
        textarea.maxLength = maxPromptChars;

        const actions = document.createElement('div');
        actions.className = 'humata-edit-actions';

        const cancelButton = document.createElement('button');
        cancelButton.type = 'button';
        cancelButton.className = 'humata-edit-cancel';
        cancelButton.innerHTML = getXIconSvg();
        cancelButton.setAttribute('title', config.i18n?.cancel || 'Cancel');
        cancelButton.setAttribute('aria-label', config.i18n?.cancel || 'Cancel');

        const saveButton = document.createElement('button');
        saveButton.type = 'button';
        saveButton.className = 'humata-edit-save';
        saveButton.innerHTML = getCheckIconSvg();
        saveButton.setAttribute('title', config.i18n?.save || 'Save');
        saveButton.setAttribute('aria-label', config.i18n?.save || 'Save');

        actions.appendChild(cancelButton);
        actions.appendChild(saveButton);

        contentEl.appendChild(textarea);
        contentEl.appendChild(actions);

        setComposerEnabled(false);

        textarea.addEventListener('input', function() {
            autoResizeEditTextarea(textarea);
        });

        textarea.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                cancelActiveEdit();
                return;
            }
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                saveActiveEdit();
            }
        });

        requestAnimationFrame(function() {
            autoResizeEditTextarea(textarea);
            textarea.focus();
            textarea.setSelectionRange(textarea.value.length, textarea.value.length);
        });
    }

    function autoResizeEditTextarea(textarea) {
        if (!textarea) {
            return;
        }

        textarea.style.height = 'auto';

        const lineHeight = parseInt(getComputedStyle(textarea).lineHeight) || 24;
        const maxHeight = lineHeight * 8;
        const newHeight = Math.min(textarea.scrollHeight, maxHeight);

        textarea.style.height = newHeight + 'px';

        updatePromptCounter();
    }

    function cancelActiveEdit() {
        if (!activeEdit) {
            return;
        }

        restoreUserMessageContent(activeEdit.messageEl, activeEdit.originalText);
        activeEdit = null;
        setComposerEnabled(true);

        if (elements.input) {
            elements.input.focus();
        }
    }

    function saveActiveEdit() {
        if (!activeEdit || isLoading) {
            return;
        }

        const textarea = activeEdit.contentEl.querySelector('.humata-edit-textarea');
        if (!textarea) {
            cancelActiveEdit();
            return;
        }

        const rawText = textarea.value;
        const newText = rawText.trim();
        if (!newText) {
            textarea.focus();
            return;
        }

        if (rawText.length > maxPromptChars) {
            const errorMessage = config.i18n?.errorPromptTooLong || ('Message is too long. Maximum is ' + maxPromptChars + ' characters.');
            addMessage(errorMessage, 'bot', true);
            textarea.focus();
            return;
        }

        const history = getChatHistoryForRequestBefore(activeEdit.messageEl);

        restoreUserMessageContent(activeEdit.messageEl, newText);
        truncateConversationAfter(activeEdit.messageEl);
        activeEdit = null;
        setComposerEnabled(true);
        saveHistory();
        sendMessage(newText, history);
    }

    function restoreUserMessageContent(messageEl, text) {
        if (!messageEl) {
            return;
        }

        const contentEl = messageEl.querySelector('.humata-message-content');
        if (!contentEl) {
            return;
        }

        messageEl.classList.remove('humata-message-editing');
        contentEl.classList.remove('humata-message-content-editing');
        contentEl.innerHTML = '';
        contentEl.textContent = text;

        ensureCopyButton(contentEl);
        ensureEditButton(contentEl);
    }

    function truncateConversationAfter(messageEl) {
        if (!elements.messages || !messageEl) {
            return;
        }

        hideLoading();

        let node = messageEl.nextSibling;
        while (node) {
            const next = node.nextSibling;
            if (node.nodeType === 1) {
                node.remove();
            }
            node = next;
        }
    }

    function getChatHistoryForRequestBefore(messageEl) {
        if (!elements.messages) {
            return [];
        }

        const history = [];
        const messageElements = elements.messages.querySelectorAll('.humata-message:not(#humata-welcome-message):not(.humata-message-loading)');

        for (let i = 0; i < messageElements.length; i++) {
            const el = messageElements[i];
            if (el === messageEl) {
                break;
            }

            const isUser = el.classList.contains('humata-message-user');
            const isError = el.classList.contains('humata-message-error');

            if (isError) {
                continue;
            }

            const content = el.querySelector('.humata-message-content');
            if (!content) {
                continue;
            }

            const text = getMessagePlainText(content);
            if (!text) {
                continue;
            }

            history.push({
                type: isUser ? 'user' : 'bot',
                content: text
            });
        }

        return history;
    }

    /**
     * Update scroll toggle state based on current scroll position.
     */
    function updateScrollToggleState() {
        if (!elements.messages || !elements.scrollToggle) {
            return;
        }

        const scrollHeight = elements.messages.scrollHeight;
        const clientHeight = elements.messages.clientHeight;
        const scrollTop = elements.messages.scrollTop;

        const maxScrollable = scrollHeight - clientHeight;

        if (maxScrollable <= 0) {
            elements.scrollToggle.style.display = 'none';
            return;
        }

        elements.scrollToggle.style.display = '';

        const distanceFromBottom = scrollHeight - (scrollTop + clientHeight);
        const threshold = maxScrollable * 0.3;
        const shouldScrollToTop = distanceFromBottom <= threshold;

        elements.scrollToggle.classList.toggle('humata-scroll-toggle-top', shouldScrollToTop);

        const labelTop = elements.scrollToggle.getAttribute('data-label-top') || 'Scroll to top';
        const labelBottom = elements.scrollToggle.getAttribute('data-label-bottom') || 'Scroll to bottom';
        const label = shouldScrollToTop ? labelTop : labelBottom;

        elements.scrollToggle.setAttribute('title', label);
        elements.scrollToggle.setAttribute('aria-label', label);
    }

    /**
     * Handle scroll toggle click.
     */
    function handleScrollToggle() {
        if (!elements.messages || !elements.scrollToggle) {
            return;
        }

        const shouldScrollToTop = elements.scrollToggle.classList.contains('humata-scroll-toggle-top');
        const targetTop = shouldScrollToTop ? 0 : elements.messages.scrollHeight;

        elements.messages.scrollTo({
            top: targetTop,
            behavior: 'smooth'
        });
    }

    /**
     * Handle input changes for auto-expanding textarea.
     */
    function handleInput() {
        const textarea = elements.input;

        // Reset height to auto to get the correct scrollHeight
        textarea.style.height = 'auto';

        // Calculate new height (max 5 rows)
        const lineHeight = parseInt(getComputedStyle(textarea).lineHeight) || 24;
        const maxHeight = lineHeight * 5;
        const newHeight = Math.min(textarea.scrollHeight, maxHeight);

        textarea.style.height = newHeight + 'px';

        const tooLong = textarea.value.length > maxPromptChars;

        updatePromptCounter();

        // Enable/disable send button based on input
        if (elements.sendButton) {
            elements.sendButton.disabled = !textarea.value.trim() || tooLong;
        }
    }

    /**
     * Handle keydown events in input.
     *
     * @param {KeyboardEvent} event - Keyboard event.
     */
    function handleKeyDown(event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            handleSend();
        }
    }

    /**
     * Handle send button click.
     */
    function handleSend() {
        const rawMessage = elements.input.value;
        const message = rawMessage.trim();

        if (!message || isLoading) {
            return;
        }

        if (rawMessage.length > maxPromptChars) {
            const errorMessage = config.i18n?.errorPromptTooLong || ('Message is too long. Maximum is ' + maxPromptChars + ' characters.');
            addMessage(errorMessage, 'bot', true);
            if (elements.input) {
                elements.input.focus();
            }
            return;
        }

        const history = getChatHistoryForRequest();

        // Add user message to chat
        addMessage(message, 'user');

        // Clear input
        elements.input.value = '';
        elements.input.style.height = 'auto';
        if (elements.sendButton) {
            elements.sendButton.disabled = true;
        }

        updatePromptCounter();

        // Send to API
        sendMessage(message, history);
    }

    function getChatHistoryForRequest() {
        if (!elements.messages) return [];

        const history = [];
        const messageElements = elements.messages.querySelectorAll('.humata-message:not(#humata-welcome-message):not(.humata-message-loading)');

        messageElements.forEach(function(el) {
            const isUser = el.classList.contains('humata-message-user');
            const isError = el.classList.contains('humata-message-error');

            if (isError) {
                return;
            }

            const content = el.querySelector('.humata-message-content');
            if (!content) {
                return;
            }

            const text = getMessagePlainText(content);
            if (!text) {
                return;
            }

            history.push({
                type: isUser ? 'user' : 'bot',
                content: text
            });
        });

        return history;
    }

    /**
     * Add a message to the chat.
     *
     * @param {string} content - Message content.
     * @param {string} type - Message type ('user' or 'bot').
     * @param {boolean} isError - Whether this is an error message.
     * @returns {HTMLElement} The message element.
     */
    function addMessage(content, type, isError = false) {
        // Hide welcome message when first message is added
        if (elements.welcomeMessage) {
            elements.welcomeMessage.style.display = 'none';
        }

        const messageDiv = document.createElement('div');
        messageDiv.className = 'humata-message humata-message-' + type;

        if (isError) {
            messageDiv.classList.add('humata-message-error');
        }

        // Avatar
        const avatarDiv = document.createElement('div');
        avatarDiv.className = 'humata-message-avatar';

        if (type === 'user') {
            avatarDiv.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>';
        } else {
            avatarDiv.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8V4H8"></path><rect width="16" height="12" x="4" y="8" rx="2"></rect><path d="M2 14h2"></path><path d="M20 14h2"></path><path d="M15 13v2"></path><path d="M9 13v2"></path></svg>';
        }

        // Content
        const contentDiv = document.createElement('div');
        contentDiv.className = 'humata-message-content';

        // Parse markdown-like formatting for bot messages
        if (type === 'bot' && !isError) {
            contentDiv.innerHTML = formatMessage(content);
        } else {
            contentDiv.textContent = content;
        }

        ensureCopyButton(contentDiv);
        if (type === 'bot') {
            ensureRegenerateButton(contentDiv);
        }
        if (type === 'user' && !isError) {
            ensureEditButton(contentDiv);
        }

        messageDiv.appendChild(avatarDiv);
        messageDiv.appendChild(contentDiv);
        elements.messages.appendChild(messageDiv);

        // Scroll to bottom
        scrollToBottom();

        // Save to history
        saveHistory();

        return messageDiv;
    }

    /**
     * Format message content with Markdown → HTML support for bot messages.
     * Raw HTML is always escaped/treated as text for safety.
     *
     * @param {string} content - Raw message content.
     * @returns {string} Formatted HTML content.
     */
    function formatMessage(content) {
        if (!content) {
            return '';
        }

        // Normalize newlines and escape raw HTML first.
        let formatted = escapeHtml(String(content).replace(/\r\n?/g, '\n'));

        // Extract fenced code blocks (```lang ... ```) so we don't parse Markdown inside them.
        const codeBlocks = [];
        formatted = formatted.replace(/```([a-zA-Z0-9_-]*)\n?([\s\S]*?)```/g, function(match, lang, code) {
            const token = '%%HUMATA_CODEBLOCK_' + codeBlocks.length + '%%';
            const safeLang = sanitizeCodeLanguage(lang || 'plaintext');
            codeBlocks.push('<pre><code class="language-' + safeLang + '">' + code.trim() + '</code></pre>');
            // Surround with newlines so tokens become standalone blocks.
            return '\n' + token + '\n';
        });

        // Render Markdown blocks into HTML.
        let html = renderMarkdownBlocks(formatted);

        // Restore code blocks.
        html = html.replace(/%%HUMATA_CODEBLOCK_(\d+)%%/g, function(match, idx) {
            const i = parseInt(idx, 10);
            return (Number.isFinite(i) && codeBlocks[i]) ? codeBlocks[i] : '';
        });

        return html;
    }

    let humataHtmlEntityDecoder = null;

    /**
     * Decode HTML entities in a string (e.g., &amp; / &#x3A;).
     * Used to validate link schemes safely.
     *
     * @param {string} text
     * @returns {string}
     */
    function decodeHtmlEntities(text) {
        if (text == null) {
            return '';
        }

        if (!humataHtmlEntityDecoder) {
            humataHtmlEntityDecoder = document.createElement('textarea');
        }

        humataHtmlEntityDecoder.innerHTML = String(text);
        return humataHtmlEntityDecoder.value;
    }

    /**
     * Sanitize a fenced code block language string for use in a CSS class name.
     *
     * @param {string} lang
     * @returns {string}
     */
    function sanitizeCodeLanguage(lang) {
        const clean = String(lang || '')
            .toLowerCase()
            .replace(/[^\w-]+/g, '');
        return clean || 'plaintext';
    }

    /**
     * Sanitize link href to avoid unsafe schemes (e.g., javascript:).
     * Returns an HTML-escaped href string safe to insert into an attribute, or an empty string.
     *
     * @param {string} href
     * @returns {string}
     */
    function sanitizeLinkHref(href) {
        if (!href) {
            return '';
        }

        const decoded = decodeHtmlEntities(String(href)).trim();
        if (!decoded) {
            return '';
        }

        // Strip whitespace/control characters when evaluating scheme.
        const cleaned = decoded.replace(/[\u0000-\u001F\u007F\s]+/g, '');
        if (!cleaned) {
            return '';
        }

        // Allow common relative forms and anchors.
        if (
            cleaned.startsWith('#') ||
            cleaned.startsWith('/') ||
            cleaned.startsWith('./') ||
            cleaned.startsWith('../') ||
            cleaned.startsWith('?') ||
            cleaned.startsWith('//')
        ) {
            return escapeHtml(decoded);
        }

        const schemeMatch = cleaned.match(/^([a-zA-Z][a-zA-Z0-9+.-]*):/);
        if (schemeMatch) {
            const scheme = (schemeMatch[1] || '').toLowerCase();
            const allowed = scheme === 'http' || scheme === 'https' || scheme === 'mailto' || scheme === 'tel';
            if (!allowed) {
                return '';
            }
        }

        return escapeHtml(decoded);
    }

    function isBlankLine(line) {
        return !line || /^\s*$/.test(line);
    }

    function isCodeBlockTokenLine(line) {
        return /^%%HUMATA_CODEBLOCK_\d+%%$/.test(String(line || '').trim());
    }

    function isHorizontalRuleLine(line) {
        return /^\s{0,3}(-{3,}|\*{3,})\s*$/.test(String(line || ''));
    }

    function parseHeadingLine(line) {
        const match = String(line || '').match(/^\s{0,3}(#{1,6})\s+(.+?)\s*$/);
        if (!match) {
            return null;
        }
        return {
            level: match[1].length,
            text: match[2]
        };
    }

    function isBlockquoteLine(line) {
        return /^\s{0,3}>\s?/.test(String(line || ''));
    }

    function stripBlockquotePrefix(line) {
        return String(line || '').replace(/^\s{0,3}>\s?/, '');
    }

    function isUnorderedListLine(line) {
        return /^\s{0,3}[-+*]\s+/.test(String(line || ''));
    }

    function isOrderedListLine(line) {
        return /^\s{0,3}\d+\.\s+/.test(String(line || ''));
    }

    /**
     * Render inline Markdown within a single block of text.
     *
     * @param {string} text - HTML-escaped text.
     * @returns {string}
     */
    function renderInlineMarkdown(text) {
        if (!text) {
            return '';
        }

        let out = String(text);

        // Protect inline code spans first so we don't apply other formatting within them.
        const codeSpans = [];
        out = out.replace(/`([^`]+)`/g, function(match, code) {
            const token = '%%HUMATA_CODESPAN_' + codeSpans.length + '%%';
            codeSpans.push('<code>' + code + '</code>');
            return token;
        });

        // Links: [label](url)
        out = out.replace(/\[([^\]]+)\]\(([^)]+)\)/g, function(match, label, url) {
            const safeHref = sanitizeLinkHref(url);
            if (!safeHref) {
                // Render as plain text if unsafe.
                return label + ' (' + url + ')';
            }
            return '<a href="' + safeHref + '" target="_blank" rel="noopener noreferrer">' + label + '</a>';
        });

        // Bold: **text**
        out = out.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');

        // Italic: *text* (avoid consuming **bold**)
        out = out.replace(/(^|[^*])\*([^*\n]+)\*(?!\*)/g, '$1<em>$2</em>');

        // Restore code spans.
        out = out.replace(/%%HUMATA_CODESPAN_(\d+)%%/g, function(match, idx) {
            const i = parseInt(idx, 10);
            return (Number.isFinite(i) && codeSpans[i]) ? codeSpans[i] : match;
        });

        return out;
    }

    /**
     * Render a limited, safe subset of Markdown into HTML block elements.
     *
     * @param {string} text - HTML-escaped text.
     * @returns {string}
     */
    function renderMarkdownBlocks(text) {
        if (!text) {
            return '';
        }

        const lines = String(text).split('\n');
        const out = [];
        let i = 0;

        while (i < lines.length) {
            const line = lines[i];

            if (isBlankLine(line)) {
                i++;
                continue;
            }

            // Standalone code block token (block-level).
            if (isCodeBlockTokenLine(line)) {
                out.push(String(line).trim());
                i++;
                continue;
            }

            // Horizontal rule.
            if (isHorizontalRuleLine(line)) {
                out.push('<hr>');
                i++;
                continue;
            }

            // Heading.
            const heading = parseHeadingLine(line);
            if (heading) {
                out.push('<h' + heading.level + '>' + renderInlineMarkdown(heading.text) + '</h' + heading.level + '>');
                i++;
                continue;
            }

            // Blockquote.
            if (isBlockquoteLine(line)) {
                const quoteLines = [];
                while (i < lines.length && isBlockquoteLine(lines[i])) {
                    quoteLines.push(stripBlockquotePrefix(lines[i]));
                    i++;
                }
                out.push('<blockquote>' + renderMarkdownBlocks(quoteLines.join('\n')) + '</blockquote>');
                continue;
            }

            // Unordered list.
            if (isUnorderedListLine(line)) {
                const items = [];
                while (i < lines.length) {
                    const current = lines[i];

                    if (isBlankLine(current)) {
                        // Allow blank lines within a list if the next non-blank continues it.
                        let j = i + 1;
                        while (j < lines.length && isBlankLine(lines[j])) {
                            j++;
                        }
                        if (j < lines.length && isUnorderedListLine(lines[j])) {
                            i = j;
                            continue;
                        }
                        break;
                    }

                    if (!isUnorderedListLine(current)) {
                        break;
                    }

                    const itemText = String(current).replace(/^\s{0,3}[-+*]\s+/, '');
                    items.push('<li>' + renderInlineMarkdown(itemText) + '</li>');
                    i++;
                }

                out.push('<ul>' + items.join('') + '</ul>');
                continue;
            }

            // Ordered list.
            if (isOrderedListLine(line)) {
                const items = [];
                while (i < lines.length) {
                    const current = lines[i];

                    if (isBlankLine(current)) {
                        let j = i + 1;
                        while (j < lines.length && isBlankLine(lines[j])) {
                            j++;
                        }
                        if (j < lines.length && isOrderedListLine(lines[j])) {
                            i = j;
                            continue;
                        }
                        break;
                    }

                    if (!isOrderedListLine(current)) {
                        break;
                    }

                    const itemText = String(current).replace(/^\s{0,3}\d+\.\s+/, '');
                    items.push('<li>' + renderInlineMarkdown(itemText) + '</li>');
                    i++;
                }

                out.push('<ol>' + items.join('') + '</ol>');
                continue;
            }

            // Paragraph: gather consecutive non-blank, non-block-start lines.
            const paraLines = [];
            while (i < lines.length) {
                const current = lines[i];

                if (isBlankLine(current)) {
                    break;
                }

                if (
                    isCodeBlockTokenLine(current) ||
                    isHorizontalRuleLine(current) ||
                    parseHeadingLine(current) ||
                    isBlockquoteLine(current) ||
                    isUnorderedListLine(current) ||
                    isOrderedListLine(current)
                ) {
                    break;
                }

                paraLines.push(String(current).trim());
                i++;
            }

            const paraText = paraLines.join(' ').trim();
            if (paraText) {
                out.push('<p>' + renderInlineMarkdown(paraText) + '</p>');
            }
        }

        return out.join('');
    }

    /**
     * Escape HTML special characters.
     *
     * @param {string} text - Text to escape.
     * @returns {string} Escaped text.
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function ensureCopyButton(contentEl) {
        if (!contentEl || contentEl.querySelector('.humata-copy-button')) {
            return;
        }

        contentEl.classList.add('humata-message-content-has-copy');

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'humata-copy-button';
        button.innerHTML = getCopyIconSvg();
        button.setAttribute('title', 'Copy');
        button.setAttribute('aria-label', 'Copy message');
        contentEl.appendChild(button);
    }

    function ensureRegenerateButton(contentEl) {
        if (!contentEl || contentEl.querySelector('.humata-regenerate-button')) {
            return;
        }

        contentEl.classList.add('humata-message-content-has-regenerate');

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'humata-regenerate-button';
        button.innerHTML = getRegenerateIconSvg();
        button.setAttribute('title', config.i18n?.regenerate || 'Regenerate');
        button.setAttribute('aria-label', config.i18n?.regenerate || 'Regenerate');
        contentEl.appendChild(button);
    }

    function ensureEditButton(contentEl) {
        if (!contentEl || contentEl.querySelector('.humata-edit-button')) {
            return;
        }

        contentEl.classList.add('humata-message-content-has-edit');

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'humata-edit-button';
        button.innerHTML = getEditIconSvg();
        button.setAttribute('title', config.i18n?.editMessage || 'Edit message');
        button.setAttribute('aria-label', config.i18n?.editMessage || 'Edit message');
        contentEl.appendChild(button);
    }

    function getMessagePlainText(contentEl) {
        const editTextarea = contentEl ? contentEl.querySelector('.humata-edit-textarea') : null;
        if (editTextarea) {
            return (editTextarea.value || '').trim();
        }

        const cloned = contentEl.cloneNode(true);
        cloned.querySelectorAll('.humata-copy-button').forEach(function(btn) {
            btn.remove();
        });
        cloned.querySelectorAll('.humata-regenerate-button').forEach(function(btn) {
            btn.remove();
        });
        cloned.querySelectorAll('.humata-edit-button').forEach(function(btn) {
            btn.remove();
        });
        cloned.querySelectorAll('.humata-edit-actions').forEach(function(el) {
            el.remove();
        });
        return ((cloned.innerText || cloned.textContent || '') + '').trim();
    }

    function getMessageHtmlForStorage(contentEl) {
        const cloned = contentEl.cloneNode(true);
        cloned.querySelectorAll('.humata-copy-button').forEach(function(btn) {
            btn.remove();
        });
        cloned.querySelectorAll('.humata-regenerate-button').forEach(function(btn) {
            btn.remove();
        });
        cloned.querySelectorAll('.humata-edit-button').forEach(function(btn) {
            btn.remove();
        });
        cloned.querySelectorAll('.humata-edit-actions').forEach(function(el) {
            el.remove();
        });
        return cloned.innerHTML;
    }

    function getMessageClipboardPayload(contentEl) {
        const editTextarea = contentEl ? contentEl.querySelector('.humata-edit-textarea') : null;
        if (editTextarea) {
            const text = (editTextarea.value || '').trim();
            return {
                text: text,
                html: text ? '<div>' + escapeHtml(text).replace(/\n/g, '<br>') + '</div>' : ''
            };
        }

        const innerHtml = contentEl ? getMessageHtmlForStorage(contentEl) : '';
        if (!innerHtml) {
            return { text: '', html: '' };
        }

        return {
            text: htmlFragmentToPlainText(innerHtml),
            html: '<div>' + innerHtml + '</div>'
        };
    }

    function htmlFragmentToPlainText(html) {
        if (!html) {
            return '';
        }

        const container = document.createElement('div');
        container.innerHTML = html;

        const text = domNodeToPlainText(container, { listStack: [] });

        return String(text || '')
            .replace(/\r\n?/g, '\n')
            .replace(/[ \t]+\n/g, '\n')
            .replace(/\n{3,}/g, '\n\n')
            .trim();
    }

    function domNodeToPlainText(node, ctx) {
        if (!node) {
            return '';
        }

        const type = node.nodeType;
        // Text node
        if (type === 3) {
            return node.nodeValue || '';
        }

        // Only process elements
        if (type !== 1) {
            return '';
        }

        const tag = (node.tagName || '').toLowerCase();

        function childrenText() {
            let out = '';
            const children = node.childNodes || [];
            for (let i = 0; i < children.length; i++) {
                out += domNodeToPlainText(children[i], ctx);
            }
            return out;
        }

        function ensureEndsWithNewline(out) {
            if (!out) {
                return '\n';
            }
            return out.endsWith('\n') ? out : out + '\n';
        }

        function ensureBlankLine(out) {
            let t = ensureEndsWithNewline(out);
            if (!t.endsWith('\n\n')) {
                t += '\n';
            }
            return t;
        }

        if (tag === 'br') {
            return '\n';
        }

        if (tag === 'hr') {
            return '\n\n';
        }

        if (tag === 'pre') {
            const block = node.textContent || '';
            return ensureBlankLine(block.replace(/\n$/, ''));
        }

        if (tag === 'p' || tag === 'div' || tag === 'section') {
            const t = childrenText().trim();
            return t ? ensureBlankLine(t) : '';
        }

        if (tag === 'blockquote') {
            const t = childrenText().trim();
            return t ? ensureBlankLine(t) : '';
        }

        if (/^h[1-6]$/.test(tag)) {
            const t = childrenText().trim();
            return t ? ensureBlankLine(t) : '';
        }

        if (tag === 'ul' || tag === 'ol') {
            ctx.listStack.push({ type: tag, index: 0 });
            let out = '';
            const children = node.childNodes || [];
            for (let i = 0; i < children.length; i++) {
                out += domNodeToPlainText(children[i], ctx);
            }
            ctx.listStack.pop();
            return ensureBlankLine(out.trim());
        }

        if (tag === 'li') {
            const stack = ctx.listStack || [];
            const current = stack.length ? stack[stack.length - 1] : null;
            let prefix = '- ';
            if (current && current.type === 'ol') {
                current.index += 1;
                prefix = String(current.index) + '. ';
            } else if (current && current.type === 'ul') {
                prefix = '• ';
            }

            const indent = stack.length > 1 ? '  '.repeat(stack.length - 1) : '';
            const t = childrenText().trim();
            return t ? indent + prefix + t + '\n' : '';
        }

        // Default inline-ish handling
        return childrenText();
    }

    function copyToClipboard(payload) {
        if (payload == null) {
            return Promise.reject(new Error('Nothing to copy'));
        }

        // Backward-compatible plain text copy
        if (typeof payload === 'string') {
            return copyPlainTextToClipboard(payload);
        }

        const text = String(payload.text || '');
        const html = String(payload.html || '');

        // Prefer the modern async clipboard API when available.
        if (html && navigator.clipboard && navigator.clipboard.write && typeof ClipboardItem !== 'undefined') {
            try {
                const item = new ClipboardItem({
                    'text/plain': new Blob([text], { type: 'text/plain' }),
                    'text/html': new Blob([html], { type: 'text/html' })
                });

                return navigator.clipboard.write([item]).catch(function() {
                    // Fall back to selection-based copy when permissions are denied.
                    return copyHtmlWithSelection(html);
                });
            } catch (e) {
                // Fall through to legacy method
            }
        }

        if (html) {
            return copyHtmlWithSelection(html);
        }

        return copyPlainTextToClipboard(text);
    }

    function copyHtmlWithSelection(html) {
        return new Promise(function(resolve, reject) {
            try {
                const container = document.createElement('div');
                container.setAttribute('aria-hidden', 'true');
                container.style.position = 'fixed';
                container.style.left = '-9999px';
                container.style.top = '0';
                container.style.width = '1px';
                container.style.height = '1px';
                container.style.overflow = 'hidden';
                container.innerHTML = html;
                document.body.appendChild(container);

                const selection = window.getSelection ? window.getSelection() : null;
                const savedRanges = [];
                if (selection && selection.rangeCount) {
                    for (let i = 0; i < selection.rangeCount; i++) {
                        savedRanges.push(selection.getRangeAt(i).cloneRange());
                    }
                }

                if (selection) {
                    const range = document.createRange();
                    range.selectNodeContents(container);
                    selection.removeAllRanges();
                    selection.addRange(range);
                }

                const successful = document.execCommand('copy');

                if (selection) {
                    selection.removeAllRanges();
                    for (let i = 0; i < savedRanges.length; i++) {
                        selection.addRange(savedRanges[i]);
                    }
                }

                container.remove();

                if (successful) {
                    resolve();
                } else {
                    reject(new Error('Copy failed'));
                }
            } catch (e) {
                reject(e);
            }
        });
    }

    function copyPlainTextToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text).catch(function() {
                return copyPlainTextWithTextarea(text);
            });
        }

        return copyPlainTextWithTextarea(text);
    }

    function copyPlainTextWithTextarea(text) {
        return new Promise(function(resolve, reject) {
            try {
                const textarea = document.createElement('textarea');
                textarea.value = String(text || '');
                textarea.setAttribute('readonly', '');
                textarea.style.position = 'fixed';
                textarea.style.top = '-9999px';
                textarea.style.left = '-9999px';
                document.body.appendChild(textarea);
                textarea.select();
                textarea.setSelectionRange(0, textarea.value.length);
                const successful = document.execCommand('copy');
                textarea.remove();
                if (successful) {
                    resolve();
                } else {
                    reject(new Error('Copy failed'));
                }
            } catch (e) {
                reject(e);
            }
        });
    }

    function indicateCopySuccess(button) {
        if (!button) {
            return;
        }

        if (!button.dataset.humataCopyDefault) {
            button.dataset.humataCopyDefault = button.innerHTML;
        }

        if (button._humataCopyResetTimer) {
            clearTimeout(button._humataCopyResetTimer);
        }

        button.innerHTML = getCheckIconSvg();
        button.classList.add('humata-copy-button-success');
        button.setAttribute('title', 'Copied');
        button.setAttribute('aria-label', 'Copied');

        button._humataCopyResetTimer = setTimeout(function() {
            button.innerHTML = button.dataset.humataCopyDefault;
            button.classList.remove('humata-copy-button-success');
            button.setAttribute('title', 'Copy');
            button.setAttribute('aria-label', 'Copy message');
        }, 1500);
    }

    function indicateCopyFailure(button) {
        if (!button) {
            return;
        }

        button.setAttribute('title', 'Copy failed');
        button.setAttribute('aria-label', 'Copy failed');

        if (button._humataCopyResetTimer) {
            clearTimeout(button._humataCopyResetTimer);
        }

        button._humataCopyResetTimer = setTimeout(function() {
            button.setAttribute('title', 'Copy');
            button.setAttribute('aria-label', 'Copy message');
        }, 1500);
    }

    function getCopyIconSvg() {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>';
    }

    function getEditIconSvg() {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"></path></svg>';
    }

    function getRegenerateIconSvg() {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.13-3.36L23 10"></path><path d="M1 14l5.36 5.36A9 9 0 0 0 20.49 15"></path></svg>';
    }

    function getCheckIconSvg() {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>';
    }

    function getXIconSvg() {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>';
    }

    /**
     * Show loading indicator.
     *
     * @returns {HTMLElement} The loading element.
     */
    function showLoading() {
        const loadingDiv = document.createElement('div');
        loadingDiv.className = 'humata-message humata-message-bot humata-message-loading';
        loadingDiv.id = 'humata-loading-indicator';

        const avatarDiv = document.createElement('div');
        avatarDiv.className = 'humata-message-avatar';
        avatarDiv.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8V4H8"></path><rect width="16" height="12" x="4" y="8" rx="2"></rect><path d="M2 14h2"></path><path d="M20 14h2"></path><path d="M15 13v2"></path><path d="M9 13v2"></path></svg>';

        const contentDiv = document.createElement('div');
        contentDiv.className = 'humata-message-content';
        const secondStageProvider = String(config.secondLlmProvider || '').toLowerCase();
        const showStatus = secondStageProvider !== '' && secondStageProvider !== 'none';
        if (showStatus) {
            contentDiv.innerHTML = '' +
                '<div class="humata-typing-indicator"><span></span><span></span><span></span></div>' +
                '<div class="humata-loading-status" aria-live="polite" aria-atomic="true">' +
                    '<span class="humata-loading-status-text"></span>' +
                '</div>';
        } else {
            contentDiv.innerHTML = '<div class="humata-typing-indicator"><span></span><span></span><span></span></div>';
        }

        loadingDiv.appendChild(avatarDiv);
        loadingDiv.appendChild(contentDiv);
        elements.messages.appendChild(loadingDiv);

        if (showStatus) {
            const statusEl = contentDiv.querySelector('.humata-loading-status-text');
            if (statusEl) {
                const phrases = [
                    'Processing your question…',
                    config.i18n?.thinking || 'Thinking…',
                    'Reviewing for accuracy…',
                    'Just a few moments more…',
                    'Almost ready…',
                    'Finalizing answer…'
                ];

                let idx = 0;
                statusEl.textContent = phrases[idx];
                statusEl.style.opacity = '1';

                loadingDiv._humataStatusTimer = setInterval(function() {
                    idx = (idx + 1) % phrases.length;
                    statusEl.style.opacity = '0';
                    loadingDiv._humataStatusFadeTimer = setTimeout(function() {
                        statusEl.textContent = phrases[idx];
                        statusEl.style.opacity = '1';
                    }, 180);
                }, 2000);
            }
        }

        scrollToBottom();

        return loadingDiv;
    }

    /**
     * Hide loading indicator.
     */
    function hideLoading() {
        const loading = document.getElementById('humata-loading-indicator');
        if (loading) {
            if (loading._humataStatusTimer) {
                clearInterval(loading._humataStatusTimer);
            }
            if (loading._humataStatusFadeTimer) {
                clearTimeout(loading._humataStatusFadeTimer);
            }
            loading.remove();
        }
    }

    /**
     * Scroll chat to bottom.
     */
    function scrollToBottom() {
        if (elements.messages) {
            elements.messages.scrollTop = elements.messages.scrollHeight;
            updateScrollToggleState();
        }
    }

    /**
     * Send message to API.
     *
     * @param {string} message - User message.
     */
    async function sendMessage(message, history = []) {
        if (isLoading) return;

        isLoading = true;
        showLoading();

        try {
            const response = await fetch(config.apiUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': config.wpNonce,
                    'X-Humata-Nonce': config.nonce
                },
                body: JSON.stringify({
                    message: message,
                    history: history
                })
            });

            hideLoading();

            const data = await response.json();

            if (!response.ok) {
                const requestFailedMessage = config.i18n?.errorRequestFailed || 'Your message request failed. Try again. If problem persists, please contact us.';
                let errorMessage = config.i18n?.errorGeneric || 'An error occurred. Please try again.';

                if (response.status === 429) {
                    errorMessage = config.i18n?.errorRateLimit || 'Too many requests. Please wait a moment.';
                } else if (data && typeof data.message === 'string' && data.message.trim() !== '') {
                    const providerMentionPattern = /\b(straico|humata|anthropic|claude)\b/i;
                    errorMessage = providerMentionPattern.test(data.message) ? requestFailedMessage : data.message;
                } else {
                    errorMessage = requestFailedMessage;
                }

                addMessage(errorMessage, 'bot', true);
                return;
            }

            if (data.success && data.answer) {
                addMessage(data.answer, 'bot');
            } else {
                addMessage(config.i18n?.errorGeneric || 'An error occurred. Please try again.', 'bot', true);
            }

        } catch (error) {
            hideLoading();
            addMessage(config.i18n?.errorNetwork || 'Network error. Please check your connection.', 'bot', true);
        } finally {
            isLoading = false;
            elements.input.focus();
        }
    }

    /**
     * Handle clear chat button click.
     */
    function handleClear() {
        // Clear messages from DOM
        if (elements.messages) {
            elements.messages.innerHTML = '';
        }

        // Show welcome message again
        if (elements.welcomeMessage) {
            elements.welcomeMessage.style.display = '';
            elements.messages.appendChild(elements.welcomeMessage);
            ensureCopyButton(elements.welcomeMessage.querySelector('.humata-message-content'));
        } else {
            // Recreate welcome message if it doesn't exist
            const welcomeHtml = `
                <div id="humata-welcome-message" class="humata-message humata-message-bot">
                    <div class="humata-message-avatar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 8V4H8"></path>
                            <rect width="16" height="12" x="4" y="8" rx="2"></rect>
                            <path d="M2 14h2"></path>
                            <path d="M20 14h2"></path>
                            <path d="M15 13v2"></path>
                            <path d="M9 13v2"></path>
                        </svg>
                    </div>
                    <div class="humata-message-content">
                        <p>${config.i18n?.welcome || "Hello! I'm here to help answer your questions. What would you like to know?"}</p>
                    </div>
                </div>
            `;
            elements.messages.innerHTML = welcomeHtml;
            elements.welcomeMessage = document.getElementById('humata-welcome-message');
            if (elements.welcomeMessage) {
                ensureCopyButton(elements.welcomeMessage.querySelector('.humata-message-content'));
            }
        }

        // Clear localStorage
        localStorage.removeItem(STORAGE_KEY);
        localStorage.removeItem(CONVERSATION_KEY);
        conversationId = null;

        // Focus input
        if (elements.input) {
            elements.input.focus();
        }
    }

    /**
     * Save chat history to localStorage.
     */
    function saveHistory() {
        if (!elements.messages) return;

        const messages = [];
        const messageElements = elements.messages.querySelectorAll('.humata-message:not(#humata-welcome-message):not(.humata-message-loading)');

        messageElements.forEach(function(el) {
            const isUser = el.classList.contains('humata-message-user');
            const isError = el.classList.contains('humata-message-error');
            const content = el.querySelector('.humata-message-content');

            if (content) {
                messages.push({
                    type: isUser ? 'user' : 'bot',
                    content: isUser ? getMessagePlainText(content) : getMessageHtmlForStorage(content),
                    isError: isError
                });
            }
        });

        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(messages));
        } catch (e) {
            // localStorage might be full or disabled
        }
    }

    /**
     * Load chat history from localStorage.
     */
    function loadHistory() {
        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (!saved) return;

            const messages = JSON.parse(saved);
            if (!Array.isArray(messages) || messages.length === 0) return;

            // Hide welcome message
            if (elements.welcomeMessage) {
                elements.welcomeMessage.style.display = 'none';
            }

            // Restore messages
            messages.forEach(function(msg) {
                const messageDiv = document.createElement('div');
                messageDiv.className = 'humata-message humata-message-' + msg.type;

                if (msg.isError) {
                    messageDiv.classList.add('humata-message-error');
                }

                const avatarDiv = document.createElement('div');
                avatarDiv.className = 'humata-message-avatar';

                if (msg.type === 'user') {
                    avatarDiv.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>';
                } else {
                    avatarDiv.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8V4H8"></path><rect width="16" height="12" x="4" y="8" rx="2"></rect><path d="M2 14h2"></path><path d="M20 14h2"></path><path d="M15 13v2"></path><path d="M9 13v2"></path></svg>';
                }

                const contentDiv = document.createElement('div');
                contentDiv.className = 'humata-message-content';

                // For bot messages, use innerHTML (already formatted)
                // For user messages, use textContent
                if (msg.type === 'user') {
                    contentDiv.textContent = msg.content;
                } else {
                    contentDiv.innerHTML = msg.content;
                }

                ensureCopyButton(contentDiv);
                if (msg.type === 'bot') {
                    ensureRegenerateButton(contentDiv);
                }
                if (msg.type === 'user' && !msg.isError) {
                    ensureEditButton(contentDiv);
                }

                messageDiv.appendChild(avatarDiv);
                messageDiv.appendChild(contentDiv);
                elements.messages.appendChild(messageDiv);
            });

            // Load conversation ID
            conversationId = localStorage.getItem(CONVERSATION_KEY);

            // Scroll to bottom
            scrollToBottom();

        } catch (e) {
            // Invalid JSON or other error
            localStorage.removeItem(STORAGE_KEY);
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
