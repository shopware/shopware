import DeviceDetection from "../../helper/device-detection.helper";
import Backdrop, { BACKDROP_EVENT } from "../backdrop/backdrop.plugin";

const OFF_CANVAS_CLASS = 'off-canvas';
const OFF_CANVAS_OPEN_CLASS = 'is-open';
const OFF_CANVAS_POSITION_LEFT_CLASS = 'is-left';
const OFF_CANVAS_POSITION_RIGHT_CLASS = 'is-right';
const OFF_CANVAS_FULLWIDTH_CLASS = 'is-fullwidth';
const OFF_CANVAS_CLOSE_TRIGGER_CLASS = 'off-canvas-close';
const REMOVE_OFF_CANVAS_DELAY = 350;

class OffCanvasSingleton {

    /**
     * Open the off-canvas and its backdrop
     * @param {string} content
     * @param {function|null} callback
     * @param {'left'|'right'} position
     * @param {boolean} closable
     * @param {number} delay
     * @param {boolean} fullwidth
     */
    open(content, callback, position, closable, delay, fullwidth) {
        // avoid multiple backdrops
        this._removeExistingOffCanvas();

        const offCanvas = this._createOffCanvas(position, fullwidth);

        this.setContent(content, closable, delay);

        // the timeout allows to apply the animation effects
        setTimeout(function() {
            Backdrop.open(function() {
                offCanvas.classList.add(OFF_CANVAS_OPEN_CLASS);

                // if a callback function is being injected execute it after opening the OffCanvas
                if (typeof callback === "function") {
                    callback();
                }
            });
        }, 1);
    }

    /**
     * Method to change the content of the already visible OffCanvas
     * @param {string} content
     * @param {boolean} closable
     * @param {number} delay
     */
    setContent(content, closable, delay) {
        let offCanvas = this._getOffCanvas();
        offCanvas[0].innerHTML = content;

        //register events again
        this._registerEvents(closable, delay);
    }

    /**
     * Close the off-canvas and its backdrop
     * @param {number} delay
     */
    close(delay) {
        // remove open class to make any css animation effects possible
        this._getOffCanvas().forEach(backdrop => backdrop.classList.remove(OFF_CANVAS_OPEN_CLASS));

        // wait before removing backdrop to let css animation effects take place
        setTimeout(this._removeExistingOffCanvas.bind(this), delay);

        Backdrop.close(delay);
    }

    /**
     * Returns whether any OffCanvas exists or not
     * @returns {boolean}
     */
    exists() {
        return (this._getOffCanvas().length > 0);
    }

    /**
     * Register events
     * @param {boolean} closable
     * @param {number} delay
     * @private
     */
    _registerEvents(closable, delay) {
        let event = (DeviceDetection.isTouchDevice()) ? 'touchstart' : 'click';

        if (closable) {
            const onBackdropClick = () => {
                this.close(delay);
                // remove the event listener immediately to avoid multiple listeners
                document.removeEventListener(BACKDROP_EVENT.ON_CLICK, onBackdropClick);
            };

            document.addEventListener(BACKDROP_EVENT.ON_CLICK, onBackdropClick);
        }

        let closeTrigger = document.querySelector(`.${OFF_CANVAS_CLOSE_TRIGGER_CLASS}`);

        if (closeTrigger instanceof Element) {
            closeTrigger.addEventListener(event, this.close.bind(this, delay));
        }
    }

    /**
     * Determine list of existing off-canvas
     * @returns {NodeListOf<Element>}
     * @private
     */
    _getOffCanvas() {
        return document.querySelectorAll(`.${OFF_CANVAS_CLASS}`);
    }

    /**
     * Remove all existing off-canvas from DOM
     * @private
     */
    _removeExistingOffCanvas() {
        this._getOffCanvas().forEach(offCanvas => offCanvas.remove());
    }

    /**
     * Defines the position of the off-canvas by setting css class
     * @param {'left'|'right'} position
     * @returns {string}
     * @private
     */
    _getPosition(position) {
        return (position === 'left') ? OFF_CANVAS_POSITION_LEFT_CLASS : OFF_CANVAS_POSITION_RIGHT_CLASS;
    }

    /**
     * Creates the off-canvas element prototype including all relevant settings,
     * appends it to the DOM and returns the HTMLElement for further processing
     * @param {'left'|'right'} position
     * @param {boolean} fullwidth
     * @returns {HTMLElement}
     * @private
     */
    _createOffCanvas(position, fullwidth) {
        let offCanvas = document.createElement('div');
        offCanvas.classList.add(OFF_CANVAS_CLASS);
        offCanvas.classList.add(this._getPosition(position));

        if (fullwidth === true) {
            offCanvas.classList.add(OFF_CANVAS_FULLWIDTH_CLASS);
        }

        document.body.appendChild(offCanvas);

        return offCanvas;
    }
}


/**
 * Make the OffCanvas being a Singleton
 * @type {OffCanvasSingleton}
 */
const instance = new OffCanvasSingleton();
Object.freeze(instance);

export default class OffCanvas {

    /**
     * Open the OffCanvas
     * @param {string} content
     * @param {function|null} callback
     * @param {'left'|'right'} position
     * @param {boolean} closable
     * @param {number} delay
     * @param {boolean} fullwidth
     */
    static open(content, callback = null, position = 'left', closable = true, delay = REMOVE_OFF_CANVAS_DELAY, fullwidth = false) {
        instance.open(content, callback, position, closable, delay, fullwidth);
    }

    /**
     * Change content of visible OffCanvas
     * @param {string} content
     * @param {boolean} closable
     * @param {number} delay
     */
    static setContent(content, closable = true, delay = REMOVE_OFF_CANVAS_DELAY) {
        instance.setContent(content, closable, delay);
    }

    /**
     * Close the OffCanvas
     * @param {number} delay
     */
    static close(delay = REMOVE_OFF_CANVAS_DELAY) {
        instance.close(delay);
    }

    /**
     * Returns whether any OffCanvas exists or not
     * @returns {boolean}
     */
    static exists() {
        return instance.exists();
    }

    /**
     * Expose constant
     * @returns {number}
     */
    static REMOVE_OFF_CANVAS_DELAY() {
        return REMOVE_OFF_CANVAS_DELAY;
    }
}
