import OffCanvasPlugin from 'src/plugin/offcanvas/offcanvas.plugin';

export default class HtmlOffCanvasPlugin extends OffCanvasPlugin {

    /**
     * Open an offcanvas with HTML content from any given selector
     * @param {string} selector
     * @param {'left'|'right'} position
     * @param {boolean} closable
     * @param {boolean} fullwidth
     * @param {array|string} cssClass
     */

    open(selector, position = 'left', closable = true, fullwidth = false, cssClass = '') {
        super.open(this._getContent(selector), position, closable, fullwidth, cssClass);
    }

    /**
     * Return the inner HTML content of a given selector
     * @param {string} selector
     *
     * @returns {string}
     * @private
     */
    _getContent(selector) {
        const parent = document.querySelector(selector);

        if (!(parent instanceof Element)) {
            throw Error('Parent element does not exist!');
        }

        return parent.innerHTML;
    }
}
