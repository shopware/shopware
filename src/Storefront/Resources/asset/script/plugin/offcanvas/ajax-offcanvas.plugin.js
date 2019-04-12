import OffCanvas from 'asset/script/plugin/offcanvas/offcanvas.plugin';
import HttpClient from 'asset/script/service/http-client.service';
import LoadingIndicator from 'asset/script/util/loading-indicator/loading-indicator.util';

export default class AjaxOffCanvas extends OffCanvas {

    /**
     * Fire AJAX request to get the offcanvas content
     * @param {string} url
     * @param {function|null} callback
     * @param {'left'|'right'} position
     * @param {boolean} closable
     * @param {number} delay
     * @param {boolean} fullwidth
     */
    static open(url, callback = null, position = 'left', closable = true, delay = OffCanvas.REMOVE_OFF_CANVAS_DELAY, fullwidth = false) {
        const client = new HttpClient(window.accessKey, window.contextToken);

        super.open(LoadingIndicator.getTemplate(), function() {
            client.get(url, (response) => {
                if (typeof callback === 'function') {
                    callback(response);
                }
            });
        }, position, closable, delay, fullwidth);
    }
}
