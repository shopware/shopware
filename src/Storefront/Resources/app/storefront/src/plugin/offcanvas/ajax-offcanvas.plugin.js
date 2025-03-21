import OffCanvas, { OffCanvasInstance } from 'src/plugin/offcanvas/offcanvas.plugin';
import LoadingIndicator from 'src/utility/loading-indicator/loading-indicator.util';

/**
 * @sw-package framework
 */
export default class AjaxOffCanvas extends OffCanvas {

    /**
     * Fire AJAX request to get the offcanvas content
     *
     * @param {string} url
     * @param {*|boolean} data
     * @param {function|null} callback
     * @param {'left'|'right'} position
     * @param {boolean} closable
     * @param {number} delay
     * @param {boolean} fullwidth
     * @param {array|string} cssClass
     */
    static open(url = false, data = false, callback = null, position = 'left', closable = true, delay = OffCanvas.REMOVE_OFF_CANVAS_DELAY(), fullwidth = false, cssClass = '') {
        if (!url) {
            throw new Error('A url must be given!');
        }
        // avoid multiple backdrops
        OffCanvasInstance._removeExistingOffCanvas();

        const offCanvas = OffCanvasInstance._createOffCanvas(position, fullwidth, cssClass, closable);
        this.setContent(url, data, callback, closable, delay);
        OffCanvasInstance._openOffcanvas(offCanvas);
    }

    /**
     * Method to change the content of the already visible OffCanvas via xhr
     *
     * @param {string} url
     * @param {*} data
     * @param {function} callback
     * @param {boolean} closable
     * @param {number} delay
     */
    static setContent(url, data, callback, closable, delay) {
        super.setContent(`<div class="offcanvas-body">${LoadingIndicator.getTemplate()}</div>`, closable, delay);

        const cb = (response) => {
            super.setContent(response, closable, delay);
            // if a callback function is being injected execute it after opening the OffCanvas
            if (typeof callback === 'function') {
                callback(response);
            }
        };

        if (data) {
            const processedData = data instanceof FormData ? data : JSON.stringify(data);
            fetch(url, {
                method: 'POST',
                body: processedData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(response => response.text())
                .then(response => AjaxOffCanvas.executeCallback(cb, response));
        } else {
            fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(response => response.text())
                .then(response => AjaxOffCanvas.executeCallback(cb, response));
        }
    }

    /**
     * Executes the given callback
     * and initializes all plugins
     *
     * @param {function} cb
     * @param {string} response
     */
    static executeCallback(cb, response) {
        if (typeof cb === 'function') {
            cb(response);
        }
        window.PluginManager.initializePlugins();
    }
}
