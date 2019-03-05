export default class DomAccess {

    /**
     * Returns if the given element has the requested attribute/property
     * @param {Element} element
     * @param {string} attribute
     */
    static hasAttribute(element, attribute) {
        if (element instanceof Element === false) return false;
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
     * Returns the selected element of a defined parent node
     * @param {Element|EventTarget} parentNode
     * @param {string} selector
     * @param {boolean} strict
     * @returns {Element}
     */
    static querySelector(parentNode, selector, strict = true) {
        let element = parentNode.querySelector(selector) || false;

        if (strict && element === false) {
            throw new Error(`The required element "${selector}" does not exist in parent node!`);
        }

        return element;
    }
}