import OffCanvasPlugin from 'src/plugin/offcanvas/offcanvas.plugin';
import HttpClient from 'src/service/http-client.service';
import LoadingIndicator from 'src/utility/loading-indicator/loading-indicator.util';

// xhr call storage
let xhr = null;

export default class AjaxOffCanvasPlugin extends OffCanvasPlugin {

    /**
     * Fire AJAX request to get the offcanvas content
     *
     * @param {string} url
     * @param {*|boolean} data
     * @param {function|null} callback
     * @param {'left'|'right'} position
     * @param {boolean} closable
     * @param {boolean} fullwidth
     * @param {array|string} cssClass
     */
    open(url = false, data = false, callback = null, position = 'left', closable = true, fullwidth = false, cssClass = '') {
        if (!url) {
            throw new Error('A url must be given!');
        }
        // avoid multiple backdrops
        this._removeExistingOffCanvas();

        const offCanvas = this._createOffCanvas(position, fullwidth, cssClass);
        this.setContent(url, data, callback, closable);
        this._openOffcanvas(offCanvas);
    }

    /**
     * Method to change the content of the already visible OffCanvas via xhr
     *
     * @param {string} url
     * @param {*} data
     * @param {function} callback
     * @param {boolean} closable
     */
    setContent(url, data, callback, closable) {
        const client = new HttpClient();
        super.setContent(`<div class="offcanvas-content-container">${LoadingIndicator.getTemplate()}</div>`, closable);

        // interrupt already running ajax calls
        if (xhr) xhr.abort();

        const cb = (response) => {
            super.setContent(response, closable);
            // if a callback function is being injected execute it after opening the OffCanvas
            if (typeof callback === 'function') {
                callback(response);
            }
        };

        if (data) {
            xhr = client.post(url, data, this._executeCallback.bind(this, cb));
        } else {
            xhr = client.get(url, this._executeCallback.bind(this, cb));
        }
    }

    /**
     * instantly replace the offcanvas content
     *
     * @param content
     * @param closable
     */
    replaceContent(content, closable = true) {
        super.setContent(content, closable);
    }

    /**
     * Executes the given callback
     * and initializes all plugins
     *
     * @param {function} cb
     * @param {string} response
     */
    _executeCallback(cb, response) {
        if (typeof cb === 'function') {
            cb(response);
        }
        window.PluginManager.initializePlugins();
    }
}
