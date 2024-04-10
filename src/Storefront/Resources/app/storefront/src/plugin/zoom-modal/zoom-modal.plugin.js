import Plugin from 'src/plugin-system/plugin.class';
import DeviceDetection from 'src/helper/device-detection.helper';
import Iterator from 'src/helper/iterator.helper';
import DomAccess from 'src/helper/dom-access.helper';
import ImageZoomPlugin from 'src/plugin/image-zoom/image-zoom.plugin';

/**
 * Zoom Modal Plugin
 */
export default class ZoomModalPlugin extends Plugin {

    static options = {

        /**
         * selector for the zoom modal
         */
        modalSelector: '.js-zoom-modal',

        /**
         * selector to trigger the image zoom modal
         */
        triggerSelector: 'img',
        triggerSelectorCanvas: 'canvas',

        /**
         * product id to load the images via ajax
         */
        productIdDataAttribute: 'data-product-id',

        /**
         * selector for the gallery slider inside the modal
         */
        modalGallerySliderSelector: '[data-modal-gallery-slider]',

        /**
         * selector for the initial gallery slider
         */
        parentGallerySliderSelector: '[data-gallery-slider]',

        /**
         * selector for gallery images which use the image zoom
         */
        imageZoomInitSelector: '[data-image-zoom]',

        /**
         * selector for the container of the gallery and the zoom modal
         */
        galleryZoomModalContainerSelector: '.js-gallery-zoom-modal-container',

        /**
         * selector for the images inside the modal which should be loaded on modal show
         */
        imgToLoadSelector: '.js-load-img',

        /**
         * data attribute which contains the src of the image to load
         */
        imgDataSrcAttr: 'data-src',

        /**
         * data attribute which contains the srcset of the image to load
         */
        imgDataSrcSetAttr: 'data-srcset',

        /**
         * selector for the active tiny slider slide
         */
        activeSlideSelector: '.tns-slide-active',
    };

    init() {
        this._triggers = this.el.querySelectorAll(this.options.triggerSelector);
        this._triggersCanvas = this.el.querySelectorAll(this.options.triggerSelectorCanvas);
        this._clickInterrupted = false;
        this._pixelsMoved = 0;
        this._mouseDown = false;
        this._registerEvents();
    }

    /**
     *
     * @private
     */
    _registerEvents() {
        const eventType = (DeviceDetection.isTouchDevice()) ? 'touchend' : 'click';

        // Events for normal elements (images)

        Iterator.iterate(this._triggers, element => {
            element.removeEventListener(eventType, this._onClick.bind(this));
            element.addEventListener(eventType, this._onClick.bind(this));
        });
        Iterator.iterate(this._triggers, element => {
            element.removeEventListener('touchmove', this._onTouchMove.bind(this));
            element.addEventListener('touchmove', this._onTouchMove.bind(this));
        });

        // Events for canvas elements (product box)

        Iterator.iterate(this._triggersCanvas, element => {
            element.removeEventListener('mousedown', this._onMouseDown.bind(this));
            element.addEventListener('mousedown', this._onMouseDown.bind(this));
        });

        Iterator.iterate(this._triggersCanvas, element => {
            element.removeEventListener('mouseup', this._onMouseUp.bind(this));
            element.addEventListener('mouseup', this._onMouseUp.bind(this));
        });

        Iterator.iterate(this._triggersCanvas, element => {
            element.removeEventListener(eventType, this._onClick.bind(this));
            element.addEventListener(eventType, this._onClick.bind(this));
        });

        Iterator.iterate(this._triggersCanvas, element => {
            element.removeEventListener('pointermove', this._onPointerMove.bind(this));
            element.addEventListener('pointermove', this._onPointerMove.bind(this));
        });
    }

    /**
     * @param event
     * @private
     */
    _onClick(event) {
        if (this._clickInterrupted === true || this._pixelsMoved > 10) {
            this._clickInterrupted = false;
            this._pixelsMoved = 0;
            return;
        }

        this._pixelsMoved = 0;

        ZoomModalPlugin._stopEvent(event);
        this._openModal();

        this.$emitter.publish('onClick');
    }

    /**
     * @private
     */
    _onTouchMove() {
        this._clickInterrupted = true;
    }

    /**
     * @private
     */
    _onPointerMove(event) {
        if (event.pointerType == 'mouse') {
            if (this._mouseDown) {
                this._pixelsMoved += 2;
            }
        }
        else {
            this._pixelsMoved += 1;
        }
    }

    /**
     * @private
     */
    _onMouseDown() {
        this._mouseDown = true;
        this._clickInterrupted = false;
    }

    /**
     * @private
     */
    _onMouseUp() {
        this._mouseDown = false;
    }

    /**
     * @private
     */
    _openModal() {
        const galleryZoomModalContainer = this.el.closest(this.options.galleryZoomModalContainerSelector);
        const modal = galleryZoomModalContainer.querySelector(this.options.modalSelector);

        // load images before modal is shown
        if (modal) {
            this._loadImages(modal);
        }

        this.$emitter.publish('onClick', { modal });
    }

