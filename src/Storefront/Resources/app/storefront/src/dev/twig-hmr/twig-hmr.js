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
 * A morph that would remove a plugin element, an element of a script or an open overlay is not applied at all.
 *
 * @sw-package framework
 * @private
 */
import { Idiomorph } from 'idiomorph';
import { createOwnership, removeServerPreserved, CLIENT_OWNED_SELECTOR, PRESERVE_ATTRIBUTE } from './ownership';
import { planPluginElements, describeElement, cloneWithoutTemporaryIds, TEMPORARY_ID_PREFIX } from './plugin-policy';
import { createInteractionState, findInertBackground, OVERLAY_SELECTOR } from './interaction-state';
import { addReloadReasons, setRenderError, resetNotice } from './notice';

export const LOG_PREFIX = '[sw-twig-hmr]';

const AJAX_MODAL_SELECTOR = '.js-pseudo-modal .modal[data-sw-source-url]';
const AJAX_OFFCANVAS_SELECTOR = '.js-offcanvas-singleton[data-sw-source-url]';
const FRAGMENT_SELECTOR = `${AJAX_MODAL_SELECTOR}, ${AJAX_OFFCANVAS_SELECTOR}`;
const FRAGMENT_HEADERS = { 'X-Requested-With': 'XMLHttpRequest' };
// Lets the server drop its compiled templates, because a compiled template keeps the parent it extended when it was compiled.
const RESET_HEADERS = { 'sw-twig-hmr-reset': '1' };

/**
 * @typedef {object} Session
 * @property {ReturnType<typeof createOwnership>} ownership
 * @property {ReturnType<typeof createInteractionState>} interaction
 * @property {Element|null} pageBaseline - the server markup of the body that the page shows
 * @property {MarkupSnapshot} headBaseline - the head elements of the last render
 * @property {MarkupSnapshot} scriptBaseline - the inline scripts in the body of the last render
 * @property {WeakMap<Element, {url: string, promise: Promise<string|null>}>} fragmentBaselines
 * @property {MutationObserver|null} sourceObserver
 */

/**
 * @typedef {object} MarkupSnapshot
 * @property {string[]} elements - the markup of the compared elements
 * @property {boolean} fromServer
 */

/** @type {Session|null} */
let session = null;
let isRefreshing = false;
let pendingFiles = null;
let pendingStructural = false;

const HEAD_ELEMENTS = new Set(['meta', 'link', 'script', 'style', 'base']);

/**
 * @param {Document} doc
 * @param {boolean} fromServer - false for the live head, which can already hold elements that scripts added
 * @returns {MarkupSnapshot}
 */
function getHeadSnapshot(doc, fromServer) {
    // Only direct children: without scripting, DOMParser parses the content of `<noscript>` as elements.
    const elements = [...doc.head.children]
        .filter((element) => HEAD_ELEMENTS.has(element.localName))
        .map(serializeWithoutNonce);

    return { elements, fromServer };
}

/**
 * @param {Element} element
 * @returns {string}
 */
function serializeWithoutNonce(element) {
    // A CSP nonce differs on every request, and browsers hide it in the live DOM.
    const copy = element.cloneNode(true);
    copy.removeAttribute('nonce');

    return copy.outerHTML;
}

/**
 * Compares two lists of markup as multisets.
 *
 * @param {string[]} previous
 * @param {string[]} next
 * @returns {{added: boolean, removed: boolean}}
 */
function compareMarkup(previous, next) {
    const remaining = [...previous];

    const added = next.some((element) => {
        const index = remaining.indexOf(element);
        if (index === -1) {
            return true;
        }

        remaining.splice(index, 1);

        return false;
    });

    return { added, removed: remaining.length > 0 };
}

