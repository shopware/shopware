/* eslint no-console: 0 */

/**
 * Applies Twig changes to the open page without a reload. Experimental and opt-in.
 *
 * A refresh has two phases:
 * 1. Prepare: render the page and every open AJAX modal and offcanvas again and validate all responses.
 *    A failed render or an unexpected redirect stops the refresh before the DOM changes.
 * 2. Apply: morph all responses into the DOM in one synchronous step.
 *
 * Plugin elements are never morphed. Changed plugin markup is listed in a "reload required" notice instead.
 *
 * @sw-package framework
 * @private
 */
import { Idiomorph } from 'idiomorph';
import { createOwnership, PRESERVE_ATTRIBUTE } from './ownership';
import { planPluginElements, describeElement } from './plugin-policy';
import { createInteractionState } from './interaction-state';
import { addReloadReasons, setRenderError, resetNotice } from './notice';

export const LOG_PREFIX = '[sw-twig-hmr]';

const AJAX_MODAL_SELECTOR = '.js-pseudo-modal .modal[data-sw-source-url]';
const AJAX_OFFCANVAS_SELECTOR = '.js-offcanvas-singleton[data-sw-source-url]';
const FRAGMENT_SELECTOR = `${AJAX_MODAL_SELECTOR}, ${AJAX_OFFCANVAS_SELECTOR}`;
const FRAGMENT_HEADERS = { 'X-Requested-With': 'XMLHttpRequest' };

/**
 * @typedef {object} Session
 * @property {ReturnType<typeof createOwnership>} ownership
 * @property {ReturnType<typeof createInteractionState>} interaction
 * @property {Element|null} pageBaseline - the server markup of the body that the page shows
 * @property {string} headSignature
 * @property {WeakMap<Element, {url: string, promise: Promise<string|null>}>} fragmentBaselines
 * @property {MutationObserver|null} sourceObserver
 */

/** @type {Session|null} */
let session = null;
let isRefreshing = false;
let pendingFiles = null;

function getHeadSignature(doc) {
    return [...doc.head.querySelectorAll('meta, link')].map((element) => element.outerHTML).join('\n');
}

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

    // A redirect, for example to the login page after the session expired, renders a different page.
    if (response.redirected) {
        throw new Error(`${LOG_PREFIX} ${url} redirected to ${response.url}`);
    }

    const contentType = response.headers?.get('content-type') ?? '';
    if (!contentType.includes('text/html')) {
        throw new Error(`${LOG_PREFIX} ${url} answered with ${contentType || 'an unknown content type'} instead of HTML`);
    }

    return html;
}

/**
 * Fragments have no server markup in the page, so the first render is fetched as soon as a fragment opens.
 */
function rememberFragmentBaseline(element) {
    const url = element.dataset.swSourceUrl;

    if (!session || !url || session.fragmentBaselines.get(element)?.url === url) {
        return;
    }

    session.fragmentBaselines.set(element, { url, promise: fetchHtml(url, FRAGMENT_HEADERS).catch(() => null) });
}

function observeFragmentSources() {
    const observer = new MutationObserver((records) => {
        records.forEach((record) => {
            const candidates = record.type === 'attributes'
                ? [record.target]
                : [...record.addedNodes].flatMap((node) => {
                    return node instanceof Element ? [node, ...node.querySelectorAll('[data-sw-source-url]')] : [];
                });

            candidates
                .filter((element) => element instanceof Element && element.matches(FRAGMENT_SELECTOR))
                .forEach(rememberFragmentBaseline);
        });
    });

    observer.observe(document.body, {
        subtree: true,
        childList: true,
        attributes: true,
        attributeFilter: ['data-sw-source-url'],
    });

    return observer;
}

/**
 * Starts tracking the state of the page. Call it before the Storefront plugins initialize,
 * so the body still holds the server markup and every element that scripts add is recorded.
 *
 * @param {{withBaseline?: boolean}} options
 * @returns {Session}
 */
export function init({ withBaseline = true } = {}) {
    if (session) {
        return session;
    }

    session = {
        ownership: createOwnership(document.body),
        interaction: createInteractionState(document),
        pageBaseline: withBaseline ? document.body.cloneNode(true) : null,
        headSignature: getHeadSignature(document),
        fragmentBaselines: new WeakMap(),
        sourceObserver: null,
    };
    session.sourceObserver = observeFragmentSources();
    document.querySelectorAll(FRAGMENT_SELECTOR).forEach(rememberFragmentBaseline);

    return session;
}

