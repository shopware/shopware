import Plugin from 'src/plugin-system/plugin.class';
import PluginManager from 'src/plugin-system/plugin.manager';
import DeviceDetection from 'src/helper/device-detection.helper';
import Iterator from 'src/helper/iterator.helper';

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
    };

    init() {
        this._triggers = this.el.querySelectorAll(this.options.triggerSelector);
        this._clickInterrupted = false;
        this._registerEvents();
    }

    /**
     *
     * @private
     */
    _registerEvents() {
        const eventType = (DeviceDetection.isTouchDevice()) ? 'touchend' : 'click';

        Iterator.iterate(this._triggers, element => {
            element.removeEventListener(eventType, this._onClick.bind(this));
            element.addEventListener(eventType, this._onClick.bind(this));
        });

        Iterator.iterate(this._triggers, element => {
            element.removeEventListener('touchmove', this._onTouchMove.bind(this));
            element.addEventListener('touchmove', this._onTouchMove.bind(this));
        });
    }

    /**
     * @param event
     * @private
     */
    _onClick(event) {
        if (this._clickInterrupted === true) {
            this._clickInterrupted = false;
            return;
        }

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
    _openModal() {
        const galleryZoomModalContainer = this.el.closest(this.options.galleryZoomModalContainerSelector);
        const modal = galleryZoomModalContainer.querySelector(this.options.modalSelector);

        if (modal) {
            // execute all needed scripts for the slider
            const $modal = $(modal);

            $modal.off('shown.bs.modal');
            $modal.on('shown.bs.modal', () => {
                this._initSlider(modal);

                this.$emitter.publish('modalShow', { modal });
            });

            $modal.modal('show');
        }

        this.$emitter.publish('onClick', { modal });
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

        PluginManager.initializePlugin('GallerySlider', slider, {
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
        });

        this.gallerySliderPlugin = PluginManager.getPluginInstanceFromElement(slider, 'GallerySlider');

        this._registerImageZoom();

        this.$emitter.publish('initSlider');
    }

    /**
     * register indexChanged callback to update the image zoom button state
     *
     * @private
     */
    _registerImageZoom() {
        this._updateImageZoom();
        this.gallerySliderPlugin._slider.events.on('indexChanged', this._updateImageZoom.bind(this));
    }

    /**
     * updates the image zoom instance after a slider event
     *
     * @private
     */
    _updateImageZoom() {
        const activeSlideElement = this.gallerySliderPlugin.getActiveSlideElement();
        if (!activeSlideElement) return;
        const activeImageZoomElement = activeSlideElement.querySelector(this.options.imageZoomInitSelector);
        if (!activeImageZoomElement) return;
        const imageZoomPlugin = PluginManager.getPluginInstanceFromElement(activeImageZoomElement, 'ImageZoom');
        if (!imageZoomPlugin) return;

        imageZoomPlugin.update();
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
            this._parentSliderPlugin = PluginManager.getPluginInstanceFromElement(this._parentSliderElement, 'GallerySlider');

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
