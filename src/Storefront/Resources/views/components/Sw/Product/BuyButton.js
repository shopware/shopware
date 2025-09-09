class BuyButton extends ShopwareComponent {

    static selector = 'form[data-component="BuyButton"]';

    static options = {
        redirectSelector: '[name="redirectTo"]',
        redirectParamSelector: '[data-redirect-parameters="true"]',
        redirectTo: 'frontend.cart.offcanvas',
    };

    init() {
        this.redirectInput = this.el.querySelector(this.options.redirectSelector);
        this.redirectParamInput = this.el.querySelector(this.options.redirectParamSelector);

        if (this.redirectInput) {
            this.redirectInput.value = this.options.redirectTo;
        }

        if (this.redirectParamInput) {
            this.redirectParamInput.disabled = true;
        }

        this.el.addEventListener('submit', this.onFormSubmit.bind(this));
    }

    destroy() {
        this.el.removeEventListener('submit', this.onFormSubmit.bind(this));
    }

    onFormSubmit(event) {
        event.preventDefault();

        let requestUrl = this.el.getAttribute('action');
        let formData = window.Shopware.serializeForm(this.el);

        [ requestUrl, formData ] = window.Shopware.emitInterception('BuyButton:PreSubmit', requestUrl, formData);

        window.Shopware.emit('BuyButton:Submit', requestUrl, formData);

        window.Shopware.callPluginMethod('OffCanvasCart', 'openOffCanvas', requestUrl, formData);
    }
}

window.Shopware.registerComponent('buy-button', BuyButton);