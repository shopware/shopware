import Plugin from 'src/script/helper/plugin/plugin.class';
import PluginManager from 'src/script/helper/plugin/plugin.manager';
import DomAccess from 'src/script/helper/dom-access.helper';
import HttpClient from 'src/script/service/http-client.service';
import AjaxOffCanvas from 'src/script/plugin/offcanvas/ajax-offcanvas.plugin';
import DeviceDetection from 'src/script/helper/device-detection.helper';
import FormSerializeUtil from 'src/script/utility/form/form-serialize.util';
import Iterator from 'src/script/helper/iterator.helper';
import OffCanvas from 'src/script/plugin/offcanvas/offcanvas.plugin';
import ElementLoadingIndicatorUtil from 'src/script/utility/loading-indicator/element-loading-indicator.util';

export default class OffCanvasCartPlugin extends Plugin {

    static options = {
        removeProductTriggerSelector: '.js-offcanvas-cart-remove-product',
        formSelector: 'form.js-add-to-cart',
        offcanvasPosition: 'right',
    };

    init() {
        this.client = new HttpClient(window.accessKey, window.contextToken);
        this._registerOpenTriggerEvents();
        this._registerFormEvents();
    }

    /**
     * Register events to handle opening the Cart OffCanvas
     * by clicking a defined trigger selector
     * @private
     */
    _registerOpenTriggerEvents() {
        const event = (DeviceDetection.isTouchDevice()) ? 'touchstart' : 'click';

        this.el.addEventListener(event, this._onOpenOffCanvasCart.bind(this));
    }

    /**
     * On clicking the trigger item the OffCanvas shall open and the current
     * cart template may be fetched and shown inside the OffCanvas
     * @param {Event} e
     * @private
     */
    _onOpenOffCanvasCart(e) {
        e.preventDefault();

        AjaxOffCanvas.open(window.router['frontend.cart.detail'], false, this._registerRemoveProductTriggerEvents.bind(this), this.options.offcanvasPosition);
    }

    /**
     * Register events to handle form submission for adding any products to the cart
     * @private
     */
    _registerFormEvents() {
        const forms = document.querySelectorAll(this.options.formSelector);
        Iterator.iterate(forms, form => form.addEventListener('submit', this._onFormSubmit.bind(this)));
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
        const formData = FormSerializeUtil.serialize(form);

        AjaxOffCanvas.open(requestUrl, formData,() => {
            this._registerRemoveProductTriggerEvents();
            this._fetchCartWidgets();
        }, this.options.offcanvasPosition);
    }

    /**
     * Register events to handle removing a product from the cart
     * @private
     */
    _registerRemoveProductTriggerEvents() {
        const forms = DomAccess.querySelectorAll(document, this.options.removeProductTriggerSelector, false);
        if (forms) {
            Iterator.iterate(forms, form => form.addEventListener('submit', this._onRemoveProductFromCart.bind(this)));
        }
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

        const data = FormSerializeUtil.serialize(form);
        // Show loading indicator immediately after submitting
        ElementLoadingIndicatorUtil.create(form.closest('.js-cart-item'));

        this.client.post(requestUrl.toLowerCase(), data, (response) => {
            this._fetchCartWidgets();
            this._updateOffCanvasContent(response);
        });
    }


    /**
     * updates all registered cart widgets
     *
     * @private
     */
    _fetchCartWidgets() {
        const CartWidgetPluginInstances = PluginManager.getPluginInstances('CartWidget');
        Iterator.iterate(CartWidgetPluginInstances, instance => instance.fetch());
    }

    /**x
     * update the OffCanvas content
     *
     * @private
     */
    _updateOffCanvasContent(response) {
        OffCanvas.setContent(response, false, this._registerRemoveProductTriggerEvents.bind(this));
    }

}
