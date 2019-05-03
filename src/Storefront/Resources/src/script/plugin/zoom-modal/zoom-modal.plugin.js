import Plugin from 'src/script/helper/plugin/plugin.class';
import PluginManager from 'src/script/helper/plugin/plugin.manager';
import DeviceDetection from 'src/script/helper/device-detection.helper';
import DomAccess from 'src/script/helper/dom-access.helper';
import ImageZoomPlugin from 'src/script/plugin/image-zoom/image-zoom.plugin';
import Iterator from 'src/script/helper/iterator.helper';

const IMAGE_SLIDER_INIT_SELECTOR = '[data-image-slider]';
const IMAGE_ZOOM_INIT_SELECTOR = '[data-image-zoom]';

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
    };

    init() {
        this._triggers = this.el.querySelectorAll(this.options.triggerSelector);

        this._registerEvents();
    }

    /**
     *
     * @private
     */
    _registerEvents() {
        const eventType = (DeviceDetection.isTouchDevice()) ? 'touchstart' : 'click';

        Iterator.iterate(this._triggers, element => {
            element.removeEventListener(eventType, this._onClick.bind(this));
            element.addEventListener(eventType, this._onClick.bind(this));
        });
    }

    /**
     *
     * @param event
     * @private
     */
    _onClick(event) {
        ZoomModalPlugin._stopEvent(event);
        this._openModal();
    }

    /**
     * @private
     */
    _openModal() {
        const modal = DomAccess.querySelector(document, this.options.modalSelector);

        if (modal) {
            // execute all needed scripts for the slider
            $(modal).on('shown.bs.modal', () => {
                this._initSlider(modal);
                setTimeout(() => this._initZoom(modal), 100);
            });

            $(modal).modal('show');
        }
    }

    /**
     *
     * @private
     */
    _initSlider(modal) {
        const slider = modal.querySelector(IMAGE_SLIDER_INIT_SELECTOR);

        const plugin = PluginManager.getPluginInstanceFromElement(slider, 'ImageSlider');

        if (plugin) {
            plugin.destroy();
        }

        if (slider) {
            const parentSliderIndex = this._getParentSliderIndex();

            PluginManager.executePlugin('ImageSlider', slider, {
                slider: {
                    startIndex: parentSliderIndex,
                },
                thumbnailSlider: {
                    startIndex: parentSliderIndex,
                    responsive: {
                        xs: {
                            enabled: true,
                            center: true,
                            axis: 'horizontal',
                        },
                        sm: {
                            enabled: true,
                            center: true,
                            axis: 'horizontal',
                        },
                        md: {
                            enabled: true,
                            center: true,
                            axis: 'horizontal',
                        },
                        lg: {
                            enabled: true,
                            center: true,
                            axis: 'horizontal',
                        },
                        xl: {
                            enabled: true,
                            center: true,
                            axis: 'horizontal',
                        },
                    },
                },
            });
        }
    }

    /**
     * @private
     */
    _initZoom(modal) {
        const elements = modal.querySelectorAll(IMAGE_ZOOM_INIT_SELECTOR);
        Iterator.iterate(elements, el => {
            new ImageZoomPlugin(el, {}, 'ImageZoom');
        });
    }

    /**
     * returns the current index
     * of the parent slider
     *
     * @return {number}
     * @private
     */
    _getParentSliderIndex() {
        let sliderIndex = 1;


        this._parentSliderElement = this._getParentSliderElement();

        if (this._parentSliderElement) {
            this._parentSliderPlugin = PluginManager.getPluginInstanceFromElement(this._parentSliderElement, 'ImageSlider');

            if (this._parentSliderPlugin) {
                sliderIndex = this._parentSliderPlugin.getCurrentSliderIndex();
            }
        }

        return sliderIndex;
    }

    /**
     * returns the parent slider element if present
     *
     * @return {any | Element}
     * @private
     */
    _getParentSliderElement() {
        return this.el.closest(IMAGE_SLIDER_INIT_SELECTOR);
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
