import HttpClient from 'src/service/http-client.service';
import Plugin from 'src/plugin-system/plugin.class';
import LoadingIndicatorUtil from 'src/utility/loading-indicator/loading-indicator.util';
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

        prevUrlAttribute: 'data-prev-url',

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
        this.el.removeEventListener('click', this._onClickHandleAjaxModal.bind(this));
        this.el.addEventListener('click', this._onClickHandleAjaxModal.bind(this));
    }

    /**
     * When clicking/touching the modal trigger the button shall
     * show a loading indicator and an AJAX request needs to be triggered.
     * The response then has to be placed inside the modal which will show up.
     * @param {Event} event
     * @private
     */
    _onClickHandleAjaxModal(event) {
        if (event.cancelable) {
            event.preventDefault();
            event.stopPropagation();
        }

        const pseudoModal = new PseudoModalUtil('', this.options.modalBackdrop);

        window.focusHandler.saveFocusState('ajax-modal');

        this._openModal(pseudoModal);

        pseudoModal.getModal().addEventListener('hidden.bs.modal', () => {
            window.focusHandler.resumeFocusState('ajax-modal');
        });

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
        pseudoModalUtil.open(this._onModalOpen.bind(this, pseudoModalUtil, modalClasses), 0);
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
        pseudoModalUtil.updateContent(response, this._renderBackButton.bind(this, pseudoModalUtil));
        window.PluginManager.initializePlugins();
        modalBodyEl.classList.remove(this.options.centerLoadingIndicatorClass);
    }

    /**
     * Renders a back button into the modal if the `data-prev-url` attribute is set.
     * The `data-prev-url` attribute can be set if an AJAX modal link (`data-ajax-modal="true"`) is inside an already opened AJAX modal.
     * When the new AJAX modal is opened, the back button will then re-open the previous modal.
     *
     * @see https://getbootstrap.com/docs/5.3/components/modal/#toggle-between-modals
     * @param pseudoModalUtil
     * @private
     */
    _renderBackButton(pseudoModalUtil) {
        const prevUrl = DomAccess.getAttribute(this.el, this.options.prevUrlAttribute, false);

        if (!prevUrl) {
            return;
        }

        const buttonTemplate = DomAccess.querySelector(document, '.js-pseudo-modal-back-btn-template', false);
        if (!buttonTemplate) {
            return;
        }

        const backButton = buttonTemplate.content.cloneNode(true);
        if (!backButton.children.length) {
            return;
        }

        backButton.children[0].setAttribute('href', prevUrl);
        backButton.children[0].setAttribute('data-url', prevUrl);
        backButton.children[0].style.marginLeft = '20px';

        const modalBodyEl = DomAccess.querySelector(pseudoModalUtil._modal, `.${PSEUDO_MODAL_TEMPLATE_CONTENT_CLASS}`);
        modalBodyEl.prepend(backButton);
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
        window.PluginManager.initializePlugins();
        this.$emitter.publish('ajaxModalOpen', { modal });
    }
}