/**
 * Stops tracking. Only used by tests.
 */
export function reset() {
    if (session) {
        session.ownership.disconnect();
        session.interaction.disconnect();
        session.sourceObserver?.disconnect();
    }

    session = null;
    isRefreshing = false;
    pendingFiles = null;
    resetNotice();
}

function isOpen(element) {
    return element.isConnected && element.classList.contains('show');
}

function collectTargets() {
    const fragments = [...document.querySelectorAll(FRAGMENT_SELECTOR)]
        .filter(isOpen)
        .map((element) => ({
            kind: element.matches(AJAX_MODAL_SELECTOR) ? 'modal' : 'offcanvas',
            element,
            url: element.dataset.swSourceUrl,
        }));

    return [{ kind: 'page', element: document.body, url: window.location.href }, ...fragments];
}

function parseFragment(html) {
    const container = document.createElement('div');
    container.innerHTML = html;

    return container;
}

/**
 * Mirrors `PseudoModalUtil._setModalContent()` and `OffCanvas.setContent()`:
 * resolves the live element that receives a fragment and how to select the matching part of a response.
 */
function resolveFragmentRoot(target, nextContainer) {
    if (target.kind === 'offcanvas') {
        return { liveRoot: target.element, select: (container) => container, title: null };
    }

    const rootSelector = '.js-pseudo-modal-template-root-element';
    const liveRoot = target.element.querySelector(rootSelector);

    if (liveRoot && nextContainer.querySelector(rootSelector)) {
        return { liveRoot, select: (container) => container.querySelector(rootSelector), title: null };
    }

    return {
        liveRoot: target.element.querySelector('.js-pseudo-modal-template-content-element'),
        select: (container) => {
            container.querySelector('.js-pseudo-modal-template-title-element')?.remove();

            return container;
        },
        title: target.element.querySelector('.js-pseudo-modal-template-title-element'),
    };
}

async function prepareFragment(target, html) {
    const baseline = session.fragmentBaselines.get(target.element);
    const previousHtml = baseline?.url === target.url ? await baseline.promise : null;
    const nextContainer = parseFragment(html);
    const nextTitle = nextContainer.querySelector('.js-pseudo-modal-template-title-element');
    const { liveRoot, select, title } = resolveFragmentRoot(target, nextContainer);

    return {
        ...target,
        html,
        liveRoot,
        title,
        nextTitle: title ? nextTitle : null,
        nextRoot: select(nextContainer),
        previousRoot: previousHtml === null ? null : select(parseFragment(previousHtml)),
    };
}

/**
 * Phase 1: fetches and validates everything. Nothing in the DOM changes here.
 */
async function prepare() {
    const targets = collectTargets();
    const htmls = await Promise.all(targets.map((target) => {
        return fetchHtml(target.url, target.kind === 'page' ? {} : FRAGMENT_HEADERS);
    }));

    return Promise.all(targets.map((target, index) => {
        if (target.kind !== 'page') {
            return prepareFragment(target, htmls[index]);
        }

        const nextDocument = new DOMParser().parseFromString(htmls[index], 'text/html');

        return {
            ...target,
            document: nextDocument,
            liveRoot: target.element,
            nextRoot: nextDocument.body,
            previousRoot: session.pageBaseline,
            // Idiomorph moves the new nodes into the DOM, so the baseline is copied first.
            nextBaseline: nextDocument.body.cloneNode(true),
        };
    }));
}

