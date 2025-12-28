/**
 * Humata Chat Widget
 *
 * Frontend JavaScript for the chat interface.
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

import { config, STORAGE_KEY, CONVERSATION_KEY, THEME_KEY } from './config.js';
import { setAvatarContent } from './avatars.js';
import { initSuggestedQuestions, hideSuggestedQuestions } from './suggested-questions.js';
import { initFollowUpQuestions, renderFollowUpQuestions, hideFollowUpQuestions } from './follow-up-questions.js';
import {
    initBotProtection,
    isBotProtectionEnabled,
    isPowEnabled,
    isPowVerified,
    setPowVerified,
    getBotProtectionHeaders,
    extractPowChallenge,
    clearPowVerification
} from './bot-protection.js';

(function() {
    'use strict';

    // DOM Elements
    let elements = {};

    // State
    let isLoading = false;
    let conversationId = null;
    let activeEdit = null;
    let maxPromptChars = 3000;
    let isEmbedded = false;
    let accordionIdCounter = 0;

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
            exportPdfButton: document.getElementById('humata-export-pdf'),
            clearButton: document.getElementById('humata-clear-chat'),
            themeToggle: document.getElementById('humata-theme-toggle'),
            welcomeMessage: document.getElementById('humata-welcome-message')
        };

        if (!elements.container || !elements.messages || !elements.input) {
            return;
        }

        // Detect embedded mode
        isEmbedded = elements.container.classList.contains('humata-chat-embedded');

        maxPromptChars = getMaxPromptChars();
        if (elements.input && maxPromptChars > 0) {
            elements.input.maxLength = maxPromptChars;
        }

        // Initialize theme
        initTheme();

        // Initialize bot protection
        initBotProtection();

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

        // Initialize mobile keyboard handling
        initMobileKeyboardHandler();

        // Initialize suggested questions (only if no history loaded)
        initSuggestedQuestionsIfEmpty();

        // Initialize follow-up questions handler
        initFollowUpQuestions(handleFollowUpQuestionClick);
    }

    /**
     * Initialize suggested questions if chat is empty.
     */
    function initSuggestedQuestionsIfEmpty() {
        const hasHistory = elements.messages &&
            elements.messages.querySelectorAll('.humata-message-user').length > 0;

        if (!hasHistory) {
            initSuggestedQuestions(handleSuggestedQuestionClick);
        }
    }

    /**
     * Handle click on a suggested question - send it immediately.
     *
     * @param {string} question The question text.
     */
    function handleSuggestedQuestionClick(question) {
        if (!question || isLoading) {
            return;
        }

        // Hide suggested questions
        hideSuggestedQuestions();

        // Store user message for intent link matching
        lastUserMessageForIntents = question;

        // Add user message to chat
        addMessage(question, 'user');

        // Get history and send
        const history = getChatHistoryForRequest();
        sendMessage(question, history);
    }

    /**
     * Handle click on a follow-up question - send it immediately.
     *
     * @param {string} question The question text.
     */
    function handleFollowUpQuestionClick(question) {
        if (!question || isLoading) {
            return;
        }

        // Hide follow-up questions
        hideFollowUpQuestions();

        // Store user message for intent link matching
        lastUserMessageForIntents = question;

        // Add user message to chat
        addMessage(question, 'user');

        // Get history and send
        const history = getChatHistoryForRequest();
        sendMessage(question, history);
    }

    /**
     * Initialize mobile keyboard handler using visualViewport API.
     * Adjusts the input container position when the virtual keyboard appears.
     * Detaches from fixed positioning when input container reaches trigger-pages/footer.
     */
    function initMobileKeyboardHandler() {
        if (!window.visualViewport) {
            return;
        }

        const inputContainer = document.getElementById('humata-chat-input-container');
        if (!inputContainer) {
            return;
        }

        const pageFooter = document.getElementById('humata-chat-page-footer');

        function getStopElement() {
            const triggerPages = document.querySelector('.humata-trigger-pages:not(.humata-trigger-pages--hidden)');
            return triggerPages || pageFooter;
        }

        const initialViewportHeight = window.visualViewport.height;
        let isKeyboardVisible = false;
        let isDetached = false;
        let pendingUpdate = false;

        function updateInputPosition() {
            if (pendingUpdate) {
                return;
            }
            pendingUpdate = true;

            requestAnimationFrame(function() {
                pendingUpdate = false;

                const vv = window.visualViewport;
                const viewportBottom = vv.offsetTop + vv.height;
                const layoutBottom = window.innerHeight;
                const bottomOffset = layoutBottom - viewportBottom;
                const heightReduction = initialViewportHeight - vv.height;
                const keyboardOpen = heightReduction > 150;

                if (keyboardOpen) {
                    if (!isKeyboardVisible) {
                        isKeyboardVisible = true;
                        document.body.classList.add('humata-keyboard-open');
                    }

                    // Check if we should detach (input container would overlap stop element)
                    let shouldDetach = false;
                    const stopElement = getStopElement();
                    if (stopElement) {
                        const stopRect = stopElement.getBoundingClientRect();
                        const inputHeight = inputContainer.offsetHeight;
                        const keyboardTop = vv.height + vv.offsetTop;
                        const inputFixedBottom = keyboardTop - inputHeight;

                        // If the stop element's top is within or above where the input would be fixed
                        if (stopRect.top <= keyboardTop && stopRect.top > 0) {
                            shouldDetach = true;
                        }
                    }

                    if (shouldDetach) {
                        // Detach: let input container scroll with content
                        if (!isDetached) {
                            isDetached = true;
                            inputContainer.classList.add('humata-input-detached');
                        }
                        inputContainer.style.position = '';
                        inputContainer.style.bottom = '';
                        inputContainer.style.left = '';
                        inputContainer.style.right = '';
                        inputContainer.style.transform = '';
                    } else {
                        // Attach: fix input container above keyboard
                        if (isDetached) {
                            isDetached = false;
                            inputContainer.classList.remove('humata-input-detached');
                        }
                        inputContainer.style.position = 'fixed';
                        inputContainer.style.bottom = '0';
                        inputContainer.style.left = '0';
                        inputContainer.style.right = '0';
                        inputContainer.style.transform = 'translateY(' + (-bottomOffset) + 'px)';
                    }
                } else {
                    if (isKeyboardVisible) {
                        isKeyboardVisible = false;
                        isDetached = false;
                        document.body.classList.remove('humata-keyboard-open');
                        inputContainer.classList.remove('humata-input-detached');
                    }
                    inputContainer.style.position = '';
                    inputContainer.style.bottom = '';
                    inputContainer.style.left = '';
                    inputContainer.style.right = '';
                    inputContainer.style.transform = '';
                }
            });
        }

        window.visualViewport.addEventListener('resize', updateInputPosition);
        window.visualViewport.addEventListener('scroll', updateInputPosition);
        window.addEventListener('scroll', updateInputPosition, { passive: true });

        if (elements.input) {
            elements.input.addEventListener('focus', function() {
                setTimeout(updateInputPosition, 300);
            });
            elements.input.addEventListener('blur', function() {
                setTimeout(updateInputPosition, 100);
            });
        }
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

        if (elements.themeToggle) {
            elements.themeToggle.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
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
     * Wrap a promise with a timeout.
     *
     * @param {Promise} promise - The promise to wrap.
     * @param {number} ms - Timeout in milliseconds.
     * @param {string} errorType - Error type identifier for timeout.
     * @returns {Promise} Resolves with promise result or rejects on timeout.
     */
    function withTimeout(promise, ms, errorType) {
        return Promise.race([
            promise,
            new Promise(function(_, reject) {
                setTimeout(function() {
                    const error = new Error('Operation timed out');
                    error.type = errorType;
                    reject(error);
                }, ms);
            })
        ]);
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

        // Scroll event for scroll toggle state
        if (isEmbedded && elements.messages) {
            elements.messages.addEventListener('scroll', updateScrollToggleState, { passive: true });
        } else {
            window.addEventListener('scroll', updateScrollToggleState, { passive: true });
        }

        // Messages click handler
        if (elements.messages) {
            elements.messages.addEventListener('click', handleMessagesClick);
        }

        // Clear button
        if (elements.clearButton) {
            elements.clearButton.addEventListener('click', handleClear);
        }

        if (elements.exportPdfButton) {
            elements.exportPdfButton.addEventListener('click', handleExportPdf);
        }

        // Theme toggle
        if (elements.themeToggle) {
            elements.themeToggle.addEventListener('click', toggleTheme);
        }

        // Trigger page modals
        initTriggerPageModals();

        // Medical disclaimer modal
        initMedicalDisclaimerModal();

        installModalFocusTrap();
    }

    function installModalFocusTrap() {
        if (typeof window === 'undefined') {
            return;
        }

        if (window.__humataModalFocusTrapInstalled) {
            return;
        }
        window.__humataModalFocusTrapInstalled = true;

        document.addEventListener('keydown', function(e) {
            if (e.key !== 'Tab') {
                return;
            }

            const openModal = document.querySelector('.humata-help-modal.is-open');
            if (!openModal) {
                return;
            }

            const dialog = openModal.querySelector('.humata-help-modal__dialog');
            if (!dialog) {
                return;
            }

            const focusableNodeList = dialog.querySelectorAll(
                'a[href], area[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), iframe, object, embed, [contenteditable], [tabindex]:not([tabindex="-1"])'
            );
            const focusable = Array.prototype.slice.call(focusableNodeList).filter(function(el) {
                return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
            });

            if (!focusable.length) {
                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            const active = document.activeElement;

            if (!dialog.contains(active)) {
                e.preventDefault();
                if (e.shiftKey) {
                    last.focus();
                } else {
                    first.focus();
                }
                return;
            }

            if (e.shiftKey && active === first) {
                e.preventDefault();
                last.focus();
                return;
            }

            if (!e.shiftKey && active === last) {
                e.preventDefault();
                first.focus();
            }
        });
    }

    /**
     * Initialize medical disclaimer modal behavior.
     */
    function initMedicalDisclaimerModal() {
        const disclaimerLink = document.getElementById('humata-medical-disclaimer-link');
        const disclaimerModal = document.getElementById('humata-medical-disclaimer-modal');

        if (!disclaimerLink || !disclaimerModal) {
            return;
        }

        disclaimerLink.addEventListener('click', function(e) {
            e.preventDefault();
            openPageModal(disclaimerModal, disclaimerLink);
        });

        // Close on overlay or close button click
        disclaimerModal.addEventListener('click', function(e) {
            if (e.target.closest('[data-humata-disclaimer-close]')) {
                e.preventDefault();
                closePageModal(disclaimerModal);
            }
        });
    }

    /**
     * Initialize trigger page modal behavior.
     */
    function initTriggerPageModals() {
        // Open modal from trigger links
        document.addEventListener('click', function(e) {
            const trigger = e.target.closest('[data-humata-page-modal]');
            if (!trigger) {
                return;
            }
            e.preventDefault();
            const modalIndex = trigger.getAttribute('data-humata-page-modal');
            const modal = document.getElementById('humata-page-modal-' + modalIndex);
            if (modal) {
                openPageModal(modal, trigger);
            }
        });

        // Close modal on overlay or close button click
        document.addEventListener('click', function(e) {
            const closeTarget = e.target.closest('[data-humata-page-close-modal]');
            if (!closeTarget) {
                return;
            }
            const modal = closeTarget.closest('.humata-help-modal');
            if (modal) {
                e.preventDefault();
                closePageModal(modal);
            }
        });

        // ESC closes modal
        document.addEventListener('keydown', function(e) {
            if (e.key !== 'Escape') {
                return;
            }
            const openModal = document.querySelector('.humata-help-modal.is-open');
            if (openModal) {
                e.preventDefault();
                closePageModal(openModal);
            }
        });
    }

    let pageModalLastFocused = null;

    /**
     * Open a trigger page modal.
     *
     * @param {HTMLElement} modal - Modal element.
     * @param {HTMLElement} triggerEl - Trigger element.
     */
    function openPageModal(modal, triggerEl) {
        pageModalLastFocused = triggerEl || document.activeElement;

        modal.hidden = false;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');

        document.documentElement.classList.add('humata-help-modal-open');
        document.body.classList.add('humata-help-modal-open');

        const closeBtn = modal.querySelector('.humata-help-modal__close');
        if (closeBtn) {
            try {
                closeBtn.focus({ preventScroll: true });
            } catch (err) {
                closeBtn.focus();
            }
        }
    }

    /**
     * Close a trigger page modal.
     *
     * @param {HTMLElement} modal - Modal element.
     */
    function closePageModal(modal) {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        modal.hidden = true;

        document.documentElement.classList.remove('humata-help-modal-open');
        document.body.classList.remove('humata-help-modal-open');

        if (pageModalLastFocused && typeof pageModalLastFocused.focus === 'function') {
            try {
                pageModalLastFocused.focus({ preventScroll: true });
            } catch (err) {
                pageModalLastFocused.focus();
            }
        }
        pageModalLastFocused = null;
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
        if (!elements.scrollToggle) {
            return;
        }

        let scrollHeight, clientHeight, scrollTop;

        if (isEmbedded && elements.messages) {
            scrollHeight = elements.messages.scrollHeight;
            clientHeight = elements.messages.clientHeight;
            scrollTop = elements.messages.scrollTop;
        } else {
            scrollHeight = document.documentElement.scrollHeight;
            clientHeight = window.innerHeight;
            scrollTop = window.scrollY || document.documentElement.scrollTop;
        }

        const maxScrollable = scrollHeight - clientHeight;

        if (maxScrollable <= 50) {
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
        if (!elements.scrollToggle) {
            return;
        }

        const shouldScrollToTop = elements.scrollToggle.classList.contains('humata-scroll-toggle-top');

        if (isEmbedded && elements.messages) {
            const targetTop = shouldScrollToTop ? 0 : elements.messages.scrollHeight;
            elements.messages.scrollTo({
                top: targetTop,
                behavior: 'smooth'
            });
        } else {
            const targetTop = shouldScrollToTop ? 0 : document.documentElement.scrollHeight;
            window.scrollTo({
                top: targetTop,
                behavior: 'smooth'
            });
        }
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

        // Hide suggested questions on first send
        hideSuggestedQuestions();

        // Hide any existing follow-up questions
        hideFollowUpQuestions();

        if (rawMessage.length > maxPromptChars) {
            const errorMessage = config.i18n?.errorPromptTooLong || ('Message is too long. Maximum is ' + maxPromptChars + ' characters.');
            addMessage(errorMessage, 'bot', true);
            if (elements.input) {
                elements.input.focus();
            }
            return;
        }

        const history = getChatHistoryForRequest();

        // Store user message for intent link matching
        lastUserMessageForIntents = message;

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
     * Hide trigger pages after first user message.
     */
    function hideTriggerPages() {
        const triggerPages = document.querySelector('.humata-trigger-pages');
        if (triggerPages && !triggerPages.classList.contains('humata-trigger-pages--hidden')) {
            triggerPages.classList.add('humata-trigger-pages--hidden');
        }
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

        // Hide trigger pages after first user message
        if (type === 'user') {
            hideTriggerPages();
        }

        const messageDiv = document.createElement('div');
        messageDiv.className = 'humata-message humata-message-' + type;

        if (isError) {
            messageDiv.classList.add('humata-message-error');
        }

        // Avatar
        const avatarDiv = document.createElement('div');
        avatarDiv.className = 'humata-message-avatar';
        setAvatarContent(avatarDiv, type);

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

        // Update bot response disclaimer position for bot messages
        if (type === 'bot' && !isError) {
            updateBotResponseDisclaimerPosition();
        }

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

        // Merge consecutive ordered lists that were split by intervening content.
        html = mergeConsecutiveOrderedLists(html);

        // Restore code blocks.
        html = html.replace(/%%HUMATA_CODEBLOCK_(\d+)%%/g, function(match, idx) {
            const i = parseInt(idx, 10);
            return (Number.isFinite(i) && codeBlocks[i]) ? codeBlocks[i] : '';
        });

        return html;
    }

    /**
     * Merge consecutive <ol> elements that are separated by other content.
     * This handles AI responses that generate numbered lists with explanatory
     * paragraphs or other content between numbered items.
     *
     * @param {string} html - The HTML string to process.
     * @returns {string} HTML with consecutive ordered lists merged.
     */
    function mergeConsecutiveOrderedLists(html) {
        if (!html || typeof html !== 'string') {
            return html;
        }

        // Pattern: </ol> followed by content (not containing <ol or </ol>) followed by <ol>
        // We merge by removing the </ol> and <ol> tags, keeping the content between.
        // The content becomes part of the list (after the previous </li> and before the next <li>).
        const pattern = /<\/ol>(\s*(?:<p>[\s\S]*?<\/p>|<ul>[\s\S]*?<\/ul>|<blockquote>[\s\S]*?<\/blockquote>|<hr>)+\s*)<ol>/g;

        let result = html;
        let prevResult = '';

        // Keep merging until no more matches (handles multiple consecutive splits).
        while (result !== prevResult) {
            prevResult = result;
            result = result.replace(pattern, function(match, betweenContent) {
                // Insert the between content inside the list, after the last </li>.
                return betweenContent + '</ol><ol>';
            });
        }

        // Now remove empty </ol><ol> pairs that resulted from the merge.
        result = result.replace(/<\/ol>\s*<ol>/g, '');

        return result;
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

    let humataPreparedAutoLinks = null;

    function humataIsWordChar(ch) {
        return !!ch && /[A-Za-z0-9]/.test(ch);
    }

    function getPreparedAutoLinks() {
        if (humataPreparedAutoLinks !== null) {
            return humataPreparedAutoLinks;
        }

        const raw = Array.isArray(config.autoLinks) ? config.autoLinks : [];
        const prepared = [];

        for (let i = 0; i < raw.length; i++) {
            const row = raw[i];
            if (!row || typeof row !== 'object') {
                continue;
            }

            const phrase = String(row.phrase || '').trim();
            const url = String(row.url || '').trim();
            if (!phrase || !url) {
                continue;
            }

            const safeHrefEscaped = sanitizeLinkHref(url);
            if (!safeHrefEscaped) {
                continue;
            }

            prepared.push({
                phrase: phrase,
                phraseLower: phrase.toLowerCase(),
                href: decodeHtmlEntities(safeHrefEscaped),
                len: phrase.length,
                startsWord: humataIsWordChar(phrase.charAt(0)),
                endsWord: humataIsWordChar(phrase.charAt(phrase.length - 1))
            });

            if (prepared.length >= 200) {
                break;
            }
        }

        prepared.sort(function(a, b) {
            return b.len - a.len;
        });

        humataPreparedAutoLinks = prepared;
        return humataPreparedAutoLinks;
    }

    function humataFindAutoLinkMatches(text, rules) {
        if (!text) {
            return [];
        }

        const haystack = String(text);
        const lower = haystack.toLowerCase();
        const matches = [];

        function overlaps(start, end) {
            for (let i = 0; i < matches.length; i++) {
                const m = matches[i];
                if (start < m.end && m.start < end) {
                    return true;
                }
            }
            return false;
        }

        for (let r = 0; r < rules.length; r++) {
            const rule = rules[r];
            if (!rule || !rule.phraseLower) {
                continue;
            }

            let idx = 0;
            while (true) {
                idx = lower.indexOf(rule.phraseLower, idx);
                if (idx === -1) {
                    break;
                }

                const start = idx;
                const end = idx + rule.phraseLower.length;

                // Boundary checks: avoid matching inside other words (e.g., "Liver" inside "Deliver").
                if (rule.startsWord) {
                    const before = start > 0 ? haystack.charAt(start - 1) : '';
                    if (before && humataIsWordChar(before)) {
                        idx = idx + 1;
                        continue;
                    }
                }
                if (rule.endsWord) {
                    const after = end < haystack.length ? haystack.charAt(end) : '';
                    if (after && humataIsWordChar(after)) {
                        idx = idx + 1;
                        continue;
                    }
                }

                if (!overlaps(start, end)) {
                    matches.push({
                        start: start,
                        end: end,
                        href: rule.href
                    });
                }

                idx = end;
            }
        }

        return matches;
    }

    function humataApplyAutoLinksToInlineHtml(html) {
        if (!html) {
            return html;
        }

        const rules = getPreparedAutoLinks();
        if (!rules || !rules.length) {
            return html;
        }

        if (typeof document === 'undefined' || !document.createElement || !document.createTreeWalker || typeof NodeFilter === 'undefined') {
            return html;
        }

        const container = document.createElement('div');
        try {
            container.innerHTML = String(html);
        } catch (e) {
            return html;
        }

        let walker;
        try {
            walker = document.createTreeWalker(container, NodeFilter.SHOW_TEXT, {
                acceptNode: function(node) {
                    const value = node && node.nodeValue ? String(node.nodeValue) : '';
                    if (!value || !value.trim()) {
                        return NodeFilter.FILTER_REJECT;
                    }

                    // Skip text inside existing links (avoid nested anchors) and code/pre blocks.
                    let p = node.parentNode;
                    while (p && p !== container) {
                        const name = p.nodeName;
                        if (name === 'A' || name === 'CODE' || name === 'PRE') {
                            return NodeFilter.FILTER_REJECT;
                        }
                        p = p.parentNode;
                    }

                    return NodeFilter.FILTER_ACCEPT;
                }
            });
        } catch (e) {
            return html;
        }

        const nodes = [];
        let node;
        while ((node = walker.nextNode())) {
            nodes.push(node);
        }

        for (let i = 0; i < nodes.length; i++) {
            const textNode = nodes[i];
            const text = textNode && textNode.nodeValue ? String(textNode.nodeValue) : '';
            if (!text) {
                continue;
            }

            const matches = humataFindAutoLinkMatches(text, rules);
            if (!matches.length) {
                continue;
            }

            matches.sort(function(a, b) {
                return a.start - b.start;
            });

            const frag = document.createDocumentFragment();
            let lastIndex = 0;

            for (let m = 0; m < matches.length; m++) {
                const match = matches[m];
                if (!match || match.start < lastIndex) {
                    continue;
                }

                if (match.start > lastIndex) {
                    frag.appendChild(document.createTextNode(text.slice(lastIndex, match.start)));
                }

                const a = document.createElement('a');
                a.setAttribute('href', match.href);
                a.setAttribute('target', '_blank');
                a.setAttribute('rel', 'noopener noreferrer');
                a.textContent = text.slice(match.start, match.end);
                frag.appendChild(a);

                lastIndex = match.end;
            }

            if (lastIndex < text.length) {
                frag.appendChild(document.createTextNode(text.slice(lastIndex)));
            }

            if (textNode.parentNode) {
                textNode.parentNode.replaceChild(frag, textNode);
            }
        }

        return container.innerHTML;
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

        // Auto-link configured phrases while avoiding existing anchors and code tokens.
        out = humataApplyAutoLinksToInlineHtml(out);

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

    function getOrCreateMessageActionsContainer(contentEl) {
        if (!contentEl) {
            return null;
        }

        let actionsEl = contentEl.querySelector('.humata-message-actions');
        if (actionsEl) {
            return actionsEl;
        }

        actionsEl = document.createElement('div');
        actionsEl.className = 'humata-message-actions';
        contentEl.appendChild(actionsEl);
        return actionsEl;
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

        const actionsEl = getOrCreateMessageActionsContainer(contentEl);
        if (!actionsEl) {
            return;
        }

        actionsEl.appendChild(button);
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

        const actionsEl = getOrCreateMessageActionsContainer(contentEl);
        if (!actionsEl) {
            return;
        }

        actionsEl.appendChild(button);
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

        const actionsEl = getOrCreateMessageActionsContainer(contentEl);
        if (!actionsEl) {
            return;
        }

        actionsEl.appendChild(button);
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
        cloned.querySelectorAll('.humata-message-actions').forEach(function(el) {
            el.remove();
        });
        cloned.querySelectorAll('.humata-bot-response-disclaimer').forEach(function(el) {
            el.remove();
        });
        cloned.querySelectorAll('.humata-intent-links').forEach(function(el) {
            el.remove();
        });
        cloned.querySelectorAll('.humata-accordions').forEach(function(el) {
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
        cloned.querySelectorAll('.humata-message-actions').forEach(function(el) {
            el.remove();
        });
        cloned.querySelectorAll('.humata-bot-response-disclaimer').forEach(function(el) {
            el.remove();
        });
        cloned.querySelectorAll('.humata-intent-links').forEach(function(el) {
            el.remove();
        });
        cloned.querySelectorAll('.humata-accordions').forEach(function(el) {
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
     * Get or create the bot response disclaimer element.
     * Returns null if no disclaimer is configured.
     *
     * @returns {HTMLElement|null}
     */
    function getBotResponseDisclaimerElement() {
        const disclaimerHtml = config.botResponseDisclaimer;
        if (!disclaimerHtml || typeof disclaimerHtml !== 'string' || !disclaimerHtml.trim()) {
            return null;
        }

        let disclaimerEl = document.getElementById('humata-bot-response-disclaimer');
        if (!disclaimerEl) {
            disclaimerEl = document.createElement('div');
            disclaimerEl.id = 'humata-bot-response-disclaimer';
            disclaimerEl.className = 'humata-bot-response-disclaimer';
            disclaimerEl.innerHTML = '<p>' + disclaimerHtml + '</p>';
        }

        return disclaimerEl;
    }

    /**
     * Update the position of the bot response disclaimer to be inside the
     * latest non-error bot message's content (after the message actions).
     * Hides it if no suitable bot message exists.
     */
    function updateBotResponseDisclaimerPosition() {
        const disclaimerEl = getBotResponseDisclaimerElement();
        if (!disclaimerEl) {
            return;
        }

        if (!elements.messages) {
            disclaimerEl.remove();
            return;
        }

        // Find the latest non-error, non-loading bot message
        const botMessages = elements.messages.querySelectorAll(
            '.humata-message-bot:not(.humata-message-error):not(.humata-message-loading):not(#humata-welcome-message)'
        );

        if (!botMessages.length) {
            disclaimerEl.remove();
            return;
        }

        const latestBotMessage = botMessages[botMessages.length - 1];
        const contentEl = latestBotMessage.querySelector('.humata-message-content');

        if (!contentEl) {
            disclaimerEl.remove();
            return;
        }

        // Append disclaimer inside the message content (after actions if present)
        contentEl.appendChild(disclaimerEl);
    }

    /**
     * Hide the bot response disclaimer temporarily (e.g., during loading).
     */
    function hideBotResponseDisclaimer() {
        const disclaimerEl = document.getElementById('humata-bot-response-disclaimer');
        if (disclaimerEl) {
            disclaimerEl.remove();
        }
    }

    /**
     * Escape special regex characters in a string.
     *
     * @param {string} str - String to escape.
     * @returns {string} Escaped string.
     */
    function escapeRegexChars(str) {
        return String(str || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    /**
     * Find matching intent links based on the user's message.
     * Matches keywords as whole words (case-insensitive).
     *
     * @param {string} userMessage - The user's question text.
     * @returns {Array} Array of {title, url} objects to display (deduplicated).
     */
    function findMatchingIntentLinks(userMessage) {
        const intents = config.intentLinks;
        if (!intents || !Array.isArray(intents) || !intents.length) {
            return [];
        }

        if (!userMessage || typeof userMessage !== 'string') {
            return [];
        }

        const matchedLinks = [];
        const seenUrls = new Set();

        for (let i = 0; i < intents.length; i++) {
            const intent = intents[i];
            const keywords = intent.keywords;
            const links = intent.links;

            if (!keywords || !Array.isArray(keywords) || !keywords.length) {
                continue;
            }
            if (!links || !Array.isArray(links) || !links.length) {
                continue;
            }

            let matched = false;
            for (let k = 0; k < keywords.length; k++) {
                const keyword = keywords[k];
                if (!keyword) {
                    continue;
                }

                // Whole-word match (case-insensitive)
                const pattern = new RegExp('\\b' + escapeRegexChars(keyword) + '\\b', 'i');
                if (pattern.test(userMessage)) {
                    matched = true;
                    break;
                }
            }

            if (matched) {
                for (let j = 0; j < links.length; j++) {
                    const link = links[j];
                    if (!link || !link.title || !link.url) {
                        continue;
                    }
                    if (!seenUrls.has(link.url)) {
                        seenUrls.add(link.url);
                        matchedLinks.push({
                            title: link.title,
                            url: link.url
                        });
                    }
                }
            }
        }

        return matchedLinks;
    }

    /**
     * Create the intent links element with pill-shaped buttons.
     *
     * @param {Array} links - Array of {title, url} objects.
     * @returns {HTMLElement|null} The container element or null if no links.
     */
    function createIntentLinksElement(links) {
        if (!links || !links.length) {
            return null;
        }

        const container = document.createElement('div');
        container.className = 'humata-intent-links';

        const label = document.createElement('span');
        label.className = 'humata-intent-links-label';
        label.textContent = config.i18n?.relatedResources || 'Related resources:';
        container.appendChild(label);

        const pillsWrap = document.createElement('div');
        pillsWrap.className = 'humata-intent-links-pills';

        for (let i = 0; i < links.length; i++) {
            const link = links[i];
            const a = document.createElement('a');
            a.href = link.url;
            a.target = '_blank';
            a.rel = 'noopener noreferrer';
            a.className = 'humata-intent-link';
            a.textContent = link.title;
            pillsWrap.appendChild(a);
        }

        container.appendChild(pillsWrap);
        return container;
    }

    /**
     * Find matching intent accordions based on the user's message.
     * Matches keywords as whole words (case-insensitive).
     *
     * @param {string} userMessage - The user's question text.
     * @returns {Array} Array of {title, content} objects to display (deduplicated).
     */
    function findMatchingIntentAccordions(userMessage) {
        const intents = config.intentLinks;
        if (!intents || !Array.isArray(intents) || !intents.length) {
            return [];
        }

        if (!userMessage || typeof userMessage !== 'string') {
            return [];
        }

        const matchedAccordions = [];
        const seenTitles = new Set();

        for (let i = 0; i < intents.length; i++) {
            const intent = intents[i];
            const keywords = intent.keywords;
            const accordions = intent.accordions;

            if (!keywords || !Array.isArray(keywords) || !keywords.length) {
                continue;
            }
            if (!accordions || !Array.isArray(accordions) || !accordions.length) {
                continue;
            }

            let matched = false;
            for (let k = 0; k < keywords.length; k++) {
                const keyword = keywords[k];
                if (!keyword) {
                    continue;
                }

                // Whole-word match (case-insensitive)
                const pattern = new RegExp('\\b' + escapeRegexChars(keyword) + '\\b', 'i');
                if (pattern.test(userMessage)) {
                    matched = true;
                    break;
                }
            }

            if (matched) {
                for (let j = 0; j < accordions.length; j++) {
                    const acc = accordions[j];
                    if (!acc || !acc.title || !acc.content) {
                        continue;
                    }
                    if (!seenTitles.has(acc.title)) {
                        seenTitles.add(acc.title);
                        matchedAccordions.push({
                            title: acc.title,
                            content: acc.content
                        });
                    }
                }
            }
        }

        return matchedAccordions;
    }

    /**
     * Create the accordion toggles element.
     *
     * @param {Array} accordions - Array of {title, content} objects.
     * @returns {HTMLElement|null} The container element or null if no accordions.
     */
    function createAccordionElement(accordions) {
        if (!accordions || !accordions.length) {
            return null;
        }

        const container = document.createElement('div');
        container.className = 'humata-accordions';

        for (let i = 0; i < accordions.length; i++) {
            const acc = accordions[i];

            const item = document.createElement('div');
            item.className = 'humata-accordion-item';

            const contentId = 'humata-accordion-content-' + (++accordionIdCounter);
            const toggleId = 'humata-accordion-toggle-' + accordionIdCounter;

            const toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'humata-accordion-toggle';
            toggle.id = toggleId;
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-controls', contentId);
            toggle.innerHTML = '<span>' + escapeHtml(acc.title) + '</span>' +
                '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>';

            const content = document.createElement('div');
            content.className = 'humata-accordion-content';
            content.id = contentId;
            content.hidden = true;
            content.setAttribute('role', 'region');
            content.setAttribute('aria-labelledby', toggleId);
            content.innerHTML = acc.content; // Pre-sanitized server-side

            toggle.addEventListener('click', function() {
                const isOpen = item.classList.toggle('open');
                toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                content.hidden = !isOpen;
            });

            item.appendChild(toggle);
            item.appendChild(content);
            container.appendChild(item);
        }

        return container;
    }

    /**
     * Append intent links and accordions to a bot message element based on the user's question.
     *
     * @param {HTMLElement} botMessageEl - The bot message element.
     * @param {string} userMessage - The user's original question.
     */
    function appendIntentLinksToMessage(botMessageEl, userMessage) {
        if (!botMessageEl || !userMessage) {
            return;
        }

        const contentEl = botMessageEl.querySelector('.humata-message-content');
        if (!contentEl) {
            return;
        }

        // Find matching accordions and links
        const accordions = findMatchingIntentAccordions(userMessage);
        const links = findMatchingIntentLinks(userMessage);

        // Create accordion element if any matched
        const accordionEl = createAccordionElement(accordions);
        if (accordionEl) {
            // Store matched accordions on element for potential export
            botMessageEl._humataIntentAccordions = accordions;

            // Insert accordions before message-actions if present
            const actionsEl = contentEl.querySelector('.humata-message-actions');
            if (actionsEl) {
                contentEl.insertBefore(accordionEl, actionsEl);
            } else {
                contentEl.appendChild(accordionEl);
            }
        }

        // Create intent links element if any matched
        if (!links.length) {
            return;
        }

        const intentLinksEl = createIntentLinksElement(links);
        if (!intentLinksEl) {
            return;
        }

        // Store the matched links on the element for PDF export
        botMessageEl._humataIntentLinks = links;

        // Insert before the bot response disclaimer if present, otherwise append
        const disclaimerEl = contentEl.querySelector('.humata-bot-response-disclaimer');
        if (disclaimerEl) {
            contentEl.insertBefore(intentLinksEl, disclaimerEl);
        } else {
            contentEl.appendChild(intentLinksEl);
        }
    }

    // Track the last user message for intent link matching
    let lastUserMessageForIntents = '';

    /**
     * Show loading indicator.
     *
     * @returns {HTMLElement} The loading element.
     */
    function showLoading() {
        // Hide disclaimer while loading
        hideBotResponseDisclaimer();

        const loadingDiv = document.createElement('div');
        loadingDiv.className = 'humata-message humata-message-bot humata-message-loading';
        loadingDiv.id = 'humata-loading-indicator';

        const avatarDiv = document.createElement('div');
        avatarDiv.className = 'humata-message-avatar';
        setAvatarContent(avatarDiv, 'bot');

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
        if (isEmbedded && elements.messages) {
            elements.messages.scrollTop = elements.messages.scrollHeight;
        } else {
            window.scrollTo({
                top: document.documentElement.scrollHeight,
                behavior: 'smooth'
            });
        }
        updateScrollToggleState();
    }

    /**
     * Scroll to a specific message element (to its top).
     *
     * @param {HTMLElement} messageEl - The message element to scroll to.
     */
    function scrollToMessage(messageEl) {
        if (!messageEl) {
            scrollToBottom();
            return;
        }

        if (isEmbedded && elements.messages) {
            // For embedded mode, scroll within the messages container
            const containerRect = elements.messages.getBoundingClientRect();
            const messageRect = messageEl.getBoundingClientRect();
            const offsetTop = messageRect.top - containerRect.top + elements.messages.scrollTop;
            elements.messages.scrollTo({
                top: offsetTop,
                behavior: 'smooth'
            });
        } else {
            // For full-page mode, scroll the window
            const rect = messageEl.getBoundingClientRect();
            const scrollTop = window.scrollY || document.documentElement.scrollTop;
            const targetTop = rect.top + scrollTop - 80; // 80px offset from top for better visibility
            window.scrollTo({
                top: Math.max(0, targetTop),
                behavior: 'smooth'
            });
        }
        updateScrollToggleState();
    }

    /**
     * Send message to API.
     *
     * @param {string} message - User message.
     */
    async function sendMessage(message, history = []) {
        if (isLoading) return;

        isLoading = true;

        // Show loading indicator immediately for user feedback
        showLoading();

        try {
            // Build request headers
            const headers = {
                'Content-Type': 'application/json',
                'X-WP-Nonce': config.wpNonce,
                'X-Humata-Nonce': config.nonce
            };

            // Include bot protection headers
            let botProtectionHeaders = {};
            let powChallenge = null;
            if (isBotProtectionEnabled()) {
                botProtectionHeaders = await getBotProtectionHeaders();
                Object.assign(headers, botProtectionHeaders);
            }

            let response = await fetch(config.apiUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: headers,
                body: JSON.stringify({
                    message: message,
                    history: history
                })
            });

            // Handle PoW challenge response - solve and retry
            // Check for 403 even if client thinks it's verified (server transient may have expired)
            if (response.status === 403 && isPowEnabled()) {
                const errorData = await response.json();
                powChallenge = extractPowChallenge(errorData);
                
                if (powChallenge) {
                    // Clear stale client verification state if server requires new challenge
                    clearPowVerification();
                    
                    // Solve the PoW challenge
                    const powHeaders = await getBotProtectionHeaders(powChallenge);
                    Object.assign(headers, powHeaders);
                    
                    // Retry the request with PoW solution
                    response = await fetch(config.apiUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: headers,
                        body: JSON.stringify({
                            message: message,
                            history: history
                        })
                    });
                }
            }

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

            // Mark PoW as verified on successful response
            if (isPowEnabled() && !isPowVerified()) {
                setPowVerified();
            }

            if (data.success && data.answer) {
                const botMessageEl = addMessage(data.answer, 'bot');
                // Append intent-based resource links if keywords matched
                if (botMessageEl && lastUserMessageForIntents) {
                    appendIntentLinksToMessage(botMessageEl, lastUserMessageForIntents);
                    saveHistory(); // Re-save to include intent links
                }
                // Render follow-up questions if provided
                if (botMessageEl && data.followUpQuestions && Array.isArray(data.followUpQuestions) && data.followUpQuestions.length > 0) {
                    renderFollowUpQuestions(botMessageEl, data.followUpQuestions);
                    // Store on element for persistence
                    botMessageEl._humataFollowUpQuestions = data.followUpQuestions;
                    saveHistory(); // Re-save to include follow-up questions
                }
                // Scroll to the beginning of the bot's response
                scrollToMessage(botMessageEl);
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

    function handleExportPdf() {
        const exportButton = elements.exportPdfButton;
        if (exportButton) {
            exportButton.disabled = true;
        }

        try {
            const jsPDF = (window.jspdf && window.jspdf.jsPDF) ? window.jspdf.jsPDF : (window.jsPDF || null);
            if (!jsPDF) {
                return;
            }

            const doc = new jsPDF({ unit: 'pt', format: 'a4' });

            const pageWidth = doc.internal.pageSize.getWidth ? doc.internal.pageSize.getWidth() : doc.internal.pageSize.width;
            const pageHeight = doc.internal.pageSize.getHeight ? doc.internal.pageSize.getHeight() : doc.internal.pageSize.height;

            const marginX = 40;
            const marginY = 40;
            const maxWidth = pageWidth - (marginX * 2);
            const lineHeight = 14;

            let y = marginY;

            const now = new Date();

            function buildFilename() {
                const yyyy = String(now.getFullYear());
                const mm = String(now.getMonth() + 1).padStart(2, '0');
                const dd = String(now.getDate()).padStart(2, '0');
                let name = 'chat-export-' + yyyy + '-' + mm + '-' + dd;

                if (conversationId) {
                    const safeId = String(conversationId).replace(/[^\w-]+/g, '').slice(0, 40);
                    if (safeId) {
                        name += '-' + safeId;
                    }
                }

                return name + '.pdf';
            }

            function normalizePdfText(text) {
                return String(text || '')
                    .replace(/\u00A0/g, ' ')
                    .replace(/[\u200B\u200C\u200D\uFEFF]/g, '')
                    .replace(/\u2022/g, '-')
                    .replace(/[\u2018\u2019]/g, "'")
                    .replace(/[\u201C\u201D]/g, '"')
                    .replace(/[\u2013\u2014]/g, '-')
                    .replace(/\u2026/g, '...')
                    .replace(/\r\n?/g, '\n')
                    .trim();
            }

            function docTextSafe(text, x, yPos) {
                try {
                    doc.text(text, x, yPos);
                } catch (e) {
                    doc.text(String(text || '').replace(/[^\x09\x0A\x0D\x20-\x7E]/g, '?'), x, yPos);
                }
            }

            const titleEl = document.querySelector('.humata-chat-title');
            const title = ((titleEl ? titleEl.textContent : '') || 'Chat Export').trim();

            doc.setFont('helvetica', 'bold');
            doc.setFontSize(16);
            docTextSafe(title || 'Chat Export', marginX, y);
            y += 22;

            doc.setFont('helvetica', 'normal');
            doc.setFontSize(10);
            docTextSafe(now.toLocaleString(), marginX, y);
            y += 24;

            const messageEls = elements.messages ? elements.messages.querySelectorAll('.humata-message:not(#humata-welcome-message):not(.humata-message-loading)') : [];
            const messages = [];

            messageEls.forEach(function(el) {
                const contentEl = el.querySelector('.humata-message-content');
                if (!contentEl) {
                    return;
                }

                const isUser = el.classList.contains('humata-message-user');
                const isError = el.classList.contains('humata-message-error');

                let text = '';
                if (isUser) {
                    text = getMessagePlainText(contentEl);
                } else {
                    const html = getMessageHtmlForStorage(contentEl);
                    text = htmlFragmentToPlainText(html);
                }

                text = normalizePdfText(text);
                if (!text) {
                    return;
                }

                // Collect intent links if present on bot messages
                let intentLinks = null;
                if (!isUser && !isError && el._humataIntentLinks && el._humataIntentLinks.length) {
                    intentLinks = el._humataIntentLinks;
                }

                // Collect accordions if present on bot messages
                let intentAccordions = null;
                if (!isUser && !isError && el._humataIntentAccordions && el._humataIntentAccordions.length) {
                    intentAccordions = el._humataIntentAccordions;
                }

                messages.push({
                    role: isUser ? 'You' : 'Assistant',
                    text: text,
                    isError: isError,
                    intentLinks: intentLinks,
                    intentAccordions: intentAccordions
                });
            });

            // Track seen accordion titles globally to avoid duplicates in PDF
            const seenAccordionTitles = new Set();

            if (!messages.length) {
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(12);
                docTextSafe('No messages.', marginX, y);
                doc.save(buildFilename());
                return;
            }

            messages.forEach(function(msg) {
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(13);

                const label = msg.role + (msg.isError ? ' (error)' : '') + ':';
                if (y + lineHeight > pageHeight - marginY) {
                    doc.addPage();
                    y = marginY;
                }
                docTextSafe(label, marginX, y);
                y += lineHeight;

                doc.setFont('helvetica', 'normal');
                doc.setFontSize(12);

                const lines = doc.splitTextToSize(msg.text, maxWidth);
                for (let i = 0; i < lines.length; i++) {
                    if (y + lineHeight > pageHeight - marginY) {
                        doc.addPage();
                        y = marginY;
                    }
                    docTextSafe(lines[i], marginX, y);
                    y += lineHeight;
                }

                // Render accordions if present (deduplicated globally)
                if (msg.intentAccordions && msg.intentAccordions.length) {
                    // Filter to only accordions we haven't seen yet
                    const newAccordions = msg.intentAccordions.filter(function(acc) {
                        if (seenAccordionTitles.has(acc.title)) {
                            return false;
                        }
                        seenAccordionTitles.add(acc.title);
                        return true;
                    });

                    if (newAccordions.length) {
                        y += 4;

                        for (let a = 0; a < newAccordions.length; a++) {
                            const acc = newAccordions[a];

                            // Accordion title (bold)
                            doc.setFont('helvetica', 'bold');
                            doc.setFontSize(11);
                            if (y + lineHeight > pageHeight - marginY) {
                                doc.addPage();
                                y = marginY;
                            }
                            docTextSafe('▸ ' + acc.title, marginX, y);
                            y += lineHeight;

                            // Accordion content (normal, indented)
                            doc.setFont('helvetica', 'normal');
                            doc.setFontSize(11);
                            // Strip HTML tags and convert to plain text
                            const plainContent = acc.content
                                .replace(/<br\s*\/?>/gi, '\n')
                                .replace(/<\/p>/gi, '\n')
                                .replace(/<[^>]+>/g, '')
                                .replace(/&nbsp;/g, ' ')
                                .replace(/&amp;/g, '&')
                                .replace(/&lt;/g, '<')
                                .replace(/&gt;/g, '>')
                                .replace(/&quot;/g, '"')
                                .trim();
                            const contentLines = doc.splitTextToSize(plainContent, maxWidth - 10);
                            for (let c = 0; c < contentLines.length; c++) {
                                if (y + lineHeight > pageHeight - marginY) {
                                    doc.addPage();
                                    y = marginY;
                                }
                                docTextSafe('  ' + contentLines[c], marginX, y);
                                y += lineHeight;
                            }
                            y += 2;
                        }
                    }
                }

                // Render intent links if present
                if (msg.intentLinks && msg.intentLinks.length) {
                    y += 4;
                    doc.setFont('helvetica', 'italic');
                    doc.setFontSize(11);

                    const resourceLabel = config.i18n?.relatedResources || 'Related resources:';
                    if (y + lineHeight > pageHeight - marginY) {
                        doc.addPage();
                        y = marginY;
                    }
                    docTextSafe(resourceLabel, marginX, y);
                    y += lineHeight;

                    doc.setFont('helvetica', 'normal');
                    for (let k = 0; k < msg.intentLinks.length; k++) {
                        const link = msg.intentLinks[k];
                        const linkText = '• ' + link.title + ' - ' + link.url;
                        const linkLines = doc.splitTextToSize(linkText, maxWidth);
                        for (let l = 0; l < linkLines.length; l++) {
                            if (y + lineHeight > pageHeight - marginY) {
                                doc.addPage();
                                y = marginY;
                            }
                            docTextSafe(linkLines[l], marginX, y);
                            y += lineHeight;
                        }
                    }
                }

                y += lineHeight;
            });

            doc.save(buildFilename());
        } catch (e) {
            console.error(e);
        } finally {
            if (exportButton) {
                exportButton.disabled = false;
            }
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
            const welcomeDiv = document.createElement('div');
            welcomeDiv.id = 'humata-welcome-message';
            welcomeDiv.className = 'humata-message humata-message-bot';

            const avatarDiv = document.createElement('div');
            avatarDiv.className = 'humata-message-avatar';
            setAvatarContent(avatarDiv, 'bot');

            const contentDiv = document.createElement('div');
            contentDiv.className = 'humata-message-content';
            contentDiv.innerHTML = '<p>' + (config.i18n?.welcome || "Hello! I'm here to help answer your questions. What would you like to know?") + '</p>';

            welcomeDiv.appendChild(avatarDiv);
            welcomeDiv.appendChild(contentDiv);
            elements.messages.appendChild(welcomeDiv);

            elements.welcomeMessage = welcomeDiv;
            ensureCopyButton(contentDiv);
        }

        // Clear localStorage
        localStorage.removeItem(STORAGE_KEY);
        localStorage.removeItem(CONVERSATION_KEY);
        conversationId = null;

        // Focus input
        if (elements.input) {
            elements.input.focus();
        }

        // Re-initialize suggested questions since chat is now empty
        initSuggestedQuestionsIfEmpty();
    }

    /**
     * Save chat history to localStorage.
     */
    function saveHistory() {
        if (!elements.messages) return;

        const messages = [];
        const messageElements = elements.messages.querySelectorAll('.humata-message:not(#humata-welcome-message):not(.humata-message-loading)');
        let lastUserMessage = '';

        messageElements.forEach(function(el) {
            const isUser = el.classList.contains('humata-message-user');
            const isError = el.classList.contains('humata-message-error');
            const content = el.querySelector('.humata-message-content');

            if (content) {
                const msgData = {
                    type: isUser ? 'user' : 'bot',
                    content: isUser ? getMessagePlainText(content) : getMessageHtmlForStorage(content),
                    isError: isError
                };

                if (isUser) {
                    lastUserMessage = msgData.content;
                } else {
                    // Store the user message that triggered this bot response for intent links
                    msgData.triggerUserMessage = lastUserMessage;
                    
                    // Store follow-up questions if present on this message
                    if (el._humataFollowUpQuestions && Array.isArray(el._humataFollowUpQuestions)) {
                        msgData.followUpQuestions = el._humataFollowUpQuestions;
                    }
                }

                messages.push(msgData);
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
                setAvatarContent(avatarDiv, msg.type);

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

                // Re-apply intent links for bot messages
                if (msg.type === 'bot' && !msg.isError && msg.triggerUserMessage) {
                    appendIntentLinksToMessage(messageDiv, msg.triggerUserMessage);
                }

                // Store follow-up questions on element for later restoration
                if (msg.type === 'bot' && !msg.isError && msg.followUpQuestions && Array.isArray(msg.followUpQuestions)) {
                    messageDiv._humataFollowUpQuestions = msg.followUpQuestions;
                }
            });

            // Restore follow-up questions for the last bot message only
            const lastBotMessage = elements.messages.querySelector('.humata-message-bot:last-of-type:not(.humata-message-error)');
            if (lastBotMessage && lastBotMessage._humataFollowUpQuestions && lastBotMessage._humataFollowUpQuestions.length > 0) {
                renderFollowUpQuestions(lastBotMessage, lastBotMessage._humataFollowUpQuestions);
            }

            // Load conversation ID
            conversationId = localStorage.getItem(CONVERSATION_KEY);

            // Update bot response disclaimer position
            updateBotResponseDisclaimerPosition();

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
