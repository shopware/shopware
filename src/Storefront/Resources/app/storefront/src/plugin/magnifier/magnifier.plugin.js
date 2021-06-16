import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';
import { Vector2 } from 'src/helper/vector.helper';
import ViewportDetection from 'src/helper/viewport-detection.helper';
import Iterator from 'src/helper/iterator.helper';

const PORTRAIT_ORIENTATION = 1;
const LANDSCAPE_ORIENTATION = 0;

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
        zoomFactor: 3,

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
         * scale size for zoomed image element
         *
         * @type boolean
         */
        scaleZoomImage: false,

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
                const imageOffset = containerPos.subtract(imagePos);
                imageOffset.x = Math.abs(imageOffset.x);
                imageOffset.y = Math.abs(imageOffset.y);

                const mousePos = new Vector2(event.pageX, event.pageY).subtract(imagePos);

                const mousePosPercent = mousePos.divide(imageSize).clamp(0, 1);

                this._setOverlayPosition(imageOffset, overlaySize, imageSize, mousePosPercent);
                this._setZoomImage(mousePos, imageSize, overlaySize, imageDimensions);
            }
        }

        this.$emitter.publish('onMouseMove');
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
     * @param {Vector2} mousePos
     * @param {Vector2} imageSize
     * @param {Vector2} overlaySize
     * @param {Vector2} imageDimensions
     *
     * @private
     */
    _setZoomImage(mousePos, imageSize, overlaySize, imageDimensions) {
        this._setZoomImageSize(imageSize);

        // set background image
        this._zoomImage.style.backgroundImage = `url('${this._imageUrl}')`;

        // set background image size
        const zoomImageBackgroundSize = this.calculateZoomBackgroundImageSize(imageDimensions, imageSize);
        this._zoomImage.style.backgroundSize = `${zoomImageBackgroundSize.x}px ${zoomImageBackgroundSize.y}px`;

        // set background image position
        const zoomImagePosPercent = this.calculateZoomImageBackgroundPosition(mousePos, imageSize, overlaySize, imageDimensions, zoomImageBackgroundSize);
        this._zoomImage.style.backgroundPosition = `-${zoomImagePosPercent.x}px -${zoomImagePosPercent.y}px`;

        this.$emitter.publish('setZoomImagePosition');
    }

    /**
     * @param {Vector2} imageSize
     * @private
     */
    _setZoomImageSize(imageSize) {
        const factor = imageSize.y / imageSize.x;
        const zoomImageSize = this._getZoomImageSize();
        const height = this.options.scaleZoomImage ? zoomImageSize.x * factor : zoomImageSize.y;
        this._zoomImage.style.height = `${height}px`;
        this._zoomImage.style.minHeight = `${height}px`;
    }

    /**
     * calculate the percentage position
     * when the image factors mismatch
     *
     * @param {Vector2} mousePos
     * @param {Vector2} imageSize
     * @param {Vector2} overlaySize
     * @param {Vector2} imageDimensions
     * @param {Vector2} zoomImageBackgroundSize
     *
     * @returns {Vector2}
     */
    calculateZoomImageBackgroundPosition(mousePos, imageSize, overlaySize, imageDimensions, zoomImageBackgroundSize) {
        const maxOverlayRange = imageSize.subtract(imageSize.divide(this.options.zoomFactor)).subtract(new Vector2(1, 1));
        let position = mousePos.subtract(overlaySize.divide(2)).clamp(0, imageSize.subtract(overlaySize)).divide(maxOverlayRange).clamp(0, 1);
        const orientation = this.getImageOrientation(imageDimensions, imageSize);
        const percentWidthWithoutLens = 1 - 1 / this.options.zoomFactor;

        if (orientation === LANDSCAPE_ORIENTATION) {
            position = position.multiply(new Vector2(percentWidthWithoutLens, 1));
            position = this.calculateImagePosition(position, imageSize, imageDimensions, 'y', 'x');
            position = position.multiply(new Vector2(1, percentWidthWithoutLens));
        } else if (orientation === PORTRAIT_ORIENTATION) {
            position = position.multiply(new Vector2(1, percentWidthWithoutLens));
            position = this.calculateImagePosition(position, imageSize, imageDimensions, 'x', 'y');
            position = position.multiply(new Vector2(percentWidthWithoutLens, 1));

        }

        return zoomImageBackgroundSize.multiply(position);
    }

    /**
     * @param position
     * @param imageSize
     * @param imageDimensions
     * @param coordOne
     * @param coordTwo
     *
     * @returns {*}
     */
    calculateImagePosition(position, imageSize, imageDimensions, coordOne, coordTwo) {
        const compressedImageSize = (imageDimensions[coordOne] * (imageSize[coordTwo] / imageSize[coordOne]));
        const offsetPercent = (1 - (compressedImageSize / (imageDimensions[coordTwo] / 1))) / 2;
        position[coordTwo] = this.calculateOffsetPercent(offsetPercent, position[coordTwo]);

        return position;
    }

    calculateOffsetPercent(offset, percent) {
        return offset + ((1 - (offset * 2)) * percent);
    }

    /**
     * @param {Vector2} imageDimensions
     * @param {Vector2} imageSize
     * @returns {Vector2}
     */
    calculateZoomBackgroundImageSize(imageDimensions, imageSize) {
        const orientation = this.getImageOrientation(imageDimensions, imageSize);
        const zoomImageSize = this._getZoomImageSize();
        let size = new Vector2(0, 0);

        if (orientation === PORTRAIT_ORIENTATION) {
            size = new Vector2(zoomImageSize.x, zoomImageSize.x * imageDimensions.y / imageDimensions.x);
        } else if (orientation === LANDSCAPE_ORIENTATION) {
            size = new Vector2(zoomImageSize.y * imageDimensions.x / imageDimensions.y, zoomImageSize.y);
        }

        return size.multiply(this.options.zoomFactor);
    }

    /**
     * returns the orientation of the detail image
     * landscape or portrait
     *
     * @param imageDimensions
     * @param imageSize
     * @returns {LANDSCAPE_ORIENTATION|PORTRAIT_ORIENTATION}
     */
    getImageOrientation(imageDimensions, imageSize) {
        if (this._assertEqualFactors(imageDimensions, imageSize)) {
            return (imageSize.x > imageSize.y) ? LANDSCAPE_ORIENTATION : PORTRAIT_ORIENTATION;
        }

        return (imageSize.x / imageSize.y > imageDimensions.x / imageDimensions.y) ? PORTRAIT_ORIENTATION : LANDSCAPE_ORIENTATION;
    }

    /**
     * @param imageDimensions
     * @param imageSize
     * @returns {boolean}
     *
     * @private
     */
    _assertEqualFactors(imageDimensions, imageSize) {
        const imageDimensionFactor = this._roundToTwoDigits(imageDimensions.x / imageDimensions.y);
        const imageSizeFactor = this._roundToTwoDigits(imageSize.x / imageSize.y);

        return imageSizeFactor === imageDimensionFactor;
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
        const { naturalWidth: width, naturalHeight: height } = image;
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
        const overlaySize = zoomImageSize.divide(this.options.zoomFactor);

        this._overlay.style.width = `${Math.ceil(overlaySize.x)}px`;
        this._overlay.style.height = `${Math.ceil(overlaySize.y)}px`;
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

    /**
     * rounds value to two decimal places
     *
     * @param value
     * @returns {*}
     *
     * @private
     */
    _roundToTwoDigits(value) {
        return Math.round(value * 1000) / 1000;
    }
}