function createMorphCallbacks(target, plan, reasons) {
    const insideFragment = target.kind !== 'page';
    const { ownership, interaction } = session;

    return {
        beforeNodeMorphed(oldNode, newNode) {
            if (!(oldNode instanceof Element) || oldNode === target.liveRoot) {
                return true;
            }

            if (ownership.isClientOwned(oldNode, insideFragment)) {
                if (!oldNode.hasAttribute(PRESERVE_ATTRIBUTE)) {
                    reasons.push(`New markup was matched with ${describeElement(oldNode)}, which a script added.`);
                }

                return false;
            }

            const entry = plan.get(oldNode);
            if (!entry) {
                return true;
            }

            const plugins = entry.pluginNames.join(', ');
            if (entry.changed) {
                reasons.push(`The markup of ${describeElement(oldNode)} changed. Plugins: ${plugins}.`);
            } else if (entry.expectedMarkup !== null && newNode.outerHTML !== entry.expectedMarkup) {
                reasons.push(`${describeElement(oldNode)} was matched with other markup. Plugins: ${plugins}.`);
            }

            // Plugins hold references to their children, so the morph never enters a plugin element.
            return false;
        },

        beforeNodeRemoved(node) {
            return !(node instanceof Element && ownership.isClientOwned(node, insideFragment));
        },

        beforeAttributeUpdated(attributeName, element) {
            return !interaction.isProtectedAttribute(attributeName, element);
        },
    };
}

function morphTarget(target, reasons) {
    if (!target.liveRoot || !target.nextRoot) {
        reasons.push(`The ${target.kind} of ${target.url} no longer has the expected structure.`);

        return;
    }

    const plan = planPluginElements({
        liveRoot: target.liveRoot,
        previousServerRoot: target.previousRoot,
        nextServerRoot: target.nextRoot,
        isExcluded: (element) => {
            return session.ownership.isInsideClientOwned(element, target.liveRoot, target.kind !== 'page');
        },
    });

    if (target.title && target.nextTitle) {
        target.title.innerHTML = target.nextTitle.innerHTML;
    }

    // Idiomorph wraps a single element in a dummy parent, so the children are passed.
    // `ignoreActiveValue` is not used: idiomorph then skips all children of the focused element,
    // and Bootstrap focuses the `.modal` element itself.
    Idiomorph.morph(target.liveRoot, target.nextRoot.childNodes, {
        morphStyle: 'innerHTML',
        restoreFocus: true,
        callbacks: createMorphCallbacks(target, plan, reasons),
    });
}

/**
 * Phase 2: applies all responses in one synchronous step.
 *
 * @returns {string[]} reasons why a reload is required
 */
function apply(targets) {
    const reasons = [];
    // A fragment that closed or loaded other content during the fetch keeps its DOM.
    const valid = targets.filter((target) => {
        return target.kind === 'page' || (isOpen(target.element) && target.element.dataset.swSourceUrl === target.url);
    });
    const restoreState = session.interaction.capture();

    session.ownership.withoutRecording(() => {
        valid.forEach((target) => morphTarget(target, reasons));
    });
    restoreState(reasons);

    const [page] = valid;
    if (page.document.title && page.document.title !== document.title) {
        document.title = page.document.title;
    }

    const headSignature = getHeadSignature(page.document);
    if (headSignature !== session.headSignature) {
        reasons.push('The <head> changed. Meta and link elements are only applied by a reload.');
    }

    session.headSignature = headSignature;
    session.pageBaseline = page.nextBaseline;
    valid.filter((target) => target.kind !== 'page').forEach((target) => {
        session.fragmentBaselines.set(target.element, { url: target.url, promise: Promise.resolve(target.html) });
    });

    return reasons;
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
 * Renders the page and all open AJAX modals and offcanvas again and morphs them into the DOM.
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

    // Without a baseline every plugin element counts as changed.
    init({ withBaseline: false });
    isRefreshing = true;
    const start = performance.now();

    try {
        const targets = await prepare();
        const restoreFocus = rememberFocus();
        const reasons = apply(targets);

        await window.PluginManager?.initializePlugins();
        restoreFocus();

        setRenderError(null);
        addReloadReasons(reasons);

        document.dispatchEvent(new CustomEvent('sw-twig-hmr:updated', { detail: { files, reloadRequired: reasons } }));
        console.info(`${LOG_PREFIX} updated in ${Math.round(performance.now() - start)} ms`, files);
    } catch (error) {
        // Nothing was applied, so the page keeps a consistent state. The next save retries.
        const message = error.message || String(error);

        console.error(message);
        setRenderError(message);
    } finally {
        isRefreshing = false;

        if (pendingFiles) {
            const next = pendingFiles;
            pendingFiles = null;
            await refresh(next);
        }
    }
}
