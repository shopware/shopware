import OffCanvas from 'src/plugin/offcanvas/offcanvas.plugin';

/**
 * @sw-package framework
 */
export default class HtmlOffCanvas extends OffCanvas {

    /**
     * Open an offcanvas with HTML content from any given selector
     * @param {string} selector
     * @param {function|null} callback
     * @param {'left'|'right'} position
     * @param {boolean} closable
     * @param {number} delay
     * @param {boolean} fullwidth
     * @param {array|string} cssClass
     */

    static open(selector, callback = null, position = 'left', closable = true, delay = OffCanvas.REMOVE_OFF_CANVAS_DELAY, fullwidth = false, cssClass = '') {
        super.open(HtmlOffCanvas._getContent(selector), callback, position, closable, delay, fullwidth, cssClass);
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
