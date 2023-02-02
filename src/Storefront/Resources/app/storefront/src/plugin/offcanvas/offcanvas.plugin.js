import DeviceDetection from 'src/helper/device-detection.helper';
import NativeEventEmitter from 'src/helper/emitter.helper';
import Backdrop, { BACKDROP_EVENT } from 'src/utility/backdrop/backdrop.util';
import Iterator from 'src/helper/iterator.helper';
import Feature from 'src/helper/feature.helper';

const OFF_CANVAS_CLASS = 'offcanvas';
const OFF_CANVAS_OPEN_CLASS = 'is-open';
const OFF_CANVAS_FULLWIDTH_CLASS = 'is-fullwidth';
const OFF_CANVAS_CLOSE_TRIGGER_CLASS = 'js-offcanvas-close';
const REMOVE_OFF_CANVAS_DELAY = 350;

/**
 * @deprecated tag:v6.5.0 - Announcement:
 * Bootstrap v5 comes with its own OffCanvas component.
 * This class will be adjusted to use Bootstraps OffCanvas JavaScript implementation.
 *
 * @see https://getbootstrap.com/docs/5.1/components/offcanvas
 */
class OffCanvasSingleton {

    constructor() {
        this.$emitter = new NativeEventEmitter();
    }

    /**
     * Open the offcanvas and its backdrop
     * @param {string} content
     * @param {function|null} callback
     * @param {'left'|'right'|'bottom'} position
     * @param {boolean} closable
     * @param {number} delay
     * @param {boolean} fullwidth
     * @param {array|string} cssClass
     */
    open(content, callback, position, closable, delay, fullwidth, cssClass) {
        // avoid multiple backdrops
        this._removeExistingOffCanvas();

        const offCanvas = this._createOffCanvas(position, fullwidth, cssClass);
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
     * Close the offcanvas and its backdrop when the browser goes back in history
     * @param {number} delay
     */
    close(delay) {
        const OffCanvasElements = this.getOffCanvas();

        /** @deprecated tag:v6.5.0 - Bootstrap v5 uses own JS plugin instance to close the OffCanvas */
        if (Feature.isActive('v6.5.0.0')) {
            Iterator.iterate(OffCanvasElements, (offCanvas) => {
                const offCanvasInstance = bootstrap.Offcanvas.getInstance(offCanvas);
                offCanvasInstance.hide();
            });
        } else {
            // remove open class to make any css animation effects possible
            Iterator.iterate(OffCanvasElements, backdrop => backdrop.classList.remove(OFF_CANVAS_OPEN_CLASS));

            // wait before removing backdrop to let css animation effects take place
            setTimeout(this._removeExistingOffCanvas.bind(this), delay);

            Backdrop.remove(delay);
        }

        setTimeout(() => {
            this.$emitter.publish('onCloseOffcanvas', {
                offCanvasContent: OffCanvasElements,
            });
        }, delay);
    }

    /**
     * Callback for close button, goes back in browser history to trigger close
     * @returns {void}
     */
    goBackInHistory() {
        window.history.back();
    }

    /**
     * Returns whether any OffCanvas exists or not
     * @returns {boolean}
     */
    exists() {
        return (this.getOffCanvas().length > 0);
    }

    /**
     * Opens the offcanvas and its backdrop
     *
     * @param {HTMLElement} offCanvas
     * @param {function} callback
     *
     * @private
     */
    _openOffcanvas(offCanvas, callback) {
        /** @deprecated tag:v6.5.0 - Bootstrap v5 uses its own OffCanvas API to open the OffCanvas */
        if (Feature.isActive('v6.5.0.0')) {
            setTimeout(() => {
                OffCanvasSingleton.bsOffcanvas.show();
                window.history.pushState('offcanvas-open', '');

                // if a callback function is being injected execute it after opening the OffCanvas
                if (typeof callback === 'function') {
                    callback();
                }
            }, 75);
        } else {
            // the timeout allows to apply the animation effects
            setTimeout(() => {
                Backdrop.create(() => {
                    offCanvas.classList.add(OFF_CANVAS_OPEN_CLASS);
                    window.history.pushState('offcanvas-open', '');

                    // if a callback function is being injected execute it after opening the OffCanvas
                    if (typeof callback === 'function') {
                        callback();
                    }
                });
            }, 75);
        }
    }

    /**
     * Register events
     * @param {boolean} closable
     * @param {number} delay
     * @private
     */
    _registerEvents(closable, delay) {
        const event = (DeviceDetection.isTouchDevice()) ? 'touchend' : 'click';

        /** @deprecated tag:v6.5.0 - Bootstrap v5 handles OffCanvas backdrop automatically */
        if (Feature.isActive('v6.5.0.0')) {
            const offCanvasElements = this.getOffCanvas();

            /**
             * TODO: NEXT-21771 - Workaround to prevent close by click on backdrop.
             * Remove when Bootstrap OffCanvas supports `static` backdrop mode.
             * @see https://github.com/twbs/bootstrap/pull/35832
             */
            if (!closable) {
                OffCanvasSingleton.bsOffcanvas._backdrop._config.clickCallback = () => {};
            }

            // Ensure OffCanvas is removed from the DOM and events are published.
            Iterator.iterate(offCanvasElements, offCanvas => {
                const onBsClose = () => {
                    setTimeout(() => {
                        this._removeExistingOffCanvas();

                        this.$emitter.publish('onCloseOffcanvas', {
                            offCanvasContent: offCanvasElements,
                        });
                    }, delay);

                    offCanvas.removeEventListener('hide.bs.offcanvas', onBsClose)
                };

                offCanvas.addEventListener('hide.bs.offcanvas', onBsClose);
            });
        } else {
            if (closable) {
                const onBackdropClick = () => {
                    this.close(delay);
                    // remove the event listener immediately to avoid multiple listeners
                    document.removeEventListener(BACKDROP_EVENT.ON_CLICK, onBackdropClick);
                };

                document.addEventListener(BACKDROP_EVENT.ON_CLICK, onBackdropClick);
            }
        }

        window.addEventListener('popstate', this.close.bind(this, delay), { once: true });
        const closeTriggers = document.querySelectorAll(`.${OFF_CANVAS_CLOSE_TRIGGER_CLASS}`);
        Iterator.iterate(closeTriggers, trigger => trigger.addEventListener(event, this.close.bind(this, delay)));
    }

    /**
     * Remove all existing offcanvas from DOM
     * @private
     */
    _removeExistingOffCanvas() {
        const offCanvasElements = this.getOffCanvas();
        return Iterator.iterate(offCanvasElements, offCanvas => offCanvas.remove());
    }

    /**
     * Defines the position of the offcanvas by setting css class
     * @param {'left'|'right'|'bottom'} position
     * @returns {string}
     * @private
     */
    _getPositionClass(position) {
        /**
         * @deprecated tag:v6.5.0 - Bootstrap v5 uses `offcanvas-*` classes for positions
         * @see https://getbootstrap.com/docs/5.1/components/offcanvas/#placement
         */
        if (Feature.isActive('v6.5.0.0')) {
            if (position === 'left') {
                return 'offcanvas-start';
            }

            if (position === 'right') {
                return 'offcanvas-end';
            }

            return `offcanvas-${position}`;
        }
        return `is-${position}`;
    }

    /**
     * Creates the offcanvas element prototype including all relevant settings,
     * appends it to the DOM and returns the HTMLElement for further processing
     * @param {'left'|'right'|'bottom'} position
     * @param {boolean} fullwidth
     * @param {array|string} cssClass
     * @returns {HTMLElement}
     * @private
     */
    _createOffCanvas(position, fullwidth, cssClass) {
        const offCanvas = document.createElement('div');
        offCanvas.classList.add(OFF_CANVAS_CLASS);
        offCanvas.classList.add(this._getPositionClass(position));

        if (fullwidth === true) {
            offCanvas.classList.add(OFF_CANVAS_FULLWIDTH_CLASS);
        }

        if (cssClass) {
            const type = typeof cssClass;

            if (type === 'string') {
                offCanvas.classList.add(cssClass);
            } else if (Array.isArray(cssClass)) {
                cssClass.forEach((value) => {
                    offCanvas.classList.add(value);
                });
            } else {
                throw new Error(`The type "${type}" is not supported. Please pass an array or a string.`);
            }
        }

        document.body.appendChild(offCanvas);

        /** @deprecated tag:v6.5.0 - Initialize Bootstrap v5 Offcanvas plugin on created DOM element */
        if (Feature.isActive('v6.5.0.0')) {
            OffCanvasSingleton.bsOffcanvas = new bootstrap.Offcanvas(offCanvas);
        }

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
     * @param {'left'|'right'|'bottom'} position
     * @param {boolean} closable
     * @param {number} delay
     * @param {boolean} fullwidth
     * @param {array|string} cssClass
     */
    static open(content, callback = null, position = 'left', closable = true, delay = REMOVE_OFF_CANVAS_DELAY, fullwidth = false, cssClass = '') {
        OffCanvasInstance.open(content, callback, position, closable, delay, fullwidth, cssClass);
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
