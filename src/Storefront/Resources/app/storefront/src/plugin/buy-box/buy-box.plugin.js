import Plugin from 'src/plugin-system/plugin.class';
import HttpClient from 'src/service/http-client.service';
import ElementLoadingIndicatorUtil from 'src/utility/loading-indicator/element-loading-indicator.util';
import PageLoadingIndicatorUtil from 'src/utility/loading-indicator/page-loading-indicator.util';
import ElementReplaceHelper from 'src/helper/element-replace.helper';
import PseudoModalUtil from 'src/utility/modal-extension/pseudo-modal.util';
import DomAccess from 'src/helper/dom-access.helper';

export default class BuyBoxPlugin extends Plugin {

    static options = {
        elementId: '',
        modalTriggerSelector: 'a[data-toggle="modal"]',
        buyWidgetSelector: '.product-detail-buy',
        urlAttribute: 'data-url',
    };

    /**
     * Plugin initializer
     *
     * @returns {void}
     */
    init() {
        this._httpClient = new HttpClient();
        this._registerEvents();
    }

    /**
     * register all needed events
     *
     * @private
     */
    _registerEvents() {
        document.$emitter.subscribe('updateBuyWidget', this._handleUpdateBuyWidget.bind(this));
    }

    /**
     * Update buy widget after switching product variant
     *
     * @private
     */
    _handleUpdateBuyWidget(event) {
        if (!event.detail || this.options.elementId !== event.detail.elementId) {
            return;
        }

        ElementLoadingIndicatorUtil.create(this.el);

        this._httpClient.get(`${event.detail.url}`, (response) => {
            ElementReplaceHelper.replaceFromMarkup(response, `${this.options.buyWidgetSelector}-${this.options.elementId}`, false);
            ElementLoadingIndicatorUtil.remove(this.el);

            this._initModalTriggerEvent();

            window.PluginManager.initializePlugins();
        });
    }

    /**
     * Initialize modal trigger event handler
     *
     * @private
     */
    _initModalTriggerEvent() {
        this._modalTrigger = DomAccess.querySelector(this.el, this.options.modalTriggerSelector, false);
        this._modalTrigger.addEventListener('click', this._onClickHandleAjaxModal.bind(this));
    }

    /**
     * Event handler which will be fired when the user clicks on the privacy link in the overlay text. The method
     * fetches the information from the URL provided in the `data-url` property.
     *
     * @param {Event} event
     * @returns {void}
     */
    _onClickHandleAjaxModal(event) {
        const trigger = event.currentTarget;
        const url = DomAccess.getAttribute(trigger, this.options.urlAttribute);

        PageLoadingIndicatorUtil.create();
        this._httpClient.get(url, response => {
            PageLoadingIndicatorUtil.remove();
            this._openTaxInfoModal(response);
        });
    }

    /**
     * After the HTTP client fetched the information from the server, we're opening up a modal box and fill it
     * with the response we got.
     *
     * @param {String} response
     * @returns {void}
     */
    _openTaxInfoModal(response) {
        const pseudoModal = new PseudoModalUtil(response);
        pseudoModal.open();
    }
}
