import HttpClient from 'src/service/http-client.service';
import Plugin from 'src/plugin-system/plugin.class';
import PluginManager from 'src/plugin-system/plugin.manager';
import LoadingIndicatorUtil from 'src/utility/loading-indicator/loading-indicator.util';
import DeviceDetection from 'src/helper/device-detection.helper';
import DomAccess from 'src/helper/dom-access.helper';
import PseudoModalUtil from 'src/utility/modal-extension/pseudo-modal.util';

const PSEUDO_MODAL_TEMPLATE_CONTENT_CLASS = 'js-pseudo-modal-template-content-element';

/**
 * This class extends the Bootstrap modal functionality by
 * adding an event listener to modal triggers that contain
 * a special "data-url" attribute which is needed to load
 * the modal content by AJAX
 *
 * Notice: The response template needs to have the markup as defined in the Bootstrap docs
 * https://getbootstrap.com/docs/5.2/components/modal/#live-demo
 *
 * @package storefront
 */
export default class AjaxModalPlugin extends Plugin {

    static options = {
        modalBackdrop: true,

        urlAttribute: 'data-url',

        modalClassAttribute: 'data-modal-class',

        modalClass: null,

        centerLoadingIndicatorClass: 'text-center',
    };

    httpClient = new HttpClient();

    init() {
        this._registerEvents();
    }

    /**
     * Register events
     * @private
     */
    _registerEvents() {
        const eventType = (DeviceDetection.isTouchDevice()) ? 'touchend' : 'click';

        this.el.removeEventListener('click', this._onClickHandleAjaxModal.bind(this));
        this.el.removeEventListener('touchend', this._onClickHandleAjaxModal.bind(this));
        this.el.addEventListener(eventType, this._onClickHandleAjaxModal.bind(this));
    }

    /**
     * When clicking/touching the modal trigger the button shall
     * show a loading indicator and an AJAX request needs to be triggered.
     * The response then has to be placed inside the modal which will show up.
     * @param {Event} event
     * @private
     */
    _onClickHandleAjaxModal(event) {
        event.preventDefault();
        event.stopPropagation();

        const pseudoModal = new PseudoModalUtil('', this.options.modalBackdrop);

        this._openModal(pseudoModal);

        const modalBodyEl = DomAccess.querySelector(pseudoModal._modal, `.${PSEUDO_MODAL_TEMPLATE_CONTENT_CLASS}`);
        modalBodyEl.classList.add(this.options.centerLoadingIndicatorClass);

        this._loadModalContent(pseudoModal, modalBodyEl);
    }

    /**
     * Opens the modal and triggers the initialising process by calling _onOpen
     * This does not contain content yet!
     *
     * @param {PseudoModalUtil} pseudoModalUtil
     * @private
     */
    _openModal(pseudoModalUtil) {
        const modalClasses = [DomAccess.getAttribute(this.el, this.options.modalClassAttribute, false), this.options.modalClass];
        pseudoModalUtil.open(this._onModalOpen.bind(this, pseudoModalUtil, modalClasses));
    }

    /**
     * Sets up a loading indicator into the given modalBodyEl, sends a request to load the HTML content
     * and sets the new content into the modal.
     *
     * @param {PseudoModalUtil} pseudoModalUtil
     * @param {HTMLElement} modalBodyEl
     * @private
     */
    _loadModalContent(pseudoModalUtil, modalBodyEl) {
        const loadingIndicatorUtil = new LoadingIndicatorUtil(modalBodyEl);
        loadingIndicatorUtil.create();

        const url = DomAccess.getAttribute(this.el, this.options.urlAttribute);

        modalBodyEl.classList.add(this.options.centerLoadingIndicatorClass);

        this.httpClient.get(url, (response) => {
            this._processResponse(response, loadingIndicatorUtil, pseudoModalUtil, modalBodyEl);
        });
    }

    /**
     * Processes the response by removing the loading indicator, updating the modal content and removing the "loading"
     * class, which centers the loading indicator.
     *
     * @param {XMLHttpRequest} response
     * @param {LoadingIndicatorUtil} loadingIndicatorUtil
     * @param {PseudoModalUtil} pseudoModalUtil
     * @param {HTMLElement} modalBodyEl
     * @private
     */
    _processResponse(response, loadingIndicatorUtil, pseudoModalUtil, modalBodyEl) {
        loadingIndicatorUtil.remove();
        pseudoModalUtil.updateContent(response);
        PluginManager.initializePlugins();
        modalBodyEl.classList.remove(this.options.centerLoadingIndicatorClass);
    }

    /**
     * Will be executed once the modal is done opening.
     *
     * @param {PseudoModalUtil} pseudoModalUtil
     * @param {Array<String>} classes
     * @private
     */
    _onModalOpen(pseudoModalUtil, classes) {
        const modal = pseudoModalUtil.getModal();
        modal.classList.add(...classes);
        PluginManager.initializePlugins();
        this.$emitter.publish('ajaxModalOpen', { modal });
    }
}
