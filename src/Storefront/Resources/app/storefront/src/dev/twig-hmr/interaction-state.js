/**
 * Keeps the state that a user created by interacting with the page, and nothing else.
 *
 * Form values are kept only for controls that the user edited, so hidden inputs and untouched defaults follow the template.
 * Bootstrap components are restored by small adapters for the attributes that Bootstrap toggles at runtime.
 *
 * @sw-package framework
 * @private
 */
import { describeElement } from './plugin-policy';

const FORM_STATE_ATTRIBUTES = ['value', 'checked', 'selected'];
const CONTROL_SELECTOR = 'input, select, textarea';
const TAB_TRIGGER_SELECTOR = '[data-bs-toggle="tab"], [data-bs-toggle="pill"], [data-bs-toggle="list"]';
const OVERLAY_SELECTOR = '.modal.show, .offcanvas.show, .offcanvas.showing';

/**
 * @typedef {function(string[]): void} RestoreState - restores the state and collects reasons for a reload
 */

function isControl(element) {
    return element instanceof Element && element.matches(CONTROL_SELECTOR);
}

function controlKey(control) {
    const form = control.form;
    const formKey = form ? form.id || form.getAttribute('action') || '' : '';
    const type = control.type || control.localName;
    const value = type === 'checkbox' || type === 'radio' ? control.value : '';

    return [formKey, control.name || control.id, type, value].join('|');
}

function readControl(control) {
    if (control.localName === 'select') {
        return { selected: [...control.options].map((option) => option.selected) };
    }

    if (control.type === 'checkbox' || control.type === 'radio') {
        return { checked: control.checked };
    }

    return { value: control.value };
}

function writeControl(control, state) {
    if (state.selected) {
        [...control.options].forEach((option, index) => {
            option.selected = !!state.selected[index];
        });
    } else if ('checked' in state) {
        control.checked = state.checked;
    } else {
        control.value = state.value;
    }
}

function resolveById(element) {
    if (element.isConnected) {
        return element;
    }

    return element.id ? document.getElementById(element.id) : null;
}

function getTabTarget(trigger) {
    return trigger.getAttribute('data-bs-target') || trigger.getAttribute('href');
}

function findTabPane(target) {
    if (!target?.startsWith('#')) {
        return null;
    }

    try {
        return document.querySelector(target);
    } catch {
        return null;
    }
}

function findTabTrigger(target) {
    return [...document.querySelectorAll(TAB_TRIGGER_SELECTOR)].find((trigger) => getTabTarget(trigger) === target) ?? null;
}

function findCollapseToggles(collapse) {
    if (!collapse.id) {
        return [];
    }

    const target = `#${collapse.id}`;

    return [...document.querySelectorAll('[data-bs-toggle="collapse"]')].filter((toggle) => {
        return toggle.getAttribute('data-bs-target') === target || toggle.getAttribute('href') === target;
    });
}

function captureAttributes(element, names) {
    return names.map((name) => [name, element.getAttribute(name)]);
}

function writeAttributes(element, attributes) {
    attributes.forEach(([name, value]) => {
        if (value === null) {
            element.removeAttribute(name);
        } else {
            element.setAttribute(name, value);
        }
    });
}

/**
 * Bootstrap shows modals and offcanvas elements by classes and attributes that the server never renders.
 */
function captureOverlays() {
    return [...document.querySelectorAll(OVERLAY_SELECTOR)].map((element) => ({
        element,
        classes: ['show', 'showing'].filter((name) => element.classList.contains(name)),
        attributes: captureAttributes(element, ['style', 'aria-modal', 'role', 'aria-hidden']),
    }));
}

function restoreOverlays(records, reasons) {
    records.forEach(({ element, classes, attributes }) => {
        if (!element.isConnected) {
            reasons.push(`The open ${describeElement(element)} was replaced. Bootstrap still controls the old element.`);

            return;
        }

        element.classList.add(...classes);
        writeAttributes(element, attributes);
    });
}

/**
 * The offcanvas and modal plugins make the background inert while they are open.
 */
function captureBackground() {
    if (!document.querySelector(OVERLAY_SELECTOR)) {
        return [];
    }

    return [...document.body.children]
        .filter((child) => child.hasAttribute('inert') || child.hasAttribute('aria-hidden'))
        .map((element) => ({ element, attributes: captureAttributes(element, ['inert', 'aria-hidden']) }));
}

function restoreBackground(records, reasons) {
    records.forEach(({ element, attributes }) => {
        if (!element.isConnected) {
            reasons.push(`The inert background ${describeElement(element)} was replaced while an overlay is open.`);

            return;
        }

        writeAttributes(element, attributes);
        element.inert = element.hasAttribute('inert');
    });
}

function captureCollapses(touched) {
    return [...document.querySelectorAll('.collapse, .collapsing')]
        .filter((element) => touched.has(element))
        .map((element) => ({ element, open: element.classList.contains('show') }));
}

function restoreCollapses(records) {
    records.forEach(({ element, open }) => {
        const collapse = resolveById(element);

        if (!collapse) {
            return;
        }

        collapse.classList.remove('collapsing');
        collapse.classList.add('collapse');
        collapse.classList.toggle('show', open);
        collapse.style.removeProperty('height');

        findCollapseToggles(collapse).forEach((toggle) => {
            toggle.classList.toggle('collapsed', !open);
            toggle.setAttribute('aria-expanded', String(open));
        });
    });
}

