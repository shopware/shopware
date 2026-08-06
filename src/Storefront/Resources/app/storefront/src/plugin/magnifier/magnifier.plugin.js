import Plugin from 'src/plugin-system/plugin.class';
import { Vector2 } from 'src/helper/vector.helper';
import ViewportDetection from 'src/helper/viewport-detection.helper';

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
         * keep the aspect ratio of the image when zoomed
         *
         * @type boolean
         */
        keepAspectRatioOnZoom: true,

        /**
         * css cursor type when zoom is active
         *
         * @type string
         */
        cursorType: 'none',
    };

    init() {
        this._imageContainers = this.el.querySelectorAll(this.options.imageContainerSelector);

        if (this.options.magnifierOverGallery) {
            this._zoomImageContainer = this.el.querySelector(this.options.zoomImageContainerSelector);
        } else {
            this._zoomImageContainer = document.querySelector(this.options.zoomImageContainerSelector);
        }

        if (!this._zoomImageContainer) {
            return;
        }

        this._registerEvents();
    }

    /**
     * Registers all necessary event listeners.
     */
    _registerEvents() {
        this._imageContainers.forEach(imageContainer => {
            const image = imageContainer.querySelector(this.options.imageSelector);
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
            ViewportDetection.isXXL(),
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

            const geometry = this._getZoomGeometry(image);

            if (this._imageUrl && this._zoomImage && this._overlay && geometry) {
                const containerPos = this._getContainerPos(imageContainer);
                const imagePos = this._getImagePos(image);
                const imageOffset = containerPos.subtract(imagePos);
                imageOffset.x = Math.abs(imageOffset.x);
                imageOffset.y = Math.abs(imageOffset.y);

                this._setZoomImageSize(geometry.region.size);

                const zoomWindowSize = this._getZoomImageSize();
                const zoomFactor = this._getEffectiveZoomFactor(geometry.region.size, zoomWindowSize);
                const overlaySize = this._getOverlaySize(geometry.region.size, zoomWindowSize, zoomFactor);

                // mouse position relative to the visible image content, not to the image element
                const mousePos = new Vector2(event.pageX, event.pageY)
                    .subtract(imagePos)
                    .subtract(geometry.region.offset);
                const progress = this._getZoomProgress(mousePos, geometry.region.size, overlaySize);

                this._setOverlayPosition(imageOffset.add(geometry.region.offset), overlaySize, geometry.region.size, progress);
                this._setZoomImage(geometry, zoomWindowSize, zoomFactor, progress);
            }
        }

        this.$emitter.publish('onMouseMove');
    }

    /**
     * returns the geometry needed to map the lens onto the zoomed image
     *
     * `region` describes the area of the image element which actually renders image content,
     * `source` describes the part of the image which is rendered inside that area.
     * Both differ from the element itself as soon as `object-fit` letterboxes or crops the image.
     * A centered `object-position` (the CSS default and the storefront default) is assumed.
     *
     * @param {HTMLElement} image
     * @return {{natural: Vector2, scale: Vector2, region: {offset: Vector2, size: Vector2}, source: {offset: Vector2, size: Vector2}}|null}
     *
     * @private
     */
    _getZoomGeometry(image) {
        const elementSize = this._getImageSize(image);
        const natural = this._getImageDimensions(image);

        if (elementSize.x <= 0 || elementSize.y <= 0 || natural.x <= 0 || natural.y <= 0) {
            return null;
        }

        const scale = this._getObjectFitScale(image, elementSize, natural);
        const renderedSize = natural.multiply(scale);

        // the image content is either letterboxed inside the element or cropped by it
        const regionSize = new Vector2(Math.min(renderedSize.x, elementSize.x), Math.min(renderedSize.y, elementSize.y));
        const regionOffset = elementSize.subtract(regionSize).divide(2);
        const sourceSize = regionSize.divide(scale);
        const sourceOffset = natural.subtract(sourceSize).divide(2);

        return {
            natural,
            scale,
            region: { offset: regionOffset, size: regionSize },
            source: { offset: sourceOffset, size: sourceSize },
        };
    }

    /**
     * returns the scale factor the browser applies to the image because of `object-fit`
     *
     * @param {HTMLElement} image
     * @param {Vector2} elementSize
     * @param {Vector2} natural
     * @return {Vector2}
     *
     * @private
     */
    _getObjectFitScale(image, elementSize, natural) {
        const fitScale = elementSize.divide(natural);
        const objectFit = window.getComputedStyle(image).objectFit;

        switch (objectFit) {
            case 'fill':
                return fitScale;
            case 'cover':
                return new Vector2(Math.max(fitScale.x, fitScale.y), Math.max(fitScale.x, fitScale.y));
            case 'none':
                return new Vector2(1, 1);
            case 'scale-down':
                return new Vector2(Math.min(fitScale.x, fitScale.y, 1), Math.min(fitScale.x, fitScale.y, 1));
            case 'contain':
            default:
                // `contain` is the object-fit of the storefront gallery images and the safe fallback
                // for unknown values (e.g. an empty string returned by jsdom in the unit tests)
                return new Vector2(Math.min(fitScale.x, fitScale.y), Math.min(fitScale.x, fitScale.y));
        }
    }

    /**
     * returns the zoom factor which is actually applied
     *
     * The configured zoom factor is only usable as long as the zoomed image still covers the zoom window.
     * Otherwise the zoom window would render empty areas next to the image.
     *
     * @param {Vector2} regionSize
     * @param {Vector2} zoomWindowSize
     * @return {number}
     *
     * @private
     */
    _getEffectiveZoomFactor(regionSize, zoomWindowSize) {
        return Math.max(
            this.options.zoomFactor,
            zoomWindowSize.x / regionSize.x,
            zoomWindowSize.y / regionSize.y,
        );
    }

    /**
     * returns how far the lens is moved inside its range, per axis, in a range of 0 to 1
     *
     * @param {Vector2} mousePos
     * @param {Vector2} regionSize
     * @param {Vector2} overlaySize
     * @return {Vector2}
     *
     * @private
     */
    _getZoomProgress(mousePos, regionSize, overlaySize) {
        const range = regionSize.subtract(overlaySize);
        // offset the lens so that the cursor is in the middle
        const lensPos = mousePos.subtract(overlaySize.divide(2));

        return new Vector2(
            range.x > 0 ? lensPos.x / range.x : 0,
            range.y > 0 ? lensPos.y / range.y : 0,
        ).clamp(0, 1);
    }

    /**
     * sets the position of the overlay
     *
     * @param {Vector2} regionOffset
     * @param {Vector2} overlaySize
     * @param {Vector2} regionSize
     * @param {Vector2} progress
     * @return {Vector2}
     * @private
     */
    _setOverlayPosition(regionOffset, overlaySize, regionSize, progress) {
        const overlayPos = regionOffset.add(regionSize.subtract(overlaySize).multiply(progress));

        this._overlay.style.left = `${overlayPos.x}px`;
        this._overlay.style.top = `${overlayPos.y}px`;

        return overlayPos;
    }

    /**
     *  sets the background size and position of the zoomed image
     *
     * @param {Object} geometry
     * @param {Vector2} zoomWindowSize
     * @param {number} zoomFactor
     * @param {Vector2} progress
     *
     * @private
     */
    _setZoomImage(geometry, zoomWindowSize, zoomFactor, progress) {
        // set background image
        this._zoomImage.style.backgroundImage = `url('${this._imageUrl}')`;

        // set background image size
        const backgroundSize = this._getZoomBackgroundSize(geometry, zoomFactor);
        this._zoomImage.style.backgroundSize = `${backgroundSize.x}px ${backgroundSize.y}px`;

        // set background image position
        const backgroundOffset = this._getZoomBackgroundOffset(geometry, zoomWindowSize, zoomFactor, progress);
        this._zoomImage.style.backgroundPosition = `${-backgroundOffset.x}px ${-backgroundOffset.y}px`;

        this.$emitter.publish('setZoomImagePosition');
    }

    /**
     * returns the size the whole image is rendered with inside the zoom window
     *
     * @param {Object} geometry
     * @param {number} zoomFactor
     * @return {Vector2}
     *
     * @private
     */
    _getZoomBackgroundSize(geometry, zoomFactor) {
        return geometry.natural.multiply(geometry.scale).multiply(zoomFactor);
    }

    /**
     * returns the offset of the zoomed image inside the zoom window
     *
     * The pannable range is the difference between the zoomed image content and the zoom window,
     * which keeps every part of the image reachable for any zoom window size and aspect ratio.
     *
     * @param {Object} geometry
     * @param {Vector2} zoomWindowSize
     * @param {number} zoomFactor
     * @param {Vector2} progress
     * @return {Vector2}
     *
     * @private
     */
    _getZoomBackgroundOffset(geometry, zoomWindowSize, zoomFactor, progress) {
        const zoomedRegionSize = geometry.region.size.multiply(zoomFactor);
        const pannableRange = zoomedRegionSize.subtract(zoomWindowSize).clamp(0, Infinity);
        // skip the part of the image which is cropped by the image element
        const croppedOffset = geometry.source.offset.multiply(geometry.scale).multiply(zoomFactor);

        return croppedOffset.add(pannableRange.multiply(progress));
    }

    /**
     * @param {Vector2} imageSize
     * @private
     */
    _setZoomImageSize(imageSize) {
        const factor = imageSize.y / imageSize.x;
        const zoomImageSize = this._getZoomImageSize();
        const maxHeight = window.innerHeight / 2;
        const height = Math.min((this.options.keepAspectRatioOnZoom
            ? (this.options.scaleZoomImage ? zoomImageSize.x * factor : zoomImageSize.y)
            : zoomImageSize.x), maxHeight);
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
        let position = mousePos.subtract(overlaySize.divide(2)).clamp(0, imageSize.subtract(overlaySize)).divide(maxOverlayRange);
        const orientation = this.getImageOrientation(imageDimensions, imageSize);
        const percentWidthWithoutLens = 1 - 1 / this.options.zoomFactor;

        if (this.options.keepAspectRatioOnZoom) {
            position = position.clamp(0, 1);
        }

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
     * sets and returns the lens size
     *
     * The lens marks exactly the image area which is rendered inside the zoom window,
     * therefore it is derived from the zoom window and not from the image itself.
     *
     * @param {Vector2} regionSize
     * @param {Vector2} zoomWindowSize
     * @param {number} zoomFactor
     *
     * @return {Vector2}
     * @private
     */
    _getOverlaySize(regionSize, zoomWindowSize, zoomFactor) {
        const overlaySize = zoomWindowSize.divide(zoomFactor).clamp(0, regionSize);

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
        container.insertAdjacentHTML('beforeend', html);
        this._overlay = container.querySelector(`.${this.options.overlayClass}`);

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
        overlays.forEach(overlay => overlay.remove());

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
        this._zoomImageContainer.insertAdjacentHTML('beforeend', html);
        this._zoomImage = this._zoomImageContainer.querySelector(`.${this.options.zoomImageClass}`);

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
        zoomImages.forEach(zoomImage => zoomImage.remove());

        this.$emitter.publish('removeZoomImage');
    }

    /**
     * sets the image url
     *
     * @param {HTMLElement} image
     * @private
     */
    _getImageUrl(image) {
        this._imageUrl = image.getAttribute(this.options.fullImageDataAttribute);

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

        const images = document.querySelectorAll(this.options.imageSelector);
        images.forEach(image => this._setCursor(image, 'default'));

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
