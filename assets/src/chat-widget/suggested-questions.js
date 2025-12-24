/**
 * Suggested Questions Module
 *
 * Handles rendering and interaction of suggested question prompts.
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

import { config } from './config.js';

let suggestedQuestionsContainer = null;
let onQuestionClick = null;

/**
 * Initialize suggested questions.
 *
 * @param {Function} clickHandler Callback when a question is clicked.
 */
export function initSuggestedQuestions(clickHandler) {
    suggestedQuestionsContainer = document.getElementById('humata-suggested-questions');
    onQuestionClick = clickHandler;

    if (!suggestedQuestionsContainer) {
        return;
    }

    const sqConfig = config.suggestedQuestions;
    if (!sqConfig || !sqConfig.enabled) {
        return;
    }

    const questions = getQuestionsToDisplay(sqConfig);
    if (questions.length === 0) {
        return;
    }

    renderSuggestedQuestions(questions);
}

/**
 * Get questions to display based on mode.
 *
 * @param {Object} sqConfig Suggested questions configuration.
 * @returns {Array} Array of question strings.
 */
function getQuestionsToDisplay(sqConfig) {
    if (sqConfig.mode === 'fixed') {
        return getFixedQuestions(sqConfig.fixedQuestions || []);
    } else if (sqConfig.mode === 'randomized') {
        return getRandomizedQuestions(sqConfig.categories || []);
    }
    return [];
}

/**
 * Get fixed mode questions (already ordered).
 *
 * @param {Array} fixedQuestions Array of question objects with text property.
 * @returns {Array} Array of question strings.
 */
function getFixedQuestions(fixedQuestions) {
    return fixedQuestions
        .filter(q => q && q.text && q.text.trim() !== '')
        .slice(0, 4)
        .map(q => q.text);
}

/**
 * Get randomized questions from categories.
 *
 * Distribution:
 * - 1 category: all 4 slots
 * - 2 categories: 2 slots each
 * - 3 categories: one random category gets 2 slots
 * - 4 categories: 1 slot each
 *
 * @param {Array} categories Array of category objects.
 * @returns {Array} Array of question strings (max 4).
 */
function getRandomizedQuestions(categories) {
    const validCategories = categories.filter(
        cat => cat && cat.questions && cat.questions.length > 0
    );

    if (validCategories.length === 0) {
        return [];
    }

    const slots = [];
    const numCategories = validCategories.length;

    if (numCategories === 1) {
        // 1 category gets all 4 slots
        const cat = validCategories[0];
        const shuffled = shuffle([...cat.questions]);
        for (let i = 0; i < 4 && i < shuffled.length; i++) {
            slots.push(shuffled[i]);
        }
    } else if (numCategories === 2) {
        // 2 slots each
        for (let i = 0; i < 2; i++) {
            const cat = validCategories[i];
            const shuffled = shuffle([...cat.questions]);
            for (let j = 0; j < 2 && j < shuffled.length; j++) {
                slots.push(shuffled[j]);
            }
        }
    } else if (numCategories === 3) {
        // One random category gets 2 slots, others get 1
        const luckyIndex = Math.floor(Math.random() * 3);
        for (let i = 0; i < 3; i++) {
            const cat = validCategories[i];
            const shuffled = shuffle([...cat.questions]);
            const count = (i === luckyIndex) ? 2 : 1;
            for (let j = 0; j < count && j < shuffled.length; j++) {
                slots.push(shuffled[j]);
            }
        }
    } else {
        // 4+ categories: 1 slot each (use first 4)
        for (let i = 0; i < 4 && i < validCategories.length; i++) {
            const cat = validCategories[i];
            const shuffled = shuffle([...cat.questions]);
            if (shuffled.length > 0) {
                slots.push(shuffled[0]);
            }
        }
    }

    // Shuffle final slots for variety in position
    return shuffle(slots).slice(0, 4);
}

/**
 * Fisher-Yates shuffle.
 *
 * @param {Array} array Array to shuffle.
 * @returns {Array} Shuffled array.
 */
function shuffle(array) {
    for (let i = array.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [array[i], array[j]] = [array[j], array[i]];
    }
    return array;
}

/**
 * Render suggested questions in the container.
 *
 * @param {Array} questions Array of question strings.
 */
function renderSuggestedQuestions(questions) {
    if (!suggestedQuestionsContainer || questions.length === 0) {
        return;
    }

    suggestedQuestionsContainer.innerHTML = '';

    questions.forEach(question => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'humata-suggested-question-btn';
        button.setAttribute('title', question);

        const span = document.createElement('span');
        span.className = 'humata-suggested-question-text';
        span.textContent = question;
        button.appendChild(span);

        button.addEventListener('click', () => {
            handleQuestionClick(question);
        });

        suggestedQuestionsContainer.appendChild(button);
    });

    suggestedQuestionsContainer.style.display = '';
}

/**
 * Handle click on a suggested question.
 *
 * @param {string} question The question text.
 */
function handleQuestionClick(question) {
    hideSuggestedQuestions();

    if (typeof onQuestionClick === 'function') {
        onQuestionClick(question);
    }
}

/**
 * Hide the suggested questions container.
 */
export function hideSuggestedQuestions() {
    if (suggestedQuestionsContainer) {
        suggestedQuestionsContainer.style.display = 'none';
        suggestedQuestionsContainer.innerHTML = '';
    }
}

/**
 * Check if suggested questions are currently visible.
 *
 * @returns {boolean} True if visible.
 */
export function areSuggestedQuestionsVisible() {
    return suggestedQuestionsContainer &&
           suggestedQuestionsContainer.style.display !== 'none' &&
           suggestedQuestionsContainer.children.length > 0;
}
