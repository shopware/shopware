/*
 * @sw-package inventory
 */

import Plugin from 'src/plugin-system/plugin.class';
import FormSerializeUtil from 'src/utility/form/form-serialize.util';

/**
 * @package checkout
 */
export default class AddToCartPlugin extends Plugin {

    static options = {
        redirectSelector: '[name="redirectTo"]',
        redirectParamSelector: '[data-redirect-parameters="true"]',
        redirectTo: 'frontend.cart.offcanvas',
        alertTemplateSelector: '.js-add-to-cart-alert-template',
        alertDismissDelay: 3000,
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
            const redirectInput = this._form.querySelector(this.options.redirectSelector);
            const redirectParamInput = this._form.querySelector(this.options.redirectParamSelector);

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
     * be fetched and shown inside the OffCanvas.
     *
     * If the "Open offcanvas after add to cart" setting is disabled, the product is
     * added silently and a success message is shown instead.
     *
     * @param {Event} event
     * @private
     */
    _formSubmit(event) {
        event.preventDefault();

        const requestUrl = this._form.getAttribute('action');
        const formData = FormSerializeUtil.serialize(this._form);

        this.$emitter.publish('beforeFormSubmit', formData);

        if (this._shouldOpenOffcanvas()) {
            this._openOffCanvasCarts(requestUrl, formData);
        } else {
            this._addToCartWithoutOffcanvas(requestUrl, formData);
        }
    }

    /**
     * Check if offcanvas cart should open after adding to cart
     *
     * @returns {boolean}
     * @private
     */
    _shouldOpenOffcanvas() {
        return window.openOffcanvasAfterAddToCart !== '0';
    }

    /**
     * Add product to cart without opening the offcanvas
     * Used when "Open offcanvas after add to cart" setting is disabled
     *
     * @param {string} requestUrl
     * @param {FormData} formData
     * @private
     */
    _addToCartWithoutOffcanvas(requestUrl, formData) {
        fetch(requestUrl, {
            method: 'POST',
            body: formData,
        }).then((response) => {
            if (!response.ok) {
                throw new Error('Add to cart failed');
            }

            // Update the cart widget to show the new item count
            window.PluginManager.getPluginInstances('CartWidget')?.forEach((instance) => {
                instance.fetch();
            });

            // Show success message
            this._showSuccessAlert();

            this.$emitter.publish('addToCartWithoutOffcanvas');
        }).catch(() => {
            // Fall back to offcanvas behaviour on error to show any cart errors
            this._openOffCanvasCarts(requestUrl, formData);
        });
    }

    /**
     * Show a success alert message near the add-to-cart button
     *
     * @private
     */
    _showSuccessAlert() {
        const template = document.querySelector(this.options.alertTemplateSelector);

        if (!template || !this._form) {
            return;
        }

        const existingAlert = this._form.parentElement.querySelector('.add-to-cart-alert');
        if (existingAlert) {
            existingAlert.remove();
        }

        const alert = template.content.firstElementChild.cloneNode(true);
        alert.classList.add('show', 'add-to-cart-alert');

        this._form.insertAdjacentElement('afterend', alert);

        setTimeout(() => {
            alert.addEventListener('transitionend', () => alert.remove(), { once: true });
            alert.classList.remove('show');
        }, this.options.alertDismissDelay);
    }

    /**
     *
     * @param {string} requestUrl
     * @param {{}|FormData} formData
     * @private
     */
    _openOffCanvasCarts(requestUrl, formData) {
        const offCanvasCartInstances = window.PluginManager.getPluginInstances('OffCanvasCart');
        offCanvasCartInstances.forEach((instance) => {
            this._openOffCanvasCart(instance, requestUrl, formData);
        });
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
