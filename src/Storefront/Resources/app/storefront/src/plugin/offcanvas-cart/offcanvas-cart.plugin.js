import Plugin from 'src/plugin-system/plugin.class';
import PluginManager from 'src/plugin-system/plugin.manager';
import DomAccess from 'src/helper/dom-access.helper';
import HttpClient from 'src/service/http-client.service';
import AjaxOffCanvas from 'src/plugin/offcanvas/ajax-offcanvas.plugin';
import DeviceDetection from 'src/helper/device-detection.helper';
import FormSerializeUtil from 'src/utility/form/form-serialize.util';
import Iterator from 'src/helper/iterator.helper';
import OffCanvas from 'src/plugin/offcanvas/offcanvas.plugin';
import ElementLoadingIndicatorUtil from 'src/utility/loading-indicator/element-loading-indicator.util';
import ViewportDetection from 'src/helper/viewport-detection.helper';

export default class OffCanvasCartPlugin extends Plugin {

    static options = {
        removeProductTriggerSelector: '.js-offcanvas-cart-remove-product',
        changeProductQuantityTriggerSelector: '.js-offcanvas-cart-change-quantity',
        addPromotionTriggerSelector: '.js-offcanvas-cart-add-promotion',
        cartItemSelector: '.js-cart-item',
        cartPromotionSelector: '.js-offcanvas-cart-promotion',
        offcanvasPosition: 'right',
        shippingContainerSelector: '.offcanvas-shipping-preference',
        shippingToggleSelector: '.js-toggle-shipping-selection',
        additionalOffcanvasClass: 'cart-offcanvas',
    };

    init() {
        this.client = new HttpClient();
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
        const isFullwidth = ViewportDetection.isXS();
        AjaxOffCanvas.open(url, data, this._onOffCanvasOpened.bind(this, callback), this.options.offcanvasPosition, undefined, undefined, isFullwidth);
        AjaxOffCanvas.setAdditionalClassName(this.options.additionalOffcanvasClass);
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

        this.openOffCanvas(window.router['frontend.cart.offcanvas'], false);
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
     * Register events to handle changing the quantity of a product from the cart
     *
     * @private
     */
    _registerChangeQuantityProductTriggerEvents() {
        const selects = DomAccess.querySelectorAll(document, this.options.changeProductQuantityTriggerSelector, false);
        if (selects) {
            Iterator.iterate(selects, select => select.addEventListener('change', this._onChangeProductQuantity.bind(this)));
        }
    }

    /**
     * Register events to handle adding a promotion to the cart
     *
     * @private
     */
    _registeraddPromotionTriggerEvents() {
        const forms = DomAccess.querySelectorAll(document, this.options.addPromotionTriggerSelector, false);

        if (forms) {
            Iterator.iterate(forms, form => form.addEventListener('submit', this._onAddPromotionToCart.bind(this)));
        }
    }

    _registerUpdateShippingEvents() {
        const { shippingContainerSelector } = this.options;
        const select = document.querySelector(`${ shippingContainerSelector } select`);
        if (select) {
            select.addEventListener('input', this._onChangeShippingMethod.bind(this));
        }
    }

    _registerToggleShippingSelection() {
        const { shippingToggleSelector, shippingContainerSelector } = this.options;
        const toggle = document.querySelector(shippingToggleSelector);

        toggle.addEventListener('click', () => {
            const target = document.querySelector(shippingContainerSelector);
            const hiddenClass = 'offcanvas-shipping-preference--hidden';

            if (target.classList.contains(hiddenClass)) {
                target.classList.remove(hiddenClass);
            } else {
                target.classList.add(hiddenClass);
            }
        });
    }

    /**
     * Register all needed events
     *
     * @private
     */
    _registerEvents() {
        this._registerRemoveProductTriggerEvents();
        this._registerChangeQuantityProductTriggerEvents();
        this._registeraddPromotionTriggerEvents();

        if (this._isShippingAvailable()) {
            this._registerUpdateShippingEvents();
            this._registerToggleShippingSelection();
        }

        this.$emitter.publish('registerEvents');
    }

    /**
     * default callback when the offcanvas has opened
     *
     * @param {function|null} callback
     * @param {string} response
     *
     * @private
     */
    _onOffCanvasOpened(callback, response) {
        if (typeof callback === 'function') callback(response);

        this.$emitter.publish('offCanvasOpened', { response });

        this._fetchCartWidgets();
        this._registerEvents();
    }

    /**
     * Fire the ajax request for the form
     *
     * @param {HTMLElement} form
     * @param {string} selector
     * @param {function} callback
     *
     * @private
     */
    _fireRequest(form, selector, callback) {
        ElementLoadingIndicatorUtil.create(form.closest(selector));

        const cb = callback ? callback.bind(this) : this._onOffCanvasOpened.bind(this, this._updateOffCanvasContent.bind(this));
        const requestUrl = DomAccess.getAttribute(form, 'action');
        const data = FormSerializeUtil.serialize(form);

        this.$emitter.publish('beforeFireRequest');

        this.client.post(requestUrl, data, cb);
    }

    /**
     * Submit the delete form inside the Offcanvas
     *
     * @param {Event} event
     *
     * @private
     */
    _onRemoveProductFromCart(event) {
        event.preventDefault();
        const form = event.target;
        const selector = this.options.cartItemSelector;

        this.$emitter.publish('onRemoveProductFromCart');

        this._fireRequest(form, selector);
    }

    /**
     * Submit the change quantity form inside the Offcanvas
     *
     * @param {Event} event
     *
     * @private
     */
    _onChangeProductQuantity(event) {
        const select = event.target;
        const form = select.closest('form');
        const selector = this.options.cartItemSelector;

        this.$emitter.publish('onChangeProductQuantity');

        this._fireRequest(form, selector);
    }


    /**
     * Submit the add form inside the Offcanvas
     *
     * @param {Event} event
     *
     * @private
     */
    _onAddPromotionToCart(event) {
        event.preventDefault();
        const form = event.target;
        const selector = this.options.cartPromotionSelector;

        this.$emitter.publish('onAddPromotionToCart');

        this._fireRequest(form, selector);
    }

    /**
     * Update all registered cart widgets
     *
     * @private
     */
    _fetchCartWidgets() {
        const CartWidgetPluginInstances = PluginManager.getPluginInstances('CartWidget');
        Iterator.iterate(CartWidgetPluginInstances, instance => instance.fetch());

        this.$emitter.publish('fetchCartWidgets');
    }

    /**
     * Update the OffCanvas content
     *
     * @private
     */
    _updateOffCanvasContent(response) {
        OffCanvas.setContent(response, false, this._registerEvents.bind(this));
        window.PluginManager.initializePlugins();
    }

    _isShippingAvailable() {
        const { shippingContainerSelector } = this.options;
        return !!document.querySelector(shippingContainerSelector);
    }

    _onChangeShippingMethod(event) {
        event.preventDefault();

        this.$emitter.publish('onShippingMethodChange');
        const url = window.router['frontend.cart.offcanvas'];

        const _callback = () => {
            this.client.get(url, response => {
                this._updateOffCanvasContent(response);
                this._registerEvents();
            }, 'text/html');
        };

        this._fireRequest(event.target.form, '.offcanvas-summary', _callback);
    }
}
