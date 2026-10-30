/* eslint no-console: 0 */

/**
 * Applies Twig changes to the open page without a reload.
 *
 * The page and every open AJAX modal are rendered again on the server.
 * The new markup is morphed into the live DOM, so state that only exists in the browser survives:
 * open modals, offcanvas and collapse elements, typed form values and focus.
 *
 * @sw-package framework
 * @private
 */
import { Idiomorph } from 'idiomorph';

export const LOG_PREFIX = '[sw-twig-hmr]';

/**
 * Classes that Bootstrap and Storefront plugins toggle at runtime.
 * The server never renders them, so a morph would remove them.
 */
const STATE_CLASSES = ['show', 'showing', 'hiding', 'collapsing', 'is-open', 'modal-open', 'offcanvas-open'];

/**
 * Attributes that Bootstrap and Storefront plugins change at runtime on stateful elements.
 */
const STATE_ATTRIBUTES = ['style', 'aria-expanded', 'aria-hidden', 'aria-modal', 'role', 'tabindex', 'inert'];

/**
 * Form state typed or chosen by the user.
 */
const FORM_STATE_ATTRIBUTES = ['value', 'checked', 'selected'];

const STATEFUL_SELECTOR = [
    '.modal',
    '.offcanvas',
    '.collapse',
    '.dropdown-menu',
    '.dropdown-toggle',
    '[data-bs-toggle]',
    '.tab-pane',
    '.nav-link',
].join(',');

/**
 * Elements that scripts add at runtime, such as backdrops, pseudo modals, offcanvas and toasts.
 */
const CLIENT_OWNED_SELECTOR = [
    '.modal-backdrop',
    '.offcanvas-backdrop',
    '.js-pseudo-modal',
    '.js-off-canvas',
    '[data-sw-twig-hmr-preserve]',
].join(',');

const AJAX_MODAL_SELECTOR = '.js-pseudo-modal .modal[data-sw-source-url]';

let isRefreshing = false;
let pendingFiles = null;

function isStateful(element) {
    return element instanceof Element && element.matches(STATEFUL_SELECTOR);
}

function mergeStateClasses(oldElement, newClassValue) {
    const classes = new Set((newClassValue || '').split(/\s+/).filter(Boolean));

    STATE_CLASSES
        .filter((cls) => oldElement.classList.contains(cls))
        .forEach((cls) => classes.add(cls));

    return [...classes].join(' ');
}

export const morphCallbacks = {
    beforeNodeRemoved(node) {
        if (!(node instanceof Element)) {
            return true;
        }

        // Scripts append their elements directly to the body.
        // Trade-off: removing a direct body child in a template needs a manual reload.
        if (node.parentNode === document.body) {
            return false;
        }

        return !node.matches(CLIENT_OWNED_SELECTOR);
    },

    beforeNodeMorphed(oldNode, newNode) {
        if (!(oldNode instanceof Element) || !(newNode instanceof Element)) {
            return true;
        }

        if (isStateful(oldNode) && newNode.hasAttribute('class')) {
            newNode.setAttribute('class', mergeStateClasses(oldNode, newNode.getAttribute('class')));
        }

        return true;
    },

    beforeAttributeUpdated(attributeName, element) {
        if (FORM_STATE_ATTRIBUTES.includes(attributeName) && element.matches('input, textarea, select, option')) {
            return false;
        }

        return !(isStateful(element) && STATE_ATTRIBUTES.includes(attributeName));
    },
};

/**
 * `ignoreActiveValue` is not used: idiomorph then skips all children of the focused element.
 * Bootstrap focuses the `.modal` element itself, so an open modal would never update.
 */
const morphOptions = {
    morphStyle: 'innerHTML',
    restoreFocus: true,
    callbacks: morphCallbacks,
};

async function fetchHtml(url, headers = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {
            Accept: 'text/html',
            'sw-twig-hmr': '1',
            ...headers,
        },
    });

    const html = await response.text();

    if (!response.ok) {
        // DOMParser decodes HTML entities in the Symfony error title.
        const title = new DOMParser().parseFromString(html, 'text/html').title.trim();

        throw new Error(`${LOG_PREFIX} ${url} answered with HTTP ${response.status}: ${title || 'server error'}`);
    }

    return html;
}

async function refreshPage() {
    const html = await fetchHtml(window.location.href);
    const newDocument = new DOMParser().parseFromString(html, 'text/html');

    if (newDocument.title && newDocument.title !== document.title) {
        document.title = newDocument.title;
    }

    // Idiomorph wraps a single element in a dummy parent.
    // Passing the body element would nest a second body inside the live one.
    Idiomorph.morph(document.body, newDocument.body.childNodes, morphOptions);
}

/**
 * Mirrors `PseudoModalUtil._setModalContent()`, but morphs instead of replacing the markup.
 */
async function refreshAjaxModal(modal) {
    const html = await fetchHtml(modal.dataset.swSourceUrl, { 'X-Requested-With': 'XMLHttpRequest' });
    const container = document.createElement('div');
    container.innerHTML = html;

    const newRoot = container.querySelector('.js-pseudo-modal-template-root-element');
    const currentRoot = modal.querySelector('.js-pseudo-modal-template-root-element');

    if (newRoot && currentRoot) {
        Idiomorph.morph(currentRoot, newRoot.childNodes, morphOptions);

        return;
    }

    const newTitle = container.querySelector('.js-pseudo-modal-template-title-element');
    const currentTitle = modal.querySelector('.js-pseudo-modal-template-title-element');
    if (newTitle) {
        if (currentTitle) {
            currentTitle.innerHTML = newTitle.innerHTML;
        }
        newTitle.remove();
    }

    const content = modal.querySelector('.js-pseudo-modal-template-content-element');
    if (content) {
        Idiomorph.morph(content, container.childNodes, morphOptions);
    }
}

/**
 * Idiomorph only restores the focus of elements with an id. Most Storefront inputs have none.
 */
function rememberFocus() {
    const element = document.activeElement;
    const hasSelection = element && typeof element.selectionStart === 'number';
    const selection = hasSelection ? [element.selectionStart, element.selectionEnd] : null;

    return () => {
        if (!element || element === document.body || !element.isConnected || document.activeElement === element) {
            return;
        }

        element.focus({ preventScroll: true });
        if (selection) {
            element.setSelectionRange(...selection);
        }
    };
}

/**
 * Renders the page and all open AJAX modals again and morphs them into the DOM.
 * Calls during a running refresh are queued and run once afterwards.
 *
 * @param {string[]} files - changed Twig files, only used for logging and the event
 * @returns {Promise<void>}
 */
export async function refresh(files) {
    if (isRefreshing) {
        pendingFiles = [...(pendingFiles || []), ...files];

        return;
    }

    isRefreshing = true;
    const start = performance.now();
    const restoreFocus = rememberFocus();

    try {
        await refreshPage();

        for (const modal of document.querySelectorAll(AJAX_MODAL_SELECTOR)) {
            await refreshAjaxModal(modal);
        }

        await window.PluginManager?.initializePlugins();
        restoreFocus();

        document.dispatchEvent(new CustomEvent('sw-twig-hmr:updated', { detail: { files } }));
        console.info(`${LOG_PREFIX} updated in ${Math.round(performance.now() - start)} ms`, files);
    } catch (error) {
        // Keep the current DOM, so a Twig syntax error does not close the modal. The next save retries.
        console.error(error.message || error);
    } finally {
        isRefreshing = false;

        if (pendingFiles) {
            const next = pendingFiles;
            pendingFiles = null;
            await refresh(next);
        }
    }
}
