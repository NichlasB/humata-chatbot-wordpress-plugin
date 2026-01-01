/**
 * Follow-Up Questions Module
 *
 * Handles rendering and interaction of AI-generated follow-up question prompts
 * that appear after bot responses.
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

let onQuestionClick = null;
let currentContainer = null;
let expandedButton = null;
let isTouchDevice = false;

/**
 * Initialize follow-up questions module.
 *
 * @param {Function} clickHandler Callback when a question is clicked.
 */
export function initFollowUpQuestions(clickHandler) {
    onQuestionClick = clickHandler;
    
    // Detect touch device
    isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
}

/**
 * Render follow-up questions after a bot message.
 *
 * @param {HTMLElement} messageElement The bot message element to attach questions to.
 * @param {Array} questions Array of question strings.
 */
export function renderFollowUpQuestions(messageElement, questions) {
    // Remove any existing follow-up questions first.
    hideFollowUpQuestions();

    if (!messageElement || !questions || !Array.isArray(questions) || questions.length === 0) {
        return;
    }

    // Create container.
    const container = document.createElement('div');
    container.className = 'humata-followup-questions';
    container.setAttribute('role', 'group');
    container.setAttribute('aria-label', 'Follow-up questions');

    // Add label.
    const label = document.createElement('span');
    label.className = 'humata-followup-questions-label';
    label.textContent = 'Follow-up questions:';
    container.appendChild(label);

    // Create buttons container.
    const buttonsContainer = document.createElement('div');
    buttonsContainer.className = 'humata-followup-questions-buttons';

    questions.forEach((question, index) => {
        if (!question || typeof question !== 'string') {
            return;
        }

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'humata-followup-question-btn';
        button.setAttribute('title', question);
        button.setAttribute('data-index', index);

        const span = document.createElement('span');
        span.className = 'humata-followup-question-text';
        span.textContent = question;
        button.appendChild(span);

        // Check if text will be truncated (rough estimate based on character count)
        const isTruncated = question.length > 50;

        button.addEventListener('click', (e) => {
            // On touch devices with truncated text: first tap expands, second tap sends
            if (isTouchDevice && isTruncated && !button.classList.contains('humata-expanded')) {
                e.preventDefault();
                collapseExpandedButton();
                button.classList.add('humata-expanded');
                expandedButton = button;
                return;
            }
            handleQuestionClick(question);
        });

        buttonsContainer.appendChild(button);
    });

    container.appendChild(buttonsContainer);

    // Insert after the message content (but inside the message wrapper).
    const messageContent = messageElement.querySelector('.humata-message-content');
    if (messageContent) {
        // Insert after any disclaimer (at the end of message content).
        messageContent.appendChild(container);
    } else {
        messageElement.appendChild(container);
    }

    currentContainer = container;
}

/**
 * Collapse any currently expanded button.
 */
function collapseExpandedButton() {
    if (expandedButton) {
        expandedButton.classList.remove('humata-expanded');
        expandedButton = null;
    }
}

/**
 * Handle click on a follow-up question.
 *
 * @param {string} question The question text.
 */
function handleQuestionClick(question) {
    hideFollowUpQuestions();

    if (typeof onQuestionClick === 'function') {
        onQuestionClick(question);
    }
}

/**
 * Hide and remove the current follow-up questions container.
 */
export function hideFollowUpQuestions() {
    if (currentContainer && currentContainer.parentNode) {
        currentContainer.parentNode.removeChild(currentContainer);
    }
    currentContainer = null;

    // Also remove any orphaned containers.
    const orphaned = document.querySelectorAll('.humata-followup-questions');
    orphaned.forEach(el => {
        if (el.parentNode) {
            el.parentNode.removeChild(el);
        }
    });
}

/**
 * Check if follow-up questions are currently visible.
 *
 * @returns {boolean} True if visible.
 */
export function areFollowUpQuestionsVisible() {
    return currentContainer !== null && currentContainer.parentNode !== null;
}
