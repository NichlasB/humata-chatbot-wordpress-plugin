/**
 * Floating Help Menu
 *
 * Frontend behavior for the floating help button/menu and modals.
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

(function() {
    'use strict';

    const root = document.getElementById('humata-floating-help');
    if (!root) {
        return;
    }

    const wrapper = root.querySelector('.humata-floating-help__wrapper');
    const button = root.querySelector('.humata-floating-help__button');
    const panel = root.querySelector('.humata-floating-help__panel');

    function isTouchMode() {
        try {
            return !!(window.matchMedia && window.matchMedia('(hover: none), (pointer: coarse)').matches);
        } catch (e) {
            return ('ontouchstart' in window);
        }
    }

    let touchMode = isTouchMode();
    let lastFocused = null;

    function setMenuExpanded(expanded) {
        if (!button) return;
        button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        if (panel) {
            panel.setAttribute('aria-hidden', expanded ? 'false' : 'true');
        }
    }

    function openMenu() {
        if (!wrapper) return;
        wrapper.classList.add('is-open');
        setMenuExpanded(true);
    }

    function closeMenu() {
        if (!wrapper) return;
        wrapper.classList.remove('is-open');
        setMenuExpanded(false);
    }

    function toggleMenu() {
        if (!wrapper) return;
        if (wrapper.classList.contains('is-open')) {
            closeMenu();
        } else {
            openMenu();
        }
    }

    function getModal(modalId) {
        return document.getElementById('humata-help-modal-' + modalId);
    }

    function openModal(modalId, triggerEl) {
        const modal = getModal(modalId);
        if (!modal) return;

        lastFocused = triggerEl || document.activeElement;

        modal.hidden = false;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');

        document.documentElement.classList.add('humata-help-modal-open');
        document.body.classList.add('humata-help-modal-open');

        const closeBtn = modal.querySelector('.humata-help-modal__close');
        if (closeBtn) {
            try {
                closeBtn.focus({ preventScroll: true });
            } catch (e) {
                closeBtn.focus();
            }
        }
    }

    function closeModal(modal) {
        if (!modal) return;

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        modal.hidden = true;

        document.documentElement.classList.remove('humata-help-modal-open');
        document.body.classList.remove('humata-help-modal-open');

        if (lastFocused && typeof lastFocused.focus === 'function') {
            try {
                lastFocused.focus({ preventScroll: true });
            } catch (e) {
                lastFocused.focus();
            }
        }
        lastFocused = null;
    }

    function closeAnyModal() {
        const open = document.querySelector('.humata-help-modal.is-open');
        if (open) {
            closeModal(open);
            return true;
        }
        return false;
    }

    function initTouchMenuBehavior() {
        if (!button || !wrapper) return;

        button.addEventListener('click', function(e) {
            if (!touchMode) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            toggleMenu();
        });

        // Close menu on outside click (touch mode only).
        document.addEventListener('click', function(e) {
            if (!touchMode) {
                return;
            }
            if (!wrapper.contains(e.target)) {
                closeMenu();
            }
        }, true);
    }

    function initModalBehavior() {
        // Open modal from menu triggers.
        root.addEventListener('click', function(e) {
            const trigger = e.target.closest('[data-humata-help-open-modal]');
            if (!trigger) {
                return;
            }
            e.preventDefault();

            const modalId = String(trigger.getAttribute('data-humata-help-open-modal') || '').trim();
            if (!modalId) {
                return;
            }

            if (touchMode) {
                closeMenu();
            }

            openModal(modalId, trigger);
        });

        // Close modal on overlay click or close button.
        document.addEventListener('click', function(e) {
            const closeTarget = e.target.closest('[data-humata-help-close-modal]');
            if (!closeTarget) {
                return;
            }
            const modal = closeTarget.closest('.humata-help-modal');
            if (!modal) {
                return;
            }
            e.preventDefault();
            closeModal(modal);
        });

        // ESC closes modal (preferred) or the touch menu if open.
        document.addEventListener('keydown', function(e) {
            if (e.key !== 'Escape') {
                return;
            }

            if (closeAnyModal()) {
                e.preventDefault();
                return;
            }

            if (touchMode && wrapper && wrapper.classList.contains('is-open')) {
                closeMenu();
            }
        });
    }

    // Keep touchMode fresh on resize/orientation changes.
    window.addEventListener('resize', function() {
        const nowTouch = isTouchMode();
        if (nowTouch !== touchMode) {
            touchMode = nowTouch;
            // Ensure we don't leave the menu stuck open if the device mode changes.
            closeMenu();
        }
    });

    // Init aria state
    setMenuExpanded(false);

    initTouchMenuBehavior();
    initModalBehavior();
})();


