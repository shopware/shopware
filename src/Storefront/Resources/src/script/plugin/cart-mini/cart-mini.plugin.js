import Plugin from 'src/script/helper/plugin/plugin.class';
import PluginManager from 'src/script/helper/plugin/plugin.manager';
import DomAccess from 'src/script/helper/dom-access.helper';
import HttpClient from 'src/script/service/http-client.service';
import OffCanvas from 'src/script/plugin/offcanvas/offcanvas.plugin';
import AjaxOffCanvas from 'src/script/plugin/offcanvas/ajax-offcanvas.plugin';
import LoadingIndicator from 'src/script/utility/loading-indicator/loading-indicator.util';
import DeviceDetection from 'src/script/helper/device-detection.helper';


export default class CartMiniPlugin extends Plugin {

    static options = {
        removeProductTriggerSelector: '.js-cart-mini-remove-product',
        formSelector: 'form.js-add-to-cart',
        offcanvasPosition: 'right',
    };

    init() {
        this.client = new HttpClient(window.accessKey, window.contextToken);
        this._registerOpenTriggerEvents();
        this._registerFormEvents();
    }

    /**
     * Register events to handle opening the Cart Mini OffCanvas
     * by clicking a defined trigger selector
     * @private
     */
    _registerOpenTriggerEvents() {
        const event = (DeviceDetection.isTouchDevice()) ? 'touchstart' : 'click';

        this.el.addEventListener(event, this._onOpenCartMini.bind(this));
    }

    /**
     * On clicking the trigger item the OffCanvas shall open and the current
     * cart template may be fetched and shown inside the OffCanvas
     * @param {Event} e
     * @private
     */
    _onOpenCartMini(e) {
        e.preventDefault();

        AjaxOffCanvas.open(window.router['frontend.cart.detail'], false, this._registerRemoveProductTriggerEvents.bind(this), this.options.offcanvasPosition);
    }

    /**
     * Register events to handle form submission for adding any products to the cart
     * @private
     */
    _registerFormEvents() {
        const forms = document.querySelectorAll(this.options.formSelector);

        forms.forEach(form => {
            form.addEventListener('submit', this._onFormSubmit.bind(this));
        });
    }

    /**
     * On submitting the form the OffCanvas shall open, the product has to be posted
     * against the storefront api and after that the current cart template needs to
     * be fetched and shown inside the OffCanvas
     * @param {Event} e
     * @private
     */
    _onFormSubmit(e) {
        e.preventDefault();

        const form = e.target;
        const requestUrl = DomAccess.getAttribute(form, 'action').toLowerCase();
        const formData = this._convertFormDataToJSON(new FormData(form));

        OffCanvas.open(LoadingIndicator.getTemplate(), () => {
            this.client.post(requestUrl, formData,this._updateCart.bind(this));
        }, this.options.offcanvasPosition);
    }

    /**
     * Convert the FormData object to JSON
     * @param {FormData} formData
     * @returns {string}
     * @private
     */
    _convertFormDataToJSON(formData) {
        const object = {};
        formData.forEach((value, key) => {
            object[key] = value;
        });
        return JSON.stringify(object);
    }

    /**
     * Register events to handle removing a product from the cart
     * @private
     */
    _registerRemoveProductTriggerEvents() {
        const forms = document.querySelectorAll(this.options.removeProductTriggerSelector);

        forms.forEach(form => {
            form.addEventListener('submit', this._onRemoveProductFromCart.bind(this));
        });
    }

    /**
     * On submitting the delete product form inside the OffCanvas a DELETE request
     * against the storefront api has to take place to remove the product. After that
     * the current cart template needs to be fetched and shown inside the OffCanvas
     * @param {Event} e
     * @private
     */
    _onRemoveProductFromCart(e) {
        e.preventDefault();

        const form = e.target;
        const requestUrl = DomAccess.getAttribute(form, 'action');

        // Show loading indicator immediately after submitting
        form.innerHTML = LoadingIndicator.getTemplate();

        this.client.delete(requestUrl.toLowerCase(), this._updateCart.bind(this));
    }

    /**
     * updates the mini cart  and its widget
     *
     * @private
     */
    _updateCart() {
        // Update the CartWidget in the header
        this._fetchCartWidgets();
        // Fetch the current cart template and replace the OffCanvas content
        this._fetchCartMini();
    }

    /**
     * updates all registered cart widgets
     *
     * @private
     */
    _fetchCartWidgets() {
        const CartWidgetPluginInstances = PluginManager.getPluginInstances('CartWidget');
        CartWidgetPluginInstances.forEach(instance => {
            instance.fetch();
        });
    }

    /**
     * Fetch the current cart template and replace the OffCanvas content
     * @private
     */
    _fetchCartMini() {
        AjaxOffCanvas.setContent(window.router['frontend.cart.detail'], false, this._registerRemoveProductTriggerEvents.bind(this));
    }

}
