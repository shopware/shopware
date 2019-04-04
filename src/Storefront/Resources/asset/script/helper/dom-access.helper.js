import StringHelper from 'asset/script/helper/string.helper';

export default class DomAccess {

    /**
     * Returns whether or not the element is an HTML node
     *
     * @param {Element} element
     * @returns {boolean}
     */
    static isNode(element) {
        if (!element) return false;

        if (typeof Node === 'object') {
            return element instanceof Node;
        }

        const isObject = typeof element === 'object';
        const isNumber = typeof element.nodeType === 'number';
        const isString = typeof element.nodeName === 'string';

        const HtmlNode = isObject && isNumber && isString;
        const RootNode = element === document || element === window;

        return element && (HtmlNode || RootNode);
    }

    /**
     * Returns if the given element has the requested attribute/property
     * @param {Element} element
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
     * @param {Element|EventTarget} element
     * @param {string} attribute
     * @param {boolean} strict
     * @returns {*|this|string}
     */
    static getAttribute(element, attribute, strict = true) {
        if (strict && DomAccess.hasAttribute(element, attribute) === false) {
            throw new Error(`The required property "${attribute}" does not exist!`);
        }

        return element.getAttribute(attribute);
    }

    /**
     * Returns the value of a given elements dataset entry
     *
     * @param {Element|EventTarget} element
     * @param {string} key
     * @param {boolean} strict
     * @returns {*|this|string}
     */
    static getDataAttribute(element, key, strict = true) {
        const keyWithoutData = key.replace(/^data(|-)/, '');
        const parsedKey = StringHelper.toLowerCamelCase(keyWithoutData, '-');
        const attribute = element.dataset[parsedKey];

        if (strict && typeof attribute === 'undefined') {
            throw new Error(`The required data attribute "${key}" does not exist on ${element}!`);
        }

        return StringHelper.parsePrimitive(attribute);
    }

    /**
     * Returns the selected element of a defined parent node
     * @param {Element|EventTarget} parentNode
     * @param {string} selector
     * @param {boolean} strict
     * @returns {Element}
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
     * @param {Element|EventTarget} parentNode
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
}
