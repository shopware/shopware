import DeviceDetection from "../../helper/DeviceDetection";
import Backdrop, { BACKDROP_EVENT } from "../backdrop/Backdrop";

const OFF_CANVAS_CLASS = 'off-canvas';
const OFF_CANVAS_OPEN_CLASS = 'off-canvas--open';
const OFF_CANVAS_POSITION_LEFT_CLASS = 'off-canvas--left';
const OFF_CANVAS_POSITION_RIGHT_CLASS = 'off-canvas--right';
const OFF_CANVAS_CLOSE_TRIGGER_CLASS = 'off-canvas__close';
const REMOVE_OFF_CANVAS_DELAY = 350;

class OffCanvasSingleton {

    /**
     * Open the off-canvas and its backdrop
     * @param {string} content
     * @param {'left'|'right'} position
     * @param {boolean} closable
     * @param {number} delay
     */
    open(content, position, closable, delay) {
        // avoid multiple backdrops
        this._removeExistingOffCanvas();

        let offCanvas = this._createOffCanvas(position);
        offCanvas.innerHTML = content;
        document.body.appendChild(offCanvas);

        setTimeout(function() {
            offCanvas.classList.add(OFF_CANVAS_OPEN_CLASS);
            Backdrop.open();
        }, 1);

        this._registerCloseEvents(closable, delay);
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
     * Register events
     * @param closable
     * @param {number} delay
     * @private
     */
    _registerCloseEvents(closable, delay) {
        let event = (DeviceDetection.isTouchDevice()) ? 'touchstart' : 'click';

        if (closable === true) {
            document.addEventListener(BACKDROP_EVENT.ON_CLICK, this.close.bind(this, delay));
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
     * Creates the off-canvas element prototype including all relevant settings
     * @param {'left'|'right'} position
     * @returns {HTMLElement}
     * @private
     */
    _createOffCanvas(position) {
        let offCanvas = document.createElement('div');
        offCanvas.classList.add(OFF_CANVAS_CLASS);
        offCanvas.classList.add(this._getPosition(position));
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
     * @param {'left'|'right'} position
     * @param {boolean} closable
     * @param {number} delay
     */
    static open(content, position = 'left', closable = true, delay = REMOVE_OFF_CANVAS_DELAY) {
        instance.open(content, position, closable, delay);
    }

    /**
     * Close the OffCanvas
     * @param {number} delay
     */
    static close(delay = REMOVE_OFF_CANVAS_DELAY) {
        instance.close(delay);
    }

    /**
     * Expose constant
     * @returns {number}
     */
    static REMOVE_OFF_CANVAS_DELAY() {
        return REMOVE_OFF_CANVAS_DELAY;
    }
}