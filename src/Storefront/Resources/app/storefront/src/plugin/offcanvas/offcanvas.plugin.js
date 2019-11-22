import DeviceDetection from 'src/helper/device-detection.helper';
import Backdrop, { BACKDROP_EVENT } from 'src/utility/backdrop/backdrop.util';
import Iterator from 'src/helper/iterator.helper';

const OFF_CANVAS_CLASS = 'offcanvas';
const OFF_CANVAS_OPEN_CLASS = 'is-open';
const OFF_CANVAS_POSITION_LEFT_CLASS = 'is-left';
const OFF_CANVAS_POSITION_RIGHT_CLASS = 'is-right';
const OFF_CANVAS_FULLWIDTH_CLASS = 'is-fullwidth';
const OFF_CANVAS_CLOSE_TRIGGER_CLASS = 'js-offcanvas-close';
const REMOVE_OFF_CANVAS_DELAY = 350;

class OffCanvasSingleton {

    /**
     * Open the offcanvas and its backdrop
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
        this._openOffcanvas(offCanvas, callback);
    }

    /**
     * Method to change the content of the already visible OffCanvas
     * @param {string} content
     * @param {boolean} closable
     * @param {number} delay
     */
    setContent(content, closable, delay) {
        const offCanvas = this.getOffCanvas();

        if (!offCanvas[0]) {
            return;
        }

        offCanvas[0].innerHTML = content;

        //register events again
        this._registerEvents(closable, delay);
    }

    /**
     * adds an additional class to the offcanvas
     *
     * @param {string} className
     */
    setAdditionalClassName(className) {
        const offCanvas = this.getOffCanvas();
        offCanvas[0].classList.add(className);
    }

    /**
     * Determine list of existing offcanvas
     * @returns {NodeListOf<Element>}
     * @private
     */
    getOffCanvas() {
        return document.querySelectorAll(`.${OFF_CANVAS_CLASS}`);
    }

    /**
     * Close the offcanvas and its backdrop
     * @param {number} delay
     */
    close(delay) {
        // remove open class to make any css animation effects possible
        const OffCanvasElements = this.getOffCanvas();
        Iterator.iterate(OffCanvasElements, backdrop => backdrop.classList.remove(OFF_CANVAS_OPEN_CLASS));

        // wait before removing backdrop to let css animation effects take place
        setTimeout(this._removeExistingOffCanvas.bind(this), delay);

        Backdrop.remove(delay);
    }

    /**
     * Returns whether any OffCanvas exists or not
     * @returns {boolean}
     */
    exists() {
        return (this.getOffCanvas().length > 0);
    }

    /**
     * opens the offcanvas and its backdrop
     *
     * @param {HTMLElement} offCanvas
     * @param {function} callback
     *
     * @private
     */
    _openOffcanvas(offCanvas, callback) {
        // the timeout allows to apply the animation effects
        setTimeout(() => {
            Backdrop.create(() => {
                offCanvas.classList.add(OFF_CANVAS_OPEN_CLASS);

                // if a callback function is being injected execute it after opening the OffCanvas
                if (typeof callback === 'function') {
                    callback();
                }
            });
        }, 75);
    }

    /**
     * Register events
     * @param {boolean} closable
     * @param {number} delay
     * @private
     */
    _registerEvents(closable, delay) {
        const event = (DeviceDetection.isTouchDevice()) ? 'touchstart' : 'click';

        if (closable) {
            const onBackdropClick = () => {
                this.close(delay);
                // remove the event listener immediately to avoid multiple listeners
                document.removeEventListener(BACKDROP_EVENT.ON_CLICK, onBackdropClick);
            };

            document.addEventListener(BACKDROP_EVENT.ON_CLICK, onBackdropClick);
        }

        const closeTriggers = document.querySelectorAll(`.${OFF_CANVAS_CLOSE_TRIGGER_CLASS}`);
        Iterator.iterate(closeTriggers, trigger => trigger.addEventListener(event, this.close.bind(this, delay)));
    }

    /**
     * Remove all existing offcanvas from DOM
     * @private
     */
    _removeExistingOffCanvas() {
        const offCanvasELements = this.getOffCanvas();
        Iterator.iterate(offCanvasELements, offCanvas => offCanvas.remove());
    }

    /**
     * Defines the position of the offcanvas by setting css class
     * @param {'left'|'right'} position
     * @returns {string}
     * @private
     */
    _getPosition(position) {
        return (position === 'left') ? OFF_CANVAS_POSITION_LEFT_CLASS : OFF_CANVAS_POSITION_RIGHT_CLASS;
    }

    /**
     * Creates the offcanvas element prototype including all relevant settings,
     * appends it to the DOM and returns the HTMLElement for further processing
     * @param {'left'|'right'} position
     * @param {boolean} fullwidth
     * @returns {HTMLElement}
     * @private
     */
    _createOffCanvas(position, fullwidth) {
        const offCanvas = document.createElement('div');
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
 * Create the OffCanvas instance.
 * @type {Readonly<OffCanvasSingleton>}
 */
export const OffCanvasInstance = Object.freeze(new OffCanvasSingleton());

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
        OffCanvasInstance.open(content, callback, position, closable, delay, fullwidth);
    }

    /**
     * Change content of visible OffCanvas
     * @param {string} content
     * @param {boolean} closable
     * @param {number} delay
     */
    static setContent(content, closable = true, delay = REMOVE_OFF_CANVAS_DELAY) {
        OffCanvasInstance.setContent(content, closable, delay);
    }

    /**
     * adds an additional class to the offcanvas
     *
     * @param {string} className
     */
    static setAdditionalClassName(className) {
        OffCanvasInstance.setAdditionalClassName(className);
    }

    /**
     * Close the OffCanvas
     * @param {number} delay
     */
    static close(delay = REMOVE_OFF_CANVAS_DELAY) {
        OffCanvasInstance.close(delay);
    }

    /**
     * Returns whether any OffCanvas exists or not
     * @returns {boolean}
     */
    static exists() {
        return OffCanvasInstance.exists();
    }

    /**
     * returns all existing offcanvas elements
     *
     * @returns {NodeListOf<Element>}
     */
    static getOffCanvas() {
        return OffCanvasInstance.getOffCanvas();
    }

    /**
     * Expose constant
     * @returns {number}
     */
    static REMOVE_OFF_CANVAS_DELAY() {
        return REMOVE_OFF_CANVAS_DELAY;
    }
}
