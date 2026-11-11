/* eslint no-console: 0 */

/**
 * Shows which Twig changes the hot reload could not apply, so the page never looks updated when it is not.
 *
 * @sw-package framework
 * @private
 */
import { PRESERVE_ATTRIBUTE } from './ownership';

export const NOTICE_ID = 'sw-twig-hmr-notice';

const LOG_PREFIX = '[sw-twig-hmr]';

/**
 * Reasons stay until the page reloads, because the DOM keeps the outdated markup until then.
 */
const reloadReasons = new Set();
let renderError = null;

// The Symfony toolbar and its minimized form are fixed to the bottom edge, the notice sits above whichever is shown.
const TOOLBAR_SELECTOR = '.sf-toolbarreset, .sf-minitoolbar';

const STYLE = [
    'position:fixed',
    'left:1rem',
    'z-index:2147483647',
    'max-width:28rem',
    'padding:.75rem 1rem',
    'border-radius:.375rem',
    'background:#212529',
    'color:#fff',
    'font:14px/1.4 system-ui,sans-serif',
    'box-shadow:0 .5rem 1rem rgba(0,0,0,.3)',
].join(';');

function toolbarHeight() {
    const heights = [...document.querySelectorAll(TOOLBAR_SELECTOR)].map((element) => element.getBoundingClientRect().height);

    return Math.max(0, ...heights);
}

function createButton(label, onClick) {
    const button = document.createElement('button');
    button.type = 'button';
    button.textContent = label;
    button.style.cssText = 'margin:.5rem .5rem 0 0;padding:.25rem .75rem;border:0;border-radius:.25rem;cursor:pointer';
    button.addEventListener('click', onClick);

    return button;
}

function render() {
    document.getElementById(NOTICE_ID)?.remove();

    if (reloadReasons.size === 0 && !renderError) {
        return;
    }

    const notice = document.createElement('div');
    notice.id = NOTICE_ID;
    notice.setAttribute(PRESERVE_ATTRIBUTE, '');
    notice.setAttribute('role', 'status');
    notice.style.cssText = STYLE;
    notice.style.bottom = `calc(${toolbarHeight()}px + 1rem)`;

    const title = document.createElement('strong');
    title.textContent = renderError
        ? 'Twig hot reload: the template could not be rendered'
        : 'Twig hot reload: reload required';
    notice.append(title);

    const list = document.createElement('ul');
    list.style.cssText = 'margin:.5rem 0 0;padding-left:1.25rem';
    [...(renderError ? [renderError] : []), ...reloadReasons].forEach((reason) => {
        const item = document.createElement('li');
        item.textContent = reason;
        list.append(item);
    });
    notice.append(list);

    if (reloadReasons.size > 0) {
        notice.append(createButton('Reload page', () => window.location.reload()));
    }
    notice.append(createButton('Dismiss', () => notice.remove()));

    document.body.append(notice);
}

/**
 * @param {string[]} reasons - changes that only a reload applies
 */
export function addReloadReasons(reasons) {
    const added = reasons.filter((reason) => !reloadReasons.has(reason));

    if (added.length === 0) {
        return;
    }

    added.forEach((reason) => {
        reloadReasons.add(reason);
        console.warn(`${LOG_PREFIX} reload required: ${reason}`);
    });
    render();
}

/**
 * @param {string|null} message - the render error, null after a successful update
 */
export function setRenderError(message) {
    if (renderError === message) {
        return;
    }

    renderError = message;
    render();
}

export function resetNotice() {
    reloadReasons.clear();
    renderError = null;
    document.getElementById(NOTICE_ID)?.remove();
}
