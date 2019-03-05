import OffCanvas from "./OffCanvas";
import HttpClient from "../../service/http-client.service";
import LoadingIndicator from "../loading-indicator/LoadingIndicator";

export default class AjaxContentOffCanvas extends OffCanvas {

    /**
     * Fire AJAX request to get the off-canvas content
     * @param {string} url
     * @param {function|null} callback
     * @param {'left'|'right'} position
     * @param {boolean} closable
     * @param {number} delay
     */
    static open(url, callback = null, position = 'left', closable = true, delay = OffCanvas.REMOVE_OFF_CANVAS_DELAY) {
        let client = new HttpClient(window.accessKey, window.contextToken);

        super.open(LoadingIndicator.getTemplate(), function() {
            client.get(url, (response) => {
                if (typeof callback === "function") {
                    callback(response);
                }
            });
        }, position, closable, delay);
    }
}