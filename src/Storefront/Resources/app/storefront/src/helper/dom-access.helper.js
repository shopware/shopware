import StringHelper from 'src/helper/string.helper';

/**
 * @package storefront
 */
export default class DomAccess {

    /**
     * Returns if the element is an HTML node
     *
     * @param {HTMLElement} element
     * @returns {boolean}
     */
    static isNode(element) {
        if (typeof element !== 'object' || element === null) {
            return false;
        }

        if (element === document || element === window) {
            return true;
        }

        return element instanceof Node;
    }

    /**
     * Returns if the given element has the requested attribute/property
     * @param {HTMLElement} element
     * @param {string} attribute
     */
    static hasAttribute(element, attribute) {
        if (!DomAccess.isNode(element)) {
            throw new Error('The element must be a valid HTML Node!');
        }

        if (typeof element.hasAttribute !== 'function') return false;

        return element.hasAttribute(attribute);
    }

    /**
     * Returns the value of a given element's attribute/property
     * @param {HTMLElement|EventTarget} element
     * @param {string} attribute
     * @param {boolean} strict
     * @returns {*|this|string}
     */
    static getAttribute(element, attribute, strict = true) {
        if (strict && DomAccess.hasAttribute(element, attribute) === false) {
            throw new Error(`The required property "${attribute}" does not exist!`);
        }

        if (typeof element.getAttribute !== 'function') {
            if (strict) {
                throw new Error('This node doesn\'t support the getAttribute function!');
            }

            return undefined;
        }

        return element.getAttribute(attribute);
    }

    /**
     * Returns the value of a given elements dataset entry
     *
     * @param {HTMLElement|EventTarget} element
     * @param {string} key
     * @param {boolean} strict
     * @returns {*|this|string}
     */
    static getDataAttribute(element, key, strict = true) {
        const keyWithoutData = key.replace(/^data(|-)/, '');
        const parsedKey = StringHelper.toLowerCamelCase(keyWithoutData, '-');
        if (!DomAccess.isNode(element)) {
            if (strict) {
                throw new Error('The passed node is not a valid HTML Node!');
            }

            return undefined;
        }

        if (typeof element.dataset === 'undefined') {
            if (strict) {
                throw new Error('This node doesn\'t support the dataset attribute!');
            }

            return undefined;
        }

        const attribute = element.dataset[parsedKey];

        if (typeof attribute === 'undefined') {
            if (strict) {
                throw new Error(`The required data attribute "${key}" does not exist on ${element}!`);
            }

            return attribute;
        }

        return StringHelper.parsePrimitive(attribute);
    }

    /**
     * Returns the selected element of a defined parent node
     * @param {HTMLElement|EventTarget} parentNode
     * @param {string} selector
     * @param {boolean} strict
     * @returns {HTMLElement}
     */
    static querySelector(parentNode, selector, strict = true) {
        if (strict && !DomAccess.isNode(parentNode)) {
            throw new Error('The parent node is not a valid HTML Node!');
        }

        const element = parentNode.querySelector(selector) || false;

        if (strict && element === false) {
            throw new Error(`The required element "${selector}" does not exist in parent node!`);
        }

        return element;
    }

    /**
     * Returns the selected elements of a defined parent node
     *
     * @param {HTMLElement|EventTarget} parentNode
     * @param {string} selector
     * @param {boolean} strict
     * @returns {NodeList|false}
     */
    static querySelectorAll(parentNode, selector, strict = true) {
        if (strict && !DomAccess.isNode(parentNode)) {
            throw new Error('The parent node is not a valid HTML Node!');
        }

        let elements = parentNode.querySelectorAll(selector);
        if (elements.length === 0) {
            elements = false;
        }

        if (strict && elements === false) {
            throw new Error(`At least one item of "${selector}" must exist in parent node!`);
        }

        return elements;
    }

    /**
     * Returns all focusable elements in the given parent node.
     *
     * @param {HTMLElement|document} parentNode
     * @returns {NodeListOf<Element>}
     */
    static getFocusableElements(parentNode = document.body) {
        const focusAbleElements = `
            input:not([tabindex^="-"]):not([disabled]):not([type="hidden"]),
            select:not([tabindex^="-"]):not([disabled]),
            textarea:not([tabindex^="-"]):not([disabled]),
            button:not([tabindex^="-"]):not([disabled]),
            a[href]:not([tabindex^="-"]):not([disabled]),
            [tabindex]:not([tabindex^="-"]):not([disabled])
        `;

        return parentNode.querySelectorAll(focusAbleElements);
    }

    /**
     * Returns the first focusable element in the given parent node.
     *
     * @param parentNode
     * @returns {HTMLElement}
     */
    static getFirstFocusableElement(parentNode = document.body) {
        return this.getFocusableElements(parentNode)[0];
    }

    /**
     * Returns the last focusable element in the given parent node.
     *
     * @param parentNode
     * @returns {HTMLElement}
     */
    static getLastFocusableElement(parentNode = document) {
        const result = this.getFocusableElements(parentNode);

        return result[result.length - 1];
    }
}
