import OffCanvas from "./OffCanvas";

export default class DOMContentOffCanvas extends OffCanvas {

    /**
     * Open an off-canvas with HTML content from any given selector
     * @param {string} selector
     * @param {'left'|'right'} position
     * @param {boolean} closable
     * @param {number} delay
     */
    static open(selector, position = 'left', closable = true, delay = OffCanvas.REMOVE_OFF_CANVAS_DELAY) {
        super.open(DOMContentOffCanvas._getContent(selector, position, closable, delay));
    }

    /**
     * Return the inner HTML content of a given selector
     * @param {string} selector
     * @param {'left'|'right'} position
     * @param {boolean} closable
     * @param {number} delay
     * @returns {string}
     * @private
     */
    static _getContent(selector, position, closable, delay) {
        let parent = document.querySelector(selector);

        if (parent instanceof Element === false) {
            throw Error('Parent element does not exist!');
        }

        return parent.innerHTML;
    }
}