import Iterator from 'src/script/helper/iterator.helper';
import DomAccess from 'src/script/helper/dom-access.helper';

class ElementReplaceHelperSingleton {

    constructor() {
        this._domParser = new DOMParser();
    }

    /**
     * replace all elements from the target
     *
     * @param {string|HTMLElement} markup
     * @param {array|string} selectors
     *
     * @private
     */
    replaceFromMarkup(markup, selectors) {
        let src = markup;
        if (typeof src === 'string') {
            src = this._createMarkupFromString(src);
        }

        if (typeof selectors === 'string') {
            selectors = [selectors];
        }

        this._replaceSelectors(src, selectors);
    }

    /**
     * replaces the target with the src elements
     *
     * @param {NodeList|HTMLElement|string} src
     * @param {NodeList|HTMLElement|string} target
     *
     * @returns {boolean}
     */
    replaceElement(src, target) {
        if (typeof src === 'string') {
            src = DomAccess.querySelectorAll(document, src);
        }

        if (typeof target === 'string') {
            target = DomAccess.querySelectorAll(document, target);
        }

        if (src instanceof NodeList) {
            Iterator.iterate(src, (srcEl, index) => {
                target[index].innerHTML = srcEl.innerHTML;
            });
            return true;
        }

        if (target instanceof NodeList) {
            Iterator.iterate(target, (targetEl) => {
                targetEl.innerHTML = src.innerHTML;
            });
            return true;
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
     * @private
     */
    _replaceSelectors(src, selectors) {
        Iterator.iterate(selectors, (selector) => {
            const srcElements = DomAccess.querySelectorAll(src, selector);
            const targetElements = DomAccess.querySelectorAll(document, selector);

            this.replaceElement(srcElements, targetElements);
        });
    }

    /**
     * returns a dom element parsed from the passed string
     *
     * @param {string} string
     *
     * @returns {HTMLElement}
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
     *
     */
    static replaceFromMarkup(markup, selectors) {
        ElementReplaceHelperInstance.replaceFromMarkup(markup, selectors);
    }

    /**
     * replaces the target with the src elements
     *
     * @param {NodeList|HTMLElement|string} src
     * @param {NodeList|HTMLElement|string} target
     *
     * @returns {boolean}
     */
    static replaceElement(src, target) {
        return ElementReplaceHelperInstance.replaceElement(src, target);
    }
}
