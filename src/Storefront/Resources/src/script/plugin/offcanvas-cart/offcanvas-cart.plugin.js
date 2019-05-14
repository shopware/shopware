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
        offcanvasPosition: 'right',
    };

    init() {
        this.client = new HttpClient(window.accessKey, window.contextToken);
        this._registerOpenTriggerEvents();
    }

    /**
     * public method to open the offCanvas
     *
     * @param {string} url
     * @param {{}|FormData} data
     * @param {function|null} callback
     */
    openOffCanvas(url, data, callback) {
        AjaxOffCanvas.open(url, data, this._onOffCanvasOpened.bind(this, callback), this.options.offcanvasPosition);
    }

    /**
     * Register events to handle opening the Cart OffCanvas
     * by clicking a defined trigger selector
     *
     * @private
     */
    _registerOpenTriggerEvents() {
        const event = (DeviceDetection.isTouchDevice()) ? 'touchstart' : 'click';

        this.el.addEventListener(event, this._onOpenOffCanvasCart.bind(this));
    }

    /**
     * On clicking the trigger item the OffCanvas shall open and the current
     * cart template may be fetched and shown inside the OffCanvas
     *
     * @param {Event} event
     * @private
     */
    _onOpenOffCanvasCart(event) {
        event.preventDefault();

        this.openOffCanvas(window.router['frontend.cart.detail'], false);
    }

    /**
     * Register events to handle removing a product from the cart
     *
     * @private
     */
    _registerRemoveProductTriggerEvents() {
        const forms = DomAccess.querySelectorAll(document, this.options.removeProductTriggerSelector, false);
        if (forms) {
            Iterator.iterate(forms, form => form.addEventListener('submit', this._onRemoveProductFromCart.bind(this)));
        }
    }

    /**
     * default callback when the off canvas has opened
     *
     * @param {function|null} callback
     * @param {string} response
     *
     * @private
     */
    _onOffCanvasOpened(callback, response) {
        if (typeof callback === 'function') callback(response);
        this._fetchCartWidgets();
        this._registerRemoveProductTriggerEvents();
    }

    /**
     * On submitting the delete product form inside the OffCanvas a DELETE request
     * against the storefront api has to take place to remove the product. After that
     * the current cart template needs to be fetched and shown inside the OffCanvas
     *
     * @param {Event} event
     *
     * @private
     */
    _onRemoveProductFromCart(event) {
        event.preventDefault();
        const form = event.target;

        ElementLoadingIndicatorUtil.create(form.closest('.js-cart-item'));

        const requestUrl = DomAccess.getAttribute(form, 'action');
        const data = FormSerializeUtil.serialize(form);

        this.client.post(requestUrl.toLowerCase(), data, this._onOffCanvasOpened.bind(this, this._updateOffCanvasContent.bind(this)));
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
