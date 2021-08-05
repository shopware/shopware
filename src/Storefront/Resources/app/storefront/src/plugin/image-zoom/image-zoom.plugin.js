import Plugin from 'src/plugin-system/plugin.class';
import Hammer from 'hammerjs';
import DomAccess from 'src/helper/dom-access.helper';
import { Vector2, Vector3 } from 'src/helper/vector.helper';

/**
 * ImageZoomPlugin class
 */
export default class ImageZoomPlugin extends Plugin {

    static options = {

        /**
         * maximum zoom of the image
         * or 'auto' for automatic calculation
         *
         * @type string|number
         */
        maxZoom: 'auto',

        /**
         * amount of steps for the zoom to be at its max/min values
         * when the action buttons are pressed
         *
         * @type string|number
         */
        zoomSteps: 5,

        /**
         * selector for the zoom modal
         *
         * @type string
         */
        imageZoomModalSelector: '[data-image-zoom-modal=true]',

        /**
         * selector for the image to be zoomed
         *
         * @type string
         */
        imageSelector: '.js-image-zoom-element',

        /**
         * selector for the element which will zoom the image in
         *  when clicked
         *
         * @type string
         */
        zoomInActionSelector: '.js-image-zoom-in',

        /**
         * selector for the element which will reset the zoom
         *  when clicked
         *
         * @type string
         */
        zoomResetActionSelector: '.js-image-zoom-reset',

        /**
         * selector for the element which will zoom the image out
         * when clicked
         *
         * @type string
         */
        zoomOutActionSelector: '.js-image-zoom-out',


        /**
         * selector to determent if the image is active at the moment
         * set to fall if this should be ignored
         *
         * @type string|boolean
         */
        activeClassSelector: '.tns-slide-active',

        /**
         * selector for the gallery slider
         *
         * @type string
         */
        gallerySliderSelector: '[data-modal-gallery-slider]',
    };

    /**
     * init the plugin
     */
    init() {
        this._modal = this.el.closest(this.options.imageZoomModalSelector);
        this._image = DomAccess.querySelector(this.el, this.options.imageSelector);
        this._zoomInActionElement = DomAccess.querySelector(this._modal, this.options.zoomInActionSelector);
        this._zoomResetActionElement = DomAccess.querySelector(this._modal, this.options.zoomResetActionSelector);
        this._zoomOutActionElement = DomAccess.querySelector(this._modal, this.options.zoomOutActionSelector);

        this._imageMaxSize = new Vector2(this._image.naturalWidth, this._image.naturalHeight);
        this._imageSize = new Vector2(this._image.offsetWidth, this._image.offsetHeight);
        this._containerSize = new Vector2(this.el.offsetWidth, this.el.offsetHeight);

        this._storedTransform = new Vector3(0, 0, 1);
        this._transform = new Vector3(this._storedTransform.x, this._storedTransform.y, this._storedTransform.z);
        this._translateRange = new Vector2(0, 0);

        this._updateTranslateRange();
        this._initHammer();
        this._registerEvents();
        this._setActionButtonState();
    }

    /**
     * updates the zoom values
     */
    update() {
        this._updateTransform();
        this._setActionButtonState();
    }

    /**
     * init hammer instance
     *
     * @private
     */
    _initHammer() {
        this._hammer = new Hammer(this.el);
        this._hammer.get('pinch').set({ enable: true });
        this._hammer.get('pan').set({ direction: Hammer.DIRECTION_ALL });
    }

    /**
     * register all needed events
     *
     * @private
     */
    _registerEvents() {
        this._hammer.on('pan', event => this._onPan(event));
        this._hammer.on('pinch pinchmove', event => this._onPinch(event));
        this._hammer.on('doubletap', event => this._onDoubleTap(event));
        this._hammer.on('panend pancancel pinchend pinchcancel', event => this._onInteractionEnd(event));

        this.el.addEventListener('wheel', event => this._onMouseWheel(event), false);
        this._image.addEventListener('mousedown', event => event.preventDefault(), false);
        window.addEventListener('resize', event => this._onResize(event), false);

        this._zoomInActionElement.addEventListener('click', event => this._onZoomIn(event), false);
        this._zoomResetActionElement.addEventListener('click', event => this._onResetZoom(event), false);
        this._zoomOutActionElement.addEventListener('click', event => this._onZoomOut(event), false);
    }

    /**
     * returns if the current element is active
     *
     * @return {boolean}
     *
     * @private
     */
    _isActive() {
        if (this.options.activeClassSelector === false) return true;

        return this.el.closest(this.options.activeClassSelector) !== null;
    }

