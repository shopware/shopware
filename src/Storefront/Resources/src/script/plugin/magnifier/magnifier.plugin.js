import Plugin from 'src/script/plugin-system/plugin.class';
import DomAccess from 'src/script/helper/dom-access.helper';
import { Vector2 } from 'src/script/helper/vector.helper';
import ViewportDetection from 'src/script/helper/viewport-detection.helper';
import Iterator from 'src/script/helper/iterator.helper';

/**
 * handles the magnifier lens functionality
 * on the detail page
 *
 * MagnifyLensPlugin class
 */
export default class MagnifierPlugin extends Plugin {

    static options = {

        /**
         * multiplier of how far the image should be zoomed in
         *
         * @type number
         */
        zoomFactor: 5,

        /**
         * container for the image
         *
         * @type string
         */
        imageContainerSelector: '.js-magnifier-container',

        /**
         * selector of the image in which the overlay should be created
         *
         * @type string
         */
        imageSelector: '.js-magnifier-image',

        /**
         * data attribute for the high resolution image
         *
         * @type string
         */
        fullImageDataAttribute: 'data-full-image',

        /**
         * class for the container in which the zoomed image should be created
         *
         * @type string
         */
        zoomImageContainerSelector: '.js-magnifier-zoom-image-container',

        /**
         * class for the image overlay
         *
         * @type string
         */
        overlayClass: 'js-magnifier-overlay',

        /**
         * class for the zoomed image element
         *
         * @type string
         */
        zoomImageClass: 'js-magnifier-zoom-image',

        /**
         * magnified image is over gallery
         *
         * @type boolean
         */
        magnifierOverGallery: false,

        /**
         * css cursor type when zoom is active
         *
         * @type string
         */
        cursorType: 'none',
    };

    init() {
        this._imageContainers = DomAccess.querySelectorAll(this.el, this.options.imageContainerSelector);

        if (this.options.magnifierOverGallery) {
            this._zoomImageContainer = DomAccess.querySelector(this.el, this.options.zoomImageContainerSelector);
        } else {
            this._zoomImageContainer = DomAccess.querySelector(document, this.options.zoomImageContainerSelector);
        }

        this._registerEvents();
    }

    /**
     * Registers all necessary event listeners.
     */
    _registerEvents() {
        Iterator.iterate(this._imageContainers, imageContainer => {
            const image = DomAccess.querySelector(imageContainer, this.options.imageSelector, false);
            if (image) {
                image.addEventListener('mousemove', (event) => this._onMouseMove(event, imageContainer, image), false);
                imageContainer.addEventListener('mouseout', (event) => this._stopMagnify(event), false);
            }
        });
    }

    /**
     * returns whether or not the lens should be active
     *
     * @return {boolean}
     * @private
     */
    _isActive() {
        const allowedViewports = [
            ViewportDetection.isLG(),
            ViewportDetection.isXL(),
        ];

        return allowedViewports.indexOf(true) !== -1;
    }

    /**
     * @param {HTMLElement} el
     * @param {string} type
     * @private
     */
    _setCursor(el, type) {
        if (el) el.style.cursor = type;
    }

    /**
     * Eventhandler for handling the
     * mouse movement on the image container.
     *
     * @param {Event} event
     * @param {HTMLElement} imageContainer
     * @param {HTMLElement} image
     */
    _onMouseMove(event, imageContainer, image) {

        if (this._isActive()) {
            this._setCursor(image, this.options.cursorType);
            this._createOverlay(imageContainer);
            this._createZoomImage();
            this._getImageUrl(image);

            if (this._imageUrl && this._zoomImage && this._overlay) {
                const containerPos = this._getContainerPos(imageContainer);
                const imagePos = this._getImagePos(image);
                const imageDimensions = this._getImageDimensions(image);
                const imageSize = this._getImageSize(image);
                const overlaySize = this._getOverlaySize(imageSize);
                const imageOffset = containerPos.subtract(imagePos).abs();
                const mousePos = new Vector2(event.pageX, event.pageY).subtract(imagePos);
                const mousePosPercent = mousePos.divide(imageSize).clamp(0, 1);

                this._setZoomImageSize(imageSize);
                const overlayPos = this._setOverlayPosition(imageOffset, overlaySize, imageSize, mousePosPercent);
                this._setZoomImage(overlayPos, overlaySize, imageOffset, imageDimensions, imageSize);
            }
        }

        this.$emitter.publish('onMouseMove');
    }

