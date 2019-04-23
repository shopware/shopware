import Plugin from 'src/script/helper/plugin/plugin.class';
import PluginManager from 'src/script/helper/plugin/plugin.manager';
import DeviceDetection from 'src/script/helper/device-detection.helper';
import HttpClient from 'src/script/service/http-client.service';
import DomAccess from 'src/script/helper/dom-access.helper';
import PageLoadingIndicator from 'src/script/utility/loading-indicator/page-loading-indicator.util';
import ImageZoomPlugin from 'src/script/plugin/image-zoom/image-zoom.plugin';

const URL_TEMPLATE = (id) => `${window.router['widgets.detail.images']}?productId=${id}`;
const MODAL_TRIGGER_SELECTOR = 'img';
const PRODUCT_ID_DATA_ATTRIBUTE = 'data-product-id';
const MODAL_WRAPPER_CLASS = 'ajax-modal-wrapper';
const IMAGE_SLIDER_INIT_SELECTOR = '[data-image-slider]';
const IMAGE_ZOOM_INIT_SELECTOR = '[data-image-zoom]';

/**
 * Zoom Modal Plugin
 */
export default class ZoomModalPlugin extends Plugin {

    init() {
        this._triggers = this.el.querySelectorAll(MODAL_TRIGGER_SELECTOR);
        this._client = new HttpClient(window.accessKey, window.contextToken);

        this._registerEvents();
    }

    /**
     *
     * @private
     */
    _registerEvents() {
        const eventType = (DeviceDetection.isTouchDevice()) ? 'touchstart' : 'click';

        this._triggers.forEach(element => element.addEventListener(eventType, this._onClick.bind(this)));
    }

    /**
     *
     * @param event
     * @private
     */
    _onClick(event) {
        ZoomModalPlugin._stopEvent(event);
        const productId = DomAccess.getDataAttribute(this.el, PRODUCT_ID_DATA_ATTRIBUTE);
        const url = URL_TEMPLATE(productId);
        PageLoadingIndicator.open();

        this._fetchContent(url, this._openModal.bind(this));
    }

    /**
     *
     * @param content
     * @private
     */
    _openModal(content) {
        // append the temporarily created ajax modal content to the end of the DOM
        const pseudoModal = ZoomModalPlugin._createPseudoModal(content);
        document.body.insertAdjacentElement('beforeend', pseudoModal);
        const modal = DomAccess.querySelector(pseudoModal, '.modal');

        // execute all needed scripts for the slider
        $(modal).on('shown.bs.modal', () => {
            this._initSlider(modal);
            this._initZoom(modal);
        });

        // remove ajax modal wrapper
        $(modal).on('hidden.bs.modal', pseudoModal.remove);

        // close the loading indicator and show the modal
        PageLoadingIndicator.close();
        $(modal).modal('show');
    }

    /**
     *
     * @private
     */
    _initSlider(modal) {
        const slider = modal.querySelector(IMAGE_SLIDER_INIT_SELECTOR);

        if (slider) {
            const parentSliderIndex = this._getParentSliderIndex();

            PluginManager.executePlugin('ImageSlider', slider, {
                startIndex: parentSliderIndex,
            });
        }
    }

    /**
     * @private
     */
    _initZoom(modal) {
        const elements = modal.querySelectorAll(IMAGE_ZOOM_INIT_SELECTOR);
        elements.forEach(el => {
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
            this._parentSliderPlugin = PluginManager.getPluginInstance(this._parentSliderElement, 'ImageSlider');

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
     * @param content
     * @return {Element}
     * @private
     */
    static _createPseudoModal(content) {
        let element = document.querySelector(`.${MODAL_WRAPPER_CLASS}`);

        if (!element) {
            element = document.createElement('div');
            element.classList.add(MODAL_WRAPPER_CLASS);
        }

        element.innerHTML = content;

        return element;
    }

    /**
     *
     * @param url
     * @param cb
     * @private
     */
    _fetchContent(url, cb) {
        this._client.get(url, (response) => {
            if (response && typeof cb === 'function') {
                cb(response);
            }
        });
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