    /**
     * listener for panning
     *
     * @param {Event} event
     * @private
     */
    _onPan(event) {
        if (this._isActive()) {
            this._transform = this._storedTransform.add(new Vector3(event.deltaX, event.deltaY, 0));
            this._unsetTransition();
            this._updateTransform();
            this._setCursor('move');
        }

        this.$emitter.publish('onPan');
    }

    /**
     * listener for pinching
     *
     * @param {Event} event
     * @private
     */
    _onPinch(event) {
        if (this._isActive()) {
            const x = this._storedTransform.x + event.deltaX;
            const y = this._storedTransform.x + event.deltaY;
            const z = this._storedTransform.z * event.scale;

            this._transform = new Vector3(x, y, z);
            this._unsetTransition();
            this._updateTransform();
            this._setCursor('move');
        }

        this.$emitter.publish('onPinch');
    }

    /**
     * listener for double tapping
     *
     * @private
     */
    _onDoubleTap() {
        if (this._isActive()) {
            const maxZoom = this._getMaxZoomValue();
            const z = (this._storedTransform.z >= maxZoom) ? 1 : maxZoom;

            this._transform = new Vector3(
                this._transform.x,
                this._transform.y,
                z
            );

            this._setTransition();
            this._updateTransform(true);
        }

        this.$emitter.publish('onDoubleTap');
    }

    /**
     * listener for zooming in
     *
     * @private
     */
    _onZoomIn() {
        if (this._isActive()) {
            const zoomAmount = this._getMaxZoomValue() / this.options.zoomSteps;
            this._transform = this._transform.add(new Vector3(0, 0, zoomAmount));
            this._setTransition();
            this._updateTransform(true);
        }

        this.$emitter.publish('onZoomIn');
    }

    /**
     * listener for resetting zoom
     *
     * @private
     */
    _onResetZoom() {
        if (this._isActive()) {
            this._transform = new Vector3(
                this._transform.x,
                this._transform.y,
                1
            );

            this._setTransition();
            this._updateTransform(true);
        }

        this.$emitter.publish('onResetZoom');
    }

    /**
     * listener for zooming out
     *
     * @private
     */
    _onZoomOut() {
        if (this._isActive()) {
            const zoomAmount = this._getMaxZoomValue() / this.options.zoomSteps;
            this._transform = this._transform.subtract(new Vector3(0, 0, zoomAmount));
            this._setTransition();
            this._updateTransform(true);
        }

        this.$emitter.publish('onZoomOut');
    }

    /**
     * listener for the mousewheel
     *
     * @param {Event} event
     * @private
     */
    _onMouseWheel(event) {
        if (this._isActive()) {
            this._transform = this._transform.add(new Vector3(0, 0, (event.wheelDelta / 800)));
            this._unsetTransition();
            this._updateTransform(true);
        }

        this.$emitter.publish('onMouseWheel');
    }

    /**
     * callback when interaction with zoom container ends
     *
     * @private
     */
    _onInteractionEnd() {
        if (this._isActive()) {
            this._updateTransform(true);
            this._setCursor('default');
        }

        this.$emitter.publish('onInteractionEnd');
    }

    /**
     * listener for resize
     * updates needed values on resize
     *
     * @private
     */
    _onResize() {
        this._getElementSizes();
        this._updateTransform(true);

        this.$emitter.publish('onResize');
    }

    /**
     * sets to needed element sizes
     * to the current context
     *
     * @private
     */
    _getElementSizes() {
        this._imageSize = new Vector2(this._image.offsetWidth, this._image.offsetHeight);
        this._containerSize = new Vector2(this.el.offsetWidth, this.el.offsetHeight);

        this.$emitter.publish('getElementSizes');
    }

    /**
     * updates the image transform values
     *
     * @param updateStoredTransform
     * @private
     */
    _updateTransform(updateStoredTransform) {
        this._updateTranslateRange();
        this._clampTransform();
        this._setActionButtonState();

        const translateX = `translateX(${Math.round(this._transform.x)}px)`;
        const translateY = `translateY(${Math.round(this._transform.y)}px)`;
        const scale = `scale(${this._transform.z},${this._transform.z})`;

        const transform = `${translateX} ${translateY} translateZ(0px) ${scale}`;
        this._image.style.transform = transform;
        this._image.style.WebkitTransform = transform;
        this._image.style.msTransform = transform;

        if (updateStoredTransform) {
            this._updateStoredTransformVector();
        }

        this.$emitter.publish('updateTransform');
    }

