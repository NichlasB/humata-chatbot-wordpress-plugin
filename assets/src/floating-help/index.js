// Entry point for the bundled floating help script (Phase 0).
// For now we reuse the existing implementation in assets/js/.
import '../../js/floating-help.js';

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

function initFloatingHelpAriaSync() {
    const root = document.getElementById('humata-floating-help');
    if (!root) {
        return;
    }

    const wrapper = root.querySelector('.humata-floating-help__wrapper');
    const button = root.querySelector('.humata-floating-help__button');
    const panel = root.querySelector('.humata-floating-help__panel');

    if (!wrapper || !button || !panel) {
        return;
    }

    function setExpanded(expanded) {
        button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        panel.setAttribute('aria-hidden', expanded ? 'false' : 'true');
    }

    function computeExpanded() {
        try {
            return (
                wrapper.classList.contains('is-open') ||
                wrapper.matches(':hover') ||
                wrapper.matches(':focus-within')
            );
        } catch (e) {
            return wrapper.classList.contains('is-open');
        }
    }

    let lastExpanded = null;

    function syncExpanded() {
        const expanded = computeExpanded();
        if (expanded === lastExpanded) {
            return;
        }
        lastExpanded = expanded;
        setExpanded(expanded);
    }

    syncExpanded();

    wrapper.addEventListener('mouseenter', syncExpanded);
    wrapper.addEventListener('mouseleave', syncExpanded);
    wrapper.addEventListener('focusin', syncExpanded);
    wrapper.addEventListener('focusout', function() {
        setTimeout(syncExpanded, 0);
    });
    button.addEventListener('click', function() {
        setTimeout(syncExpanded, 0);
    });
    document.addEventListener('click', function() {
        setTimeout(syncExpanded, 0);
    }, true);
}

installModalFocusTrap();
initFloatingHelpAriaSync();