function captureTabs(touched) {
    return [...document.querySelectorAll(TAB_TRIGGER_SELECTOR)]
        .filter((trigger) => touched.has(trigger) && trigger.classList.contains('active'))
        .map((trigger) => ({ trigger, target: getTabTarget(trigger) }));
}

function activate(element, siblings, active) {
    siblings.forEach((sibling) => {
        const isActive = sibling === element;

        sibling.classList.toggle('active', isActive);
        active(sibling, isActive);
    });
}

function restoreTabs(records) {
    records.forEach(({ trigger, target }) => {
        const liveTrigger = trigger.isConnected ? trigger : findTabTrigger(target);
        const pane = findTabPane(target);

        if (!liveTrigger || !pane) {
            return;
        }

        const list = liveTrigger.closest('[role="tablist"], .nav, .list-group') ?? liveTrigger.parentElement;
        activate(liveTrigger, [...list.querySelectorAll(TAB_TRIGGER_SELECTOR)], (sibling, isActive) => {
            sibling.setAttribute('aria-selected', String(isActive));
            if (isActive) {
                sibling.removeAttribute('tabindex');
            } else {
                sibling.setAttribute('tabindex', '-1');
            }
        });

        const panes = [...pane.parentElement.children].filter((child) => child.classList.contains('tab-pane'));
        activate(pane, panes, (sibling, isActive) => {
            // Bootstrap only fades panes with the fade class.
            sibling.classList.toggle('show', isActive && sibling.classList.contains('fade'));
        });
    });
}

function captureDropdowns() {
    return [...document.querySelectorAll('.dropdown-menu.show')].map((menu) => {
        const toggle = menu.parentElement?.querySelector('[data-bs-toggle="dropdown"]') ?? null;

        return {
            menu,
            toggle,
            attributes: captureAttributes(menu, ['style', 'data-popper-placement', 'data-bs-popper']),
        };
    });
}

function restoreDropdowns(records) {
    records.forEach(({ menu, toggle, attributes }) => {
        if (!menu.isConnected) {
            return;
        }

        menu.classList.add('show');
        writeAttributes(menu, attributes);

        if (toggle?.isConnected) {
            toggle.classList.add('show');
            toggle.setAttribute('aria-expanded', 'true');
        }
    });
}

/**
 * @param {Document} root
 */
export function createInteractionState(root = document) {
    const edited = new WeakSet();
    const touched = new WeakSet();

    function markEdited(event) {
        const control = event.target;

        if (!isControl(control) || control.type === 'hidden') {
            return;
        }

        if (control.type === 'radio' && control.name) {
            const group = control.form ? [...control.form.elements] : [...root.querySelectorAll('input[type="radio"]')];
            group
                .filter((element) => element.type === 'radio' && element.name === control.name)
                .forEach((element) => edited.add(element));

            return;
        }

        edited.add(control);
    }

    function markTouched(event) {
        if (event.target instanceof Element) {
            touched.add(event.target);
        }
    }

    const listeners = [
        ['input', markEdited],
        ['change', markEdited],
        ['shown.bs.collapse', markTouched],
        ['hidden.bs.collapse', markTouched],
        ['shown.bs.tab', markTouched],
    ];
    listeners.forEach(([type, listener]) => root.addEventListener(type, listener, true));

    function isEdited(element) {
        if (element.localName === 'option') {
            const select = element.closest('select');

            return !!select && edited.has(select);
        }

        return edited.has(element);
    }

    return {
        /**
         * @param {string} attributeName
         * @param {Element} element
         * @returns {boolean} true if the morph must keep the attribute
         */
        isProtectedAttribute(attributeName, element) {
            return FORM_STATE_ATTRIBUTES.includes(attributeName) && isEdited(element);
        },

        /**
         * Captures the state before a morph.
         *
         * @returns {RestoreState}
         */
        capture() {
            const controls = [...root.querySelectorAll(CONTROL_SELECTOR)]
                .filter((control) => edited.has(control))
                .map((control) => ({ control, key: controlKey(control), state: readControl(control) }));
            const overlays = captureOverlays();
            const background = captureBackground();
            const collapses = captureCollapses(touched);
            const tabs = captureTabs(touched);
            const dropdowns = captureDropdowns();

            return (reasons) => {
                restoreOverlays(overlays, reasons);
                restoreBackground(background, reasons);
                restoreCollapses(collapses);
                restoreTabs(tabs);
                restoreDropdowns(dropdowns);
                restoreControls(controls);
            };
        },

        disconnect() {
            listeners.forEach(([type, listener]) => root.removeEventListener(type, listener, true));
        },
    };

    /**
     * A control that the morph replaced gets the value of the old one, if exactly one new control matches.
     */
    function restoreControls(records) {
        const replaced = records.filter(({ control }) => !control.isConnected);

        if (replaced.length === 0) {
            return;
        }

        const candidates = [...root.querySelectorAll(CONTROL_SELECTOR)];
        replaced.forEach(({ key, state }) => {
            const matches = candidates.filter((candidate) => candidate.type !== 'hidden' && controlKey(candidate) === key);

            if (matches.length === 1) {
                writeControl(matches[0], state);
                edited.add(matches[0]);
            }
        });
    }
}