    /**
     * @param {Vector2} imageSize
     * @private
     */
    _setZoomImageSize(imageSize) {
        const zoomImageSize = this._getZoomImageSize();
        const height = imageSize.y * (zoomImageSize.x / imageSize.x);
        this._zoomImage.height = `${height}px`;
    }

    /**
     * sets the position of the overlay
     *
     * @param {Vector2} imageOffset
     * @param {Vector2} overlaySize
     * @param {Vector2} imageSize
     * @param {Vector2} mousePosPercent
     * @return {VectorBase|*}
     * @private
     */
    _setOverlayPosition(imageOffset, overlaySize, imageSize, mousePosPercent) {
        let overlayPos = imageOffset.subtract(overlaySize.divide(2)); // offset the lens so that the cursor is in the middle
        overlayPos = overlayPos.add(imageSize.multiply(mousePosPercent)); // add the mouse offset
        overlayPos = overlayPos.clamp(imageOffset, imageOffset.add(imageSize).subtract(overlaySize)); // clamp the position to image min max
        this._overlay.style.left = `${overlayPos.x}px`;
        this._overlay.style.top = `${overlayPos.y}px`;

        return overlayPos;
    }

    /**
     *  sets the background position of the zoomed image
     *
     * @param {Vector2} overlayPos
     * @param {Vector2} overlaySize
     * @param {Vector2} imageOffset
     * @param {Vector2} imageDimensions
     * @param {Vector2} imageSize
     *
     * @private
     */
    _setZoomImage(overlayPos, overlaySize, imageOffset, imageDimensions, imageSize) {
        // set background image
        this._zoomImage.style.backgroundImage = `url('${this._imageUrl}')`;

        // set background image size
        const relativeImageSize = this.calculateRelativeImageSize(imageDimensions, imageSize);
        const zoomImageBackgroundSize = relativeImageSize.multiply(this.options.zoomFactor).ceil();
        this._zoomImage.style.backgroundSize = `${zoomImageBackgroundSize.x}px ${zoomImageBackgroundSize.y}px`;

        // set background image position
        const overlayPosPercent = this.caclulateRelativeOverlayPosInPercent(overlayPos, overlaySize, imageDimensions, imageSize);
        this._zoomImage.style.backgroundPosition = `${overlayPosPercent.x}% ${overlayPosPercent.y}%`;

        this.$emitter.publish('setZoomImagePosition');
    }

    /**
     * calculate the percentage position
     * when the image factors mismatch
     *
     * @param {Vector2} overlayPos
     * @param {Vector2} overlaySize
     * @param {Vector2} imageDimensions
     * @param {Vector2} imageSize
     * @returns {Vector2}
     */
    caclulateRelativeOverlayPosInPercent(overlayPos, overlaySize, imageDimensions, imageSize) {
        const orientation = this.getImageOrientation(imageDimensions, imageSize);

        if (orientation === 'landscape') {
            const imageFactor = imageDimensions.y / imageDimensions.x;
            const height = imageSize.x * imageFactor;
            const difference = (height - imageSize.y) / 2;
            overlayPos.y += difference;
            overlayPos = overlayPos.divide(imageSize.x - overlaySize.x, height - overlaySize.y);
        } else if (orientation === 'portrait') {
            const imageFactor = imageDimensions.x / imageDimensions.y;
            const width = imageSize.y * imageFactor;
            const difference = (width - imageSize.x) / 2;
            overlayPos.x += difference;
            overlayPos = overlayPos.divide(width - overlaySize.x, imageSize.y - overlaySize.y);
        }

        return overlayPos.multiply(100);
    }

    /**
     * @param {Vector2} imageDimensions
     * @param {Vector2} imageSize
     * @returns {Vector2}
     */
    calculateRelativeImageSize(imageDimensions, imageSize) {
        const orientation = this.getImageOrientation(imageDimensions, imageSize);
        const zoomImageSize = this._getZoomImageSize();

        if (orientation === 'landscape') {
            const imageFactor = imageDimensions.y / imageDimensions.x;
            return new Vector2(zoomImageSize.x, zoomImageSize.x * imageFactor);
        } else if (orientation === 'portrait') {
            const imageFactor = imageDimensions.x / imageDimensions.y;
            return new Vector2(zoomImageSize.y * imageFactor, zoomImageSize.y);
        }

        return new Vector2();
    }

    /**
     * returns the orientation of the detail image
     * landscape or portrait
     *
     * @param imageDimensions
     * @param imageSize
     * @returns {string}
     */
    getImageOrientation(imageDimensions, imageSize) {
        const isLandscape = (imageSize.x > imageSize.y > imageDimensions.x / imageDimensions.y);
        if (isLandscape) {
            return 'landscape';
        } else {
            return 'portrait';
        }
    }

