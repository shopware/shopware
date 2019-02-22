import OffCanvas from "./OffCanvas";
import HttpClient from "../../service/http-client.service";

export default class AjaxContentOffCanvas extends OffCanvas {

    /**
     * Fire AJAX request to get the off-canvas content
     * @param {string} url
     * @param {'left'|'right'} position
     * @param {boolean} closable
     * @param {number} delay
     */
    static open(url, position = 'left', closable = true, delay = OffCanvas.REMOVE_OFF_CANVAS_DELAY) {
        let client = new HttpClient(window.accessKey, window.contextToken);
        client.get(url, AjaxContentOffCanvas._onAjaxSuccess.bind(this, position, closable, delay));
    }

    /**
     * Open the off-canvas on AJAX success
     * @param {'left'|'right'} position
     * @param {boolean} closable
     * @param {number} delay
     * @param {string} response
     * @private
     */
    static _onAjaxSuccess(position, closable, delay, response) {
        super.open(response, position, closable, delay);
    }
}