/**
 * Elements from DOMParser never run, so the morph updates inline scripts in the body without executing them.
 * Data blocks such as `application/json` count as well, plugins read them only once.
 *
 * The morph keeps client owned regions, so their scripts do not count.
 *
 * @param {Element} body - without the server preserved elements
 * @param {boolean} fromServer - false for the live body
 * @returns {MarkupSnapshot}
 */
function getInlineScriptSnapshot(body, fromServer) {
    const scripts = [...body.querySelectorAll('script:not([src])')].filter((script) => !script.closest(CLIENT_OWNED_SELECTOR));

    return { elements: scripts.map(serializeWithoutNonce), fromServer };
}

/**
 * @param {MarkupSnapshot} previous
 * @param {MarkupSnapshot} next - always a server render
 */
function hasSnapshotChanged(previous, next) {
    const { added, removed } = compareMarkup(previous.elements, next.elements);

    // Scripts add elements to the live page, for example styles or tracking scripts, so only a server render shows removals.
    return added || (previous.fromServer && removed);
}

const shownSetupWarnings = new Set();

function warnOnce(key, message) {
    if (shownSetupWarnings.has(key)) {
        return;
    }

    shownSetupWarnings.add(key);
    console.warn(`${LOG_PREFIX} ${message}`);
}

/**
 * Template changes only show up if the shop renders them again.
 * That needs the debug mode, where Twig reloads changed templates, and a response that does not come from an HTTP cache.
 * `TwigHmrCacheSubscriber` only bypasses the Symfony HTTP cache in debug mode, a reverse proxy such as Varnish can still answer.
 */