    /**
     * @param {HTMLElement} imageContainer
     * @return {Vector2}
     *
     * @private
     */
    _getContainerPos(imageContainer) {
        const containerBoundingRect = imageContainer.getBoundingClientRect();
        return new Vector2(containerBoundingRect.left + window.pageXOffset, containerBoundingRect.top + window.pageYOffset);
    }

    /**
     * @param {HTMLElement} image
     * @return {Vector2}
     * @private
     */
    _getImagePos(image) {
        const imageBoundingRect = image.getBoundingClientRect();
        return new Vector2(imageBoundingRect.left + window.pageXOffset, imageBoundingRect.top + window.pageYOffset);
    }

    /**
     * @param {HTMLElement} image
     * @return {Vector2}
     *
     * @private
     */
    _getImageDimensions(image) {
        const { width, height } = image.dataset;
        return new Vector2(width, height);
    }

    /**
     * @param {HTMLElement} image
     * @return {Vector2}
     *
     * @private
     */
    _getImageSize(image) {
        const imageBoundingRect = image.getBoundingClientRect();
        return new Vector2(imageBoundingRect.width, imageBoundingRect.height);
    }

    /**
     * @return {Vector2}
     * @private
     */
    _getZoomImageSize() {
        const imageBoundingRect = this._zoomImage.getBoundingClientRect();
        return new Vector2(imageBoundingRect.width, imageBoundingRect.height);
    }

    /**
     * @param {Vector2} zoomImageSize
     *
     * @return {VectorBase|*}
     * @private
     */
    _getOverlaySize(zoomImageSize) {
        const overlaySize = zoomImageSize.divide(this.options.zoomFactor).ceil();
        this._overlay.style.width = `${overlaySize.x}px`;
        this._overlay.style.height = `${overlaySize.y}px`;
        return overlaySize;
    }

    /**
     * creates the image overlay element
     *
     * @param container
     * @return {HTMLElement|any}
     * @private
     */
    _createOverlay(container) {
        this._overlay = container.querySelector(`.${this.options.overlayClass}`);
        if (this._overlay) {
            return this._overlay;
        }

        const html = `<div class="magnifier-overlay  ${this.options.overlayClass}">&nbsp;</div>`;
        this._overlay = container.insertAdjacentHTML('beforeend', html);

        this.$emitter.publish('createOverlay');

        return this._overlay;
    }

    /**
     * removes the image overlay element
     *
     * @return {HTMLElement|any}
     * @private
     */
    _removeOverlay() {
        const overlays = document.querySelectorAll(`.${this.options.overlayClass}`);
        Iterator.iterate(overlays, overlay => overlay.remove());

        this.$emitter.publish('removeOverlay');
    }

    /**
     * creates the zoom image element
     *
     * @return {HTMLElement}
     * @private
     */
    _createZoomImage() {
        this._zoomImage = this._zoomImageContainer.querySelector(`.${this.options.zoomImageClass}`);

        if (this._zoomImage) {
            return this._zoomImage;
        }

        this._zoomImageContainer.style.position = 'relative';
        const html = `<div class="magnifier-zoom-image  ${this.options.zoomImageClass}">&nbsp;</div>`;
        this._zoomImage = this._zoomImageContainer.insertAdjacentHTML('beforeend', html);

        this.$emitter.publish('createZoomImage');

        return this._zoomImage;
    }

    /**
     * removes the zoom image element
     *
     * @private
     */
    _removeZoomImage() {
        const zoomImages = document.querySelectorAll(`.${this.options.zoomImageClass}`);
        Iterator.iterate(zoomImages, zoomImage => zoomImage.remove());

        this.$emitter.publish('removeZoomImage');
    }

    /**
     * sets the image url
     *
     * @param {HTMLElement} image
     * @private
     */
    _getImageUrl(image) {
        this._imageUrl = DomAccess.getDataAttribute(image, this.options.fullImageDataAttribute);

        this.$emitter.publish('getImageUrl');
    }

    /**
     * stops the magnify effect
     *
     * @private
     */
    _stopMagnify() {
        this._removeZoomImage();
        this._removeOverlay();

        const images = DomAccess.querySelectorAll(document, this.options.imageSelector);
        Iterator.iterate(images, image => this._setCursor(image, 'default'));

        this.$emitter.publish('stopMagnify');
    }
}
