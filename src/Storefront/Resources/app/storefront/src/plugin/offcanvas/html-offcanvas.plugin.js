import OffCanvas from 'src/plugin/offcanvas/offcanvas.plugin';

/**
 * @package storefront
 */
export default class HtmlOffCanvas extends OffCanvas {

    /**
     * Open an offcanvas with HTML content from any given selector
     * @param {string} selector
     * @param {'left'|'right'} position
     * @param {boolean} closable
     * @param {number} delay
     * @param {boolean} fullwidth
     * @param {array|string} cssClass
     */

    static open(selector, position = 'left', closable = true, delay = OffCanvas.REMOVE_OFF_CANVAS_DELAY, fullwidth = false, cssClass = '') {
        super.open(HtmlOffCanvas._getContent(selector), position, closable, delay, fullwidth, cssClass);
    }

    /**
     * Return the inner HTML content of a given selector
     * @param {string} selector
     *
     * @returns {string}
     * @private
     */
    static _getContent(selector) {
        const parent = document.querySelector(selector);

        if (parent instanceof Element === false) {
            throw Error('Parent element does not exist!');
        }

        return parent.innerHTML;
    }
}
