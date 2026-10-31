/**
 * Decides which plugin elements a morph may touch.
 *
 * Plugins cache child elements and add their own markup, so a morph inside a plugin element can leave
 * listeners on removed nodes. The morph therefore never enters a plugin element.
 * The server markup of the previous and the new render tells whether the template changed the element:
 * an unchanged element keeps its instance as it is, a changed one keeps its old markup and needs a reload.
 *
 * @sw-package framework
 * @private
 */
import { CLIENT_OWNED_SELECTOR } from './ownership';

/**
 * @typedef {object} PluginElementPlan
 * @property {string[]} pluginNames
 * @property {boolean} changed - the template changed the server markup of the element
 * @property {string|null} expectedMarkup - the new server markup, null if it cannot be assigned
 * @property {Element|null} nextElement - the element of the new server markup, null if it cannot be assigned
 * @property {string|null} temporaryId - the id that pairs the live element with `nextElement` during a morph
 */

/**
 * @returns {{name: string, selector: string}[]}
 */
function getStringRegistrations() {
    const pluginList = window.PluginManager?.getPluginList?.() ?? {};

    return Object.entries(pluginList).flatMap(([name, plugin]) => {
        const registrations = plugin instanceof Map ? plugin.get('registrations') : null;

        if (!(registrations instanceof Map)) {
            return [];
        }

        // Plugins registered on an element or a NodeList have no selector to evaluate on server markup.
        return [...registrations.values()]
            .filter(({ selector }) => typeof selector === 'string')
            .map(({ selector }) => ({ name, selector }));
    });
}

function queryAll(root, selector) {
    try {
        return [...root.querySelectorAll(selector)];
    } catch {
        return [];
    }
}

/**
 * Server markup that a template marks as owned by scripts never takes part in a morph.
 */
function queryServerElements(root, selector) {
    return queryAll(root, selector).filter((element) => !element.closest(CLIENT_OWNED_SELECTOR));
}

function queryServerMarkup(root, selector) {
    return queryServerElements(root, selector).map((element) => element.outerHTML);
}

function sameList(first, second) {
    return first.length === second.length && first.every((value, index) => value === second[index]);
}

/**
 * @param {object} params
 * @param {Element} params.liveRoot - the live element that the morph updates
 * @param {Element|null} params.previousServerRoot - the previous server markup, null if unknown
 * @param {Element} params.nextServerRoot - the new server markup
 * @param {function(Element): boolean} params.isExcluded - live elements outside of this morph
 * @returns {Map<Element, PluginElementPlan>}
 */
export function planPluginElements({ liveRoot, previousServerRoot, nextServerRoot, isExcluded }) {
    const plan = new Map();

    getStringRegistrations().forEach(({ name, selector }) => {
        const liveElements = queryAll(liveRoot, selector).filter((element) => {
            return !isExcluded(element) && !!window.PluginManager.getPluginInstanceFromElement(element, name);
        });

        if (liveElements.length === 0) {
            return;
        }

        const nextElements = queryServerElements(nextServerRoot, selector);
        const nextMarkup = nextElements.map((element) => element.outerHTML);
        const previousMarkup = previousServerRoot ? queryServerMarkup(previousServerRoot, selector) : null;
        const unchanged = previousMarkup !== null && sameList(previousMarkup, nextMarkup);
        // Elements can only be assigned by position if the template and the scripts kept their number.
        const assignable = nextMarkup.length === liveElements.length
            && (previousMarkup === null || previousMarkup.length === liveElements.length);

        liveElements.forEach((element, index) => {
            const entry = plan.get(element) ?? {
                pluginNames: [],
                changed: false,
                expectedMarkup: null,
                nextElement: null,
                temporaryId: null,
            };
            const changed = !unchanged && !(assignable && previousMarkup?.[index] === nextMarkup[index]);

            entry.pluginNames.push(name);
            entry.changed = entry.changed || changed;
            entry.expectedMarkup = assignable ? nextMarkup[index] : entry.expectedMarkup;
            entry.nextElement = assignable ? nextElements[index] : entry.nextElement;
            plan.set(element, entry);
        });
    });

    return plan;
}

/**
 * @param {Element} element
 * @returns {string}
 */
export function describeElement(element) {
    const id = element.id ? `#${element.id}` : '';
    const classes = [...element.classList].slice(0, 2).map((name) => `.${name}`).join('');

    return `<${element.localName}${id}${classes}>`;
}