    /**
     * sets the button state according to the zoom value
     *
     * @private
     */
    _setActionButtonState() {
        if (this._transform.z === 1 && this._getMaxZoomValue() === 1) {
            this._setButtonDisabledState(this._zoomResetActionElement);
            this._setButtonDisabledState(this._zoomOutActionElement);
            this._setButtonDisabledState(this._zoomInActionElement);
        } else if (this._getMaxZoomValue() === this._transform.z && this._isTranslatable()) {
            this._setButtonDisabledState(this._zoomResetActionElement);
            this._setButtonDisabledState(this._zoomOutActionElement);
            this._setButtonDisabledState(this._zoomInActionElement);
        } else if (this._getMaxZoomValue() === this._transform.z) {
            this._unsetButtonDisabledState(this._zoomResetActionElement);
            this._unsetButtonDisabledState(this._zoomOutActionElement);
            this._setButtonDisabledState(this._zoomInActionElement);
        } else if (this._transform.z === 1) {
            this._setButtonDisabledState(this._zoomResetActionElement);
            this._setButtonDisabledState(this._zoomOutActionElement);
            this._unsetButtonDisabledState(this._zoomInActionElement);
        } else {
            this._unsetButtonDisabledState(this._zoomResetActionElement);
            this._unsetButtonDisabledState(this._zoomOutActionElement);
            this._unsetButtonDisabledState(this._zoomInActionElement);
        }

        this.$emitter.publish('setActionButtonState');
    }

    /**
     * returns if the element has a translatable range or not
     * @returns {boolean}
     * @private
     */
    _isTranslatable(){
        return this._translateRange.x === 0 && this._translateRange.y === 0;
    }

    /**
     * toggle the active state of the zoom in action element
     *
     * @private
     */
    _setButtonDisabledState(el) {
        el.classList.add('disabled');
        el.disabled = true;

        this.$emitter.publish('setButtonDisabledState');
    }

    /**
     * toggle the active state of the zoom in action element
     *
     * @private
     */
    _unsetButtonDisabledState(el) {
        el.classList.remove('disabled');
        el.disabled = false;

        this.$emitter.publish('unsetButtonDisabledState');
    }

    /**
     * updates the stored transform vector
     *
     * @private
     */
    _updateStoredTransformVector() {
        this._clampTransform();
        this._storedTransform = new Vector3(this._transform.x, this._transform.y, this._transform.z);
    }

    /**
     * updates the x/y translate range for the image
     *
     * @private
     */
    _updateTranslateRange() {
        this._getElementSizes();
        const scaledImageSize = this._imageSize.multiply(this._transform.z);
        scaledImageSize.x = Math.round(scaledImageSize.x);
        scaledImageSize.y = Math.round(scaledImageSize.y);

        this._translateRange = scaledImageSize.subtract(this._containerSize).clamp(0, scaledImageSize).divide(2);
    }

    /**
     * returns the max zoom value of the element
     *
     * @return {number}
     * @private
     */
    _getMaxZoomValue() {
        this._getElementSizes();

        if (this._imageSize.x === 0 || this._imageSize.y === 0) {
            return 1;
        }

        const max = this._imageMaxSize.divide(this._imageSize);

        return Math.max(max.x, max.y);
    }

    /**
     * @param type
     * @private
     */
    _setCursor(type) {
        this.el.style.cursor = type;

        this.$emitter.publish('setCursor');
    }

    /**
     * sets the image transition
     *
     * @private
     */
    _setTransition() {
        const transition = 'all 350ms ease 0s';

        this._image.style.transition = transition;
        this._image.style.WebkitTransition = transition;
        this._image.style.msTransition = transition;

        this.$emitter.publish('setTransition');
    }

    /**
     * unsets the image transition
     *
     * @private
     */
    _unsetTransition() {
        const transition = '';

        this._image.style.transition = transition;
        this._image.style.WebkitTransition = transition;
        this._image.style.msTransition = transition;

        this.$emitter.publish('unsetTransition');
    }

    /**
     * clamps the vector to its possible min/max values
     *
     * @private
     */
    _clampTransform() {
        const minVector = new Vector3(-this._translateRange.x, -this._translateRange.y, 1);
        const maxVector = new Vector3(this._translateRange.x, this._translateRange.y, this._getMaxZoomValue());

        this._transform = this._transform.clamp(minVector, maxVector);
    }
}
