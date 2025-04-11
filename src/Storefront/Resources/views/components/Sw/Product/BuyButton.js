({ Shopware, ShopwareComponent } = window);

export default class BuyButton extends ShopwareComponent {

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

    onFormSubmit(event) {
        event.preventDefault();

        let requestUrl = this.el.getAttribute('action');
        let formData = Shopware.serializeForm(this.el);

        ({ requestUrl, formData } = Shopware.emitInterception(`BuyButton:PreSubmit`, { requestUrl, formData }));

        Shopware.emit(`BuyButton:Submit`, requestUrl, formData);

        window.PluginManager.callPluginMethod('OffCanvasCart', 'openOffCanvas', requestUrl, formData);
    }

    destroy() {
        this.el.removeEventListener('submit', this.onFormSubmit.bind(this));
    }
}
