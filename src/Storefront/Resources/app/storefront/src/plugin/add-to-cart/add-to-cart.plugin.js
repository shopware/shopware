import Plugin from 'src/plugin-system/plugin.class';
import PluginManager from 'src/plugin-system/plugin.manager';
import Iterator from 'src/helper/iterator.helper';
import DomAccess from 'src/helper/dom-access.helper';
import FormSerializeUtil from 'src/utility/form/form-serialize.util';

export default class AddToCartPlugin extends Plugin {

    static options = {
        redirectSelector: '[name="redirectTo"]',
        redirectParamSelector: '[data-redirect-parameters="true"]',
        redirectTo: 'frontend.cart.offcanvas',
    };

    init() {
        this._getForm();

        if (!this._form) {
            throw new Error(`No form found for the plugin: ${this.constructor.name}`);
        }

        this._prepareFormRedirect();

        this._registerEvents();
    }

    /**
     * prepares the redirect values
     * fallback redirect back to detail page is deactivated
     * offcanvas redirect is activated
     *
     * @private
     */
    _prepareFormRedirect() {
        try {
            const redirectInput = DomAccess.querySelector(this._form, this.options.redirectSelector);
            const redirectParamInput = DomAccess.querySelector(this._form, this.options.redirectParamSelector);

            redirectInput.value = this.options.redirectTo;
            redirectParamInput.disabled = true;
        } catch (e) {
            // preparations are not needed if fields are not available
        }
    }

    /**
     * tries to get the closest form
     *
     * @returns {HTMLElement|boolean}
     * @private
     */
    _getForm() {
        if (this.el && this.el.nodeName === 'FORM') {
            this._form = this.el;
        } else {
            this._form = this.el.closest('form');
        }
    }

    _registerEvents() {
        this.el.addEventListener('submit', this._formSubmit.bind(this));
    }

    /**
     * On submitting the form the OffCanvas shall open, the product has to be posted
     * against the storefront api and after that the current cart template needs to
     * be fetched and shown inside the OffCanvas
     * @param {Event} event
     * @private
     */
    _formSubmit(event) {
        event.preventDefault();

        const requestUrl = DomAccess.getAttribute(this._form, 'action');
        const formData = FormSerializeUtil.serialize(this._form);

        this.$emitter.publish('beforeFormSubmit', formData);

        this._openOffCanvasCarts(requestUrl, formData);
    }

    /**
     *
     * @param {string} requestUrl
     * @param {{}|FormData} formData
     * @private
     */
    _openOffCanvasCarts(requestUrl, formData) {
        const offCanvasCartInstances = PluginManager.getPluginInstances('OffCanvasCart');
        Iterator.iterate(offCanvasCartInstances, instance => this._openOffCanvasCart(instance, requestUrl, formData));
    }

    /**
     *
     * @param {OffCanvasCartPlugin} instance
     * @param {string} requestUrl
     * @param {{}|FormData} formData
     * @private
     */
    _openOffCanvasCart(instance, requestUrl, formData) {
        instance.openOffCanvas(requestUrl, formData, () => {
            this.$emitter.publish('openOffCanvasCart');
        });
    }
}