export function warnAboutServerSetup(response) {
    const symfonyCache = response.headers.get('x-symfony-cache');

    // The Symfony HTTP cache can send `Age: 1` on a fresh miss, so its own header wins if present.
    const isCached = symfonyCache === null
        ? Number(response.headers.get('age')) > 0
        : /\b(fresh|stale|valid)\b/.test(symfonyCache);

    if (isCached) {
        warnOnce('http-cache', [
            'The page came from an HTTP cache, so template changes may not show up.',
            'Disable the HTTP cache while developing, for example with SHOPWARE_HTTP_CACHE_ENABLED=0, and bypass reverse proxies such as Varnish.',
        ].join('\n'));
    }

    // Symfony only sends these headers in debug mode, with the profiler or the HTTP cache enabled.
    if (response.headers.get('x-debug-token') === null && symfonyCache === null) {
        warnOnce('debug', [
            'The shop does not seem to run in debug mode.',
            'Twig only reloads changed templates in debug mode, so run the shop with APP_ENV=dev.',
        ].join('\n'));
    }
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

    warnAboutServerSetup(response);

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
        pageBaseline: withBaseline ? removeServerPreserved(document.body.cloneNode(true)) : null,
        headBaseline: getHeadSnapshot(document, false),
        scriptBaseline: getInlineScriptSnapshot(removeServerPreserved(document.body.cloneNode(true)), false),
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
    pendingStructural = false;
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
 * Some offcanvas content is a part of the page with a fetched response inside, for example the navigation offcanvas.
 * `data-sw-source-shell` selects that part of the page, and `data-sw-source-slot` the element in it that holds the response.
 *
 * @param {Element} fragment - the live modal or offcanvas
 * @param {string} html - the response of the source URL
 * @param {Element|null} pageRoot - the body of the matching page render
 * @returns {Element|null} null if the page render has no matching shell
 */
function composeFragment(fragment, html, pageRoot) {
    const { swSourceShell: shellSelector, swSourceSlot: slotSelector } = fragment.dataset;

    if (!shellSelector) {
        return parseFragment(html);
    }

    const shell = pageRoot?.querySelector(shellSelector);
    const container = parseFragment(shell?.innerHTML ?? '');
    const slot = slotSelector ? container.querySelector(slotSelector) : null;

    if (!shell || !slot) {
        return null;
    }

    slot.innerHTML = html;

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

async function prepareFragment(target, html, nextPageRoot) {
    const baseline = session.fragmentBaselines.get(target.element);
    const previousHtml = baseline?.url === target.url ? await baseline.promise : null;
    const nextContainer = composeFragment(target.element, html, nextPageRoot);
    const previousContainer = previousHtml === null ? null : composeFragment(target.element, previousHtml, session.pageBaseline);

    if (!nextContainer) {
        return { ...target, html, liveRoot: target.element, title: null, nextTitle: null, nextRoot: null, previousRoot: null };
    }

    const nextTitle = nextContainer.querySelector('.js-pseudo-modal-template-title-element');
    const { liveRoot, select, title } = resolveFragmentRoot(target, nextContainer);

    return {
        ...target,
        html,
        liveRoot,
        title,
        nextTitle: title ? nextTitle : null,
        nextRoot: select(nextContainer),
        previousRoot: previousContainer === null ? null : select(previousContainer),
    };
}

function fetchTarget(target, headers = {}) {
    return fetchHtml(target.url, { ...(target.kind === 'page' ? {} : FRAGMENT_HEADERS), ...headers });
}

/**
 * @param {object[]} targets - the page first, then the fragments
 * @param {boolean} structural - a Twig file was added or removed
 * @returns {Promise<string[]>}
 */
async function fetchTargets(targets, structural) {
    if (!structural) {
        return Promise.all(targets.map((target) => fetchTarget(target)));
    }

    // The page request drops the compiled templates, so the fragments wait and never read a half cleared cache.
    const [page, ...fragments] = targets;
    const pageHtml = await fetchTarget(page, RESET_HEADERS);

    return [pageHtml, ...await Promise.all(fragments.map((target) => fetchTarget(target)))];
}

/**
 * Phase 1: fetches and validates everything. Nothing in the DOM changes here.
 */
async function prepare(structural) {
    const [pageTarget, ...fragmentTargets] = collectTargets();
    const [pageHtml, ...fragmentHtmls] = await fetchTargets([pageTarget, ...fragmentTargets], structural);

    const nextDocument = new DOMParser().parseFromString(pageHtml, 'text/html');
    removeServerPreserved(nextDocument.body);
    // Idiomorph moves the new nodes into the DOM, so the baseline is copied first.
    const nextBaseline = nextDocument.body.cloneNode(true);

    const page = {
        ...pageTarget,
        document: nextDocument,
        liveRoot: pageTarget.element,
        nextRoot: nextDocument.body,
        previousRoot: session.pageBaseline,
        nextBaseline,
    };
    const fragments = await Promise.all(fragmentTargets.map((target, index) => {
        return prepareFragment(target, fragmentHtmls[index], nextBaseline);
    }));

    return [page, ...fragments];
}

/**
 * @param {object} target
 * @param {Map<Element, object>} plan
 * @param {string[]} reasons
 * @param {{toLive: function(Node): Node, scriptMatches: Element[]}|null} dryRun - maps the nodes of a dry run to the live nodes
 *     and collects the elements of scripts that new markup was matched with
 * @param {{element: Element, next: Element}[]} replacements - collects the component elements to replace after the morph
 */
function createMorphCallbacks(target, plan, reasons, dryRun = null, replacements = []) {
    const toLive = dryRun?.toLive ?? ((node) => node);
    const insideFragment = target.kind !== 'page';
    const { ownership, interaction } = session;

    return {
        beforeNodeMorphed(node, newNode) {
            const oldNode = toLive(node);

            if (!(oldNode instanceof Element) || oldNode === target.liveRoot) {
                return true;
            }

            if (ownership.isClientOwned(oldNode, insideFragment)) {
                if (!oldNode.hasAttribute(PRESERVE_ATTRIBUTE)) {
                    reasons.push(`New markup was matched with ${describeElement(oldNode)}, which a script added.`);
                    dryRun?.scriptMatches.push(oldNode);
                }

                return false;
            }

            const entry = plan.get(oldNode);
            if (!entry) {
                return true;
            }

            // The component system creates a component again for a new element, so a changed component element is replaced.
            if (entry.pluginNames.length === 0) {
                if (!dryRun && (entry.changed || !matchesExpectedMarkup(entry, newNode))) {
                    replacements.push({ element: oldNode, next: cloneWithoutTemporaryIds(newNode) });
                }

                return false;
            }

            const plugins = entry.pluginNames.join(', ');
            if (entry.changed) {
                reasons.push(`The markup of ${describeElement(oldNode)} changed. Plugins: ${plugins}.`);
            } else if (!matchesExpectedMarkup(entry, newNode)) {
                reasons.push(`${describeElement(oldNode)} was matched with other markup. Plugins: ${plugins}.`);
            }

            // Plugins hold references to their children, so the morph never enters a plugin element.
            return false;
        },

        beforeNodeRemoved(node) {
            const oldNode = toLive(node);

            return !(oldNode instanceof Element && ownership.isClientOwned(oldNode, insideFragment));
        },

        beforeAttributeUpdated(attributeName, element) {
            return !interaction.isProtectedAttribute(attributeName, toLive(element));
        },
    };
}

/**
 * @param {object} entry - the plan entry of a live plugin element
 * @param {Element} newNode - the new markup that idiomorph matched with the element
 */
function matchesExpectedMarkup(entry, newNode) {
    if (entry.temporaryId !== null) {
        return newNode.id === entry.temporaryId;
    }

    return entry.expectedMarkup === null || cloneWithoutTemporaryIds(newNode).outerHTML === entry.expectedMarkup;
}

let temporaryIdCounter = 0;

/**
 * idiomorph matches siblings without an id by tag name, so an element that a template adds before a plugin element
 * can take over the match of a text node behind it, and the plugin element is removed in between.
 * A temporary id on the live plugin element and on its new counterpart makes idiomorph keep the pair.
 *
 * @param {Map<Element, object>} plan
 * @returns {function(): void} removes the temporary ids again
 */
function assignTemporaryIds(plan) {
    const assigned = [];

    plan.forEach((entry, element) => {
        if (element.id || !entry.nextElement || entry.nextElement.id) {
            return;
        }

        temporaryIdCounter += 1;
        entry.temporaryId = `${TEMPORARY_ID_PREFIX}${temporaryIdCounter}`;
        element.id = entry.temporaryId;
        entry.nextElement.id = entry.temporaryId;
        assigned.push({ entry, element });
    });

    // The new markup becomes the next baseline, so the ids must leave both trees.
    return () => {
        assigned.forEach(({ entry, element }) => {
            element.removeAttribute('id');
            entry.nextElement.removeAttribute('id');
            entry.temporaryId = null;
        });
    };
}

/**
 * Elements that must survive a morph as they are, with the reason why.
 *
 * @returns {Map<Element, string>}
 */
function collectGuardedElements(target, plan) {
    const insideFragment = target.kind !== 'page';
    const guarded = new Map();
    const guard = (element, reason) => {
        if (!guarded.has(element)) {
            guarded.set(element, reason);
        }
    };

    // The component system destroys the components of removed elements itself.
    plan.forEach((entry, element) => {
        if (entry.pluginNames.length > 0) {
            guard(element, `holds the plugins ${entry.pluginNames.join(', ')}`);
        }
    });
    target.liveRoot.querySelectorAll('*').forEach((element) => {
        if (session.ownership.isClientOwned(element, insideFragment)) {
            guard(element, 'a script added');
        }
    });
    target.liveRoot.querySelectorAll(OVERLAY_SELECTOR).forEach((element) => guard(element, 'is open'));

    return guarded;
}

/**
 * idiomorph matches nodes differently around the focused element and reads the focus from `ownerDocument`.
 * The detached copy gets a document that reports the copy of the focused element, so the dry run matches like the morph.
 */
function mirrorFocus(copy, toCopy) {
    const activeElement = toCopy.get(document.activeElement);

    if (!activeElement) {
        return;
    }

    const doc = new Proxy(document, {
        get(target, property) {
            if (property === 'activeElement') {
                return activeElement;
            }

            const value = Reflect.get(target, property, target);

            return typeof value === 'function' ? value.bind(target) : value;
        },
    });
    Object.defineProperty(copy, 'ownerDocument', { value: doc });
}

/**
 * Runs the morph on a detached copy first. Idiomorph removes nodes in several ways,
 * for example together with an ancestor or from its pantry of nodes with an id, without calling a callback.
 * Comparing the copy afterwards finds every removal.
 *
 * @returns {{guarded: Map<Element, string>, reasons: string[]}} the guarded elements and why the morph is unsafe
 */
function findUnsafeChanges(target, plan) {
    const guarded = collectGuardedElements(target, plan);
    const inertBackground = target.kind === 'page' ? findInertBackground() : [];
    inertBackground.forEach((element) => {
        if (!guarded.has(element)) {
            guarded.set(element, 'belongs to the inert background of an open overlay');
        }
    });

    if (guarded.size === 0) {
        return { guarded, reasons: [] };
    }

    const copy = target.liveRoot.cloneNode(true);
    const liveElements = [target.liveRoot, ...target.liveRoot.querySelectorAll('*')];
    const copyElements = [copy, ...copy.querySelectorAll('*')];
    const toLive = new Map(copyElements.map((element, index) => [element, liveElements[index]]));
    const toCopy = new Map(liveElements.map((element, index) => [element, copyElements[index]]));

    const scriptMatches = [];
    mirrorFocus(copy, toCopy);
    Idiomorph.morph(copy, target.nextRoot.cloneNode(true).childNodes, {
        morphStyle: 'innerHTML',
        restoreFocus: false,
        callbacks: createMorphCallbacks(target, plan, [], { toLive: (node) => toLive.get(node) ?? node, scriptMatches }),
    });

    const reasons = [...guarded]
        .filter(([element]) => !copy.contains(toCopy.get(element)))
        .map(([element, reason]) => `The template removes ${describeElement(element)}, which ${reason}.`);

    // The morph would skip the new markup and leave the page between the old and the new render.
    scriptMatches.forEach((element) => {
        reasons.push(`New markup was matched with ${describeElement(element)}, which a script added.`);
    });

    // The overlay only restores the background elements that it made inert itself.
    if (inertBackground.length > 0) {
        [...copy.children]
            .filter((child) => !toLive.has(child))
            .forEach((child) => {
                reasons.push(`The template adds ${describeElement(child)} while an open overlay makes the rest of the page inert.`);
            });
    }

    return { guarded, reasons };
}

/**
 * @returns {boolean} true if the target was updated
 */
function morphTarget(target, reasons) {
    if (!target.liveRoot || !target.nextRoot) {
        reasons.push(`The ${target.kind} of ${target.url} no longer has the expected structure.`);

        return false;
    }

    const plan = planPluginElements({
        liveRoot: target.liveRoot,
        previousServerRoot: target.previousRoot,
        nextServerRoot: target.nextRoot,
        isExcluded: (element) => {
            return session.ownership.isInsideClientOwned(element, target.liveRoot, target.kind !== 'page');
        },
    });

    const removeTemporaryIds = assignTemporaryIds(plan);
    const replacements = [];
    let guarded;

    try {
        const unsafeChanges = findUnsafeChanges(target, plan);
        guarded = unsafeChanges.guarded;
        if (unsafeChanges.reasons.length > 0) {
            reasons.push(...unsafeChanges.reasons, `The ${target.kind} of ${target.url} keeps its current content.`);

            return false;
        }

        if (target.title && target.nextTitle) {
            target.title.innerHTML = target.nextTitle.innerHTML;
        }

        // Idiomorph wraps a single element in a dummy parent, so the children are passed.
        // `ignoreActiveValue` is not used: idiomorph then skips all children of the focused element,
        // and Bootstrap focuses the `.modal` element itself.
        Idiomorph.morph(target.liveRoot, target.nextRoot.childNodes, {
            morphStyle: 'innerHTML',
            restoreFocus: true,
            callbacks: createMorphCallbacks(target, plan, reasons, null, replacements),
        });
    } finally {
        removeTemporaryIds();
    }

    // The dry run should have rejected this. Never let the page look updated if the morph still removed a guarded element.
    guarded.forEach((reason, element) => {
        if (!element.isConnected) {
            reasons.push(`The morph removed ${describeElement(element)}, which ${reason}. The page may no longer work as expected.`);
        }
    });

    replaceComponentElements(replacements, plan, reasons);

    return true;
}

/**
 * Replaces changed component elements with their new markup.
 * The mutation observer of `window.Shopware` destroys the old components and creates new ones.
 * A plugin inside the element would lose its element without being destroyed, so such an element keeps its markup.
 *
 * @param {{element: Element, next: Element}[]} replacements
 * @param {Map<Element, object>} plan
 * @param {string[]} reasons
 */
function replaceComponentElements(replacements, plan, reasons) {
    replacements.forEach(({ element, next }) => {
        const components = plan.get(element).componentNames.join(', ');
        const plugins = [...plan]
            .filter(([other, entry]) => other !== element && entry.pluginNames.length > 0 && element.contains(other))
            .flatMap(([, entry]) => entry.pluginNames);

        if (plugins.length > 0) {
            reasons.push(`The markup of ${describeElement(element)} changed. Components: ${components}. Plugins inside: ${[...new Set(plugins)].join(', ')}.`);

            return;
        }

        if (element.isConnected) {
            element.replaceWith(next);
        }
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

    const applied = session.ownership.withoutRecording(() => {
        return valid.filter((target) => morphTarget(target, reasons));
    });
    restoreState();

    // A target that keeps its content keeps its baseline, so the next refresh compares against the shown markup.
    applied.filter((target) => target.kind !== 'page').forEach((target) => {
        session.fragmentBaselines.set(target.element, { url: target.url, promise: Promise.resolve(target.html) });
    });

    const [page] = applied;
    if (page?.kind !== 'page') {
        return reasons;
    }

    if (page.document.title && page.document.title !== document.title) {
        document.title = page.document.title;
    }

    const headSnapshot = getHeadSnapshot(page.document, true);
    if (hasSnapshotChanged(session.headBaseline, headSnapshot)) {
        reasons.push('The <head> changed. Meta, link, script and style elements in the head are only applied by a reload.');
    }

    const scriptSnapshot = getInlineScriptSnapshot(page.nextBaseline, true);
    if (hasSnapshotChanged(session.scriptBaseline, scriptSnapshot)) {
        reasons.push('An inline script in the page changed. Scripts only run after a reload.');
    }

    session.headBaseline = headSnapshot;
    session.scriptBaseline = scriptSnapshot;
    session.pageBaseline = page.nextBaseline;

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
 * @param {{structural?: boolean}} options - `structural` if a Twig file was added or removed,
 *     then the server drops its compiled templates before it renders the page
 * @returns {Promise<void>}
 */
export async function refresh(files, { structural = false } = {}) {
    if (isRefreshing) {
        pendingFiles = [...(pendingFiles || []), ...files];
        pendingStructural = pendingStructural || structural;

        return;
    }

    // Without a baseline every plugin element counts as changed.
    init({ withBaseline: false });
    isRefreshing = true;
    const start = performance.now();

    try {
        const targets = await prepare(structural);
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
            const nextStructural = pendingStructural;
            pendingFiles = null;
            pendingStructural = false;
            await refresh(next, { structural: nextStructural });
        }
    }
}
