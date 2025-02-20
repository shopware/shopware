

/**
 * @sw-package framework
 */
class ElementReplaceHelperSingleton {

    constructor() {
        this._domParser = new DOMParser();
    }

    /**
     * replace all elements from the target
     *
     * @param {string|HTMLElement} markup
     * @param {array|string} selectors
     * @param {boolean} strict
     *
     * @private
     */
    replaceFromMarkup(markup, selectors, strict = true) {
        let src = markup;
        if (typeof src === 'string') {
            src = this._createMarkupFromString(src);
        }

        if (typeof selectors === 'string') {
            selectors = [selectors];
        }

        this._replaceSelectors(src, selectors, strict);
    }

    /**
     * replaces the target with the src elements
     *
     * @param {NodeList|HTMLElement|string} src
     * @param {NodeList|HTMLElement|string} target
     * @param {boolean} strict - @deprecated tag:v6.8.0 - Parameter "strict" will be removed.
     *
     * @returns {boolean}
     */
    replaceElement(src, target, strict = true) {
        if (typeof src === 'string') {
            src = document.querySelectorAll(src);
        }

        if (typeof target === 'string') {
            target = document.querySelectorAll(target);
        }

        if (src instanceof NodeList && target instanceof NodeList && target.length > src.length) {
            target.forEach((targetEl) => {
                src.forEach((srcEl) => {
                    if (srcEl.innerHTML && srcEl.className === targetEl.className) {
                        targetEl.innerHTML = srcEl.innerHTML;
                    }
                });
            });

            return true;
        }

        if (src instanceof NodeList && src.length) {
            src.forEach((srcEl, index) => {
                if (srcEl.innerHTML) {
                    target[index].innerHTML = srcEl.innerHTML;
                }
            });
            return true;
        }

        if (target instanceof NodeList && target.length) {
            target.forEach((targetEl) => {
                if (src.innerHTML) {
                    targetEl.innerHTML = src.innerHTML;
                }
            });
            return true;
        }

        if (!target || !src || !src.innerHTML) {
            return false;
        }

        target.innerHTML = src.innerHTML;
        return true;
    }

    /**
     * replaces all found selectors in the document
     * with the ones in the source
     *
     * @param {HTMLElement} src
     * @param {Array} selectors
     * @param {boolean} strict - @deprecated tag:v6.8.0 - Parameter "strict" will be removed.
     *
     * @private
     */
    _replaceSelectors(src, selectors, strict) {
        selectors.forEach((selector) => {
            const srcElements = src.querySelectorAll(selector);
            const targetElements = document.querySelectorAll(selector);

            this.replaceElement(srcElements, targetElements, strict);
        });
    }

    /**
     * returns a dom element parsed from the passed string
     *
     * @param {string} string
     *
     * @returns {HTMLElement}
     *
     * @private
     */
    _createMarkupFromString(string) {
        return this._domParser.parseFromString(string, 'text/html');
    }
}

/**
 * Create the ElementReplaceHelper instance.
 * @type {Readonly<ElementReplaceHelperSingleton>}
 */
export const ElementReplaceHelperInstance = Object.freeze(new ElementReplaceHelperSingleton());

export default class ElementReplaceHelper {

    /**
     * replace all elements from the target
     *
     * @param {string|HTMLElement} markup
     * @param {array|string} selectors
     * @param {boolean} strict - @deprecated tag:v6.8.0 - Parameter "strict" will be removed.
     *
     */
    static replaceFromMarkup(markup, selectors, strict) {
        ElementReplaceHelperInstance.replaceFromMarkup(markup, selectors, strict);
    }

    /**
     * replaces the target with the src elements
     *
     * @param {NodeList|HTMLElement|string} src
     * @param {NodeList|HTMLElement|string} target
     * @param {boolean} strict - @deprecated tag:v6.8.0 - Parameter "strict" will be removed.
     *
     * @returns {boolean}
     */
    static replaceElement(src, target, strict) {
        return ElementReplaceHelperInstance.replaceElement(src, target, strict);
    }
}