    /**
     * load images inside modal before modal is shown
     *
     * @private
     */
    _loadImages(modal) {
        const imagesToLoad = modal.querySelectorAll('img[' + this.options.imgDataSrcAttr + ']' + this.options.imgToLoadSelector);
        const imageCount = imagesToLoad.length;

        // images are already loaded
        if (imageCount === 0) {
            this._showModal(modal);
            return;
        }

        let loadedCount = 0;
        let errorCount = 0;

        Iterator.iterate(imagesToLoad, img => {
            const src = DomAccess.getDataAttribute(img, this.options.imgDataSrcAttr);
            const srcSet = DomAccess.getDataAttribute(img, this.options.imgDataSrcSetAttr, false);

            if (src) {
                img.onload = () => {
                    loadedCount++;

                    // show modal if all images are loaded or error occured
                    if (loadedCount + errorCount === imageCount){
                        this._showModal(modal);
                    }
                };

                img.onerror = () => {
                    errorCount++;

                    // show modal if all images are loaded or error occured
                    if (loadedCount + errorCount === imageCount){
                        this._showModal(modal);
                    }
                };

                img.setAttribute('src', src);
                img.removeAttribute(this.options.imgDataSrcAttr);

                if (srcSet) {
                    img.setAttribute('srcset', srcSet);
                    img.removeAttribute(this.options.imgDataSrcSetAttr);
                }
            }
        });
    }

    /**
     * trigger bootstrap modal show function
     *
     * @private
     */
    _showModal(modal) {
        const bootstrapModal = new bootstrap.Modal(modal);

        if (!this._showModalListener) {
            this._showModalListener = () => {
                this._initSlider(modal);
                this._registerImageZoom();

                this.$emitter.publish('modalShow', { modal });
            };
        }

        modal.removeEventListener('shown.bs.modal', this._showModalListener);
        modal.addEventListener('shown.bs.modal', this._showModalListener);

        bootstrapModal.show();
    }

    /**
     * init the gallery slider or update the position of the slider
     *
     * @private
     */
    _initSlider(modal) {
        const slider = modal.querySelector(this.options.modalGallerySliderSelector);

        if (!slider) {
            return;
        }

        const parentSliderIndex = this._getParentSliderIndex();

        if (this.gallerySliderPlugin && this.gallerySliderPlugin._slider) {
            this.gallerySliderPlugin._slider.goTo(parentSliderIndex - 1);
            return;
        }

        window.PluginManager.initializePlugin('GallerySlider', slider, {
            slider: {
                startIndex: parentSliderIndex,
                touch: false,
            },
            thumbnailSlider: {
                startIndex: parentSliderIndex,
                autoWidth: true,
                responsive: {
                    md: {
                        enabled: true,
                    },
                    lg: {
                        enabled: true,
                    },
                    xl: {
                        enabled: true,
                        axis: 'horizontal',
                    },
                },
            },
        }).then(() => {
            this.gallerySliderPlugin = window.PluginManager.getPluginInstanceFromElement(slider, 'GallerySlider');
            this.$emitter.publish('initSlider');
        });
    }

    /**
     * register ImageZoom Plugin and indexChanged callback to update the image zoom button state
     *
     * @private
     */
    _registerImageZoom() {
        if (this.imageZoomRegistered) {
            return;
        }

        if (this.gallerySliderPlugin) {
            window.PluginManager.register('ImageZoom', ImageZoomPlugin, this.options.activeSlideSelector + ' ' + this.options.imageZoomInitSelector);

            window.PluginManager.initializePlugin('ImageZoom', this.options.activeSlideSelector + ' ' + this.options.imageZoomInitSelector);

            this.gallerySliderPlugin._slider.events.off('indexChanged', this._updateImageZoom.bind(this));
            this.gallerySliderPlugin._slider.events.on('indexChanged',this._updateImageZoom.bind(this));
        } else {
            window.PluginManager.register('ImageZoom', ImageZoomPlugin, this.options.imageZoomInitSelector);

            window.PluginManager.initializePlugin('ImageZoom', this.options.imageZoomInitSelector, {
                activeClassSelector: false,
            });
        }

        this.imageZoomRegistered = true;
    }

    /**
     * updates or initializes the image zoom instance after a slider event
     *
     * @private
     */
    _updateImageZoom() {
        const activeSlideElement = this.gallerySliderPlugin.getActiveSlideElement();
        if (!activeSlideElement) return;

        const activeImageZoomElement = activeSlideElement.querySelector(this.options.imageZoomInitSelector);
        if (!activeImageZoomElement) return;

        const imageZoomPlugin = window.PluginManager.getPluginInstanceFromElement(activeImageZoomElement, 'ImageZoom');
        if (!imageZoomPlugin) {
            window.PluginManager.initializePlugin('ImageZoom', this.options.activeSlideSelector + ' ' + this.options.imageZoomInitSelector);
        } else {
            imageZoomPlugin.update();
        }
    }

    /**
     * returns the current index of the parent slider
     *
     * @return {number}
     * @private
     */
    _getParentSliderIndex() {
        let sliderIndex = 1;

        this._parentSliderElement = this.el.closest(this.options.parentGallerySliderSelector);

        if (this._parentSliderElement) {
            this._parentSliderPlugin = window.PluginManager.getPluginInstanceFromElement(this._parentSliderElement, 'GallerySlider');

            if (this._parentSliderPlugin) {
                sliderIndex = this._parentSliderPlugin.getCurrentSliderIndex();
            }
        }

        return sliderIndex + 1;
    }

    /**
     *
     * @param event
     * @private
     */
    static _stopEvent(event) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }
    }
}
