export default class CartWidget extends window.ShopwareComponent {

    static options = {
        cartInfoRoute: window.router['frontend.checkout.info'],
        emptyValue: '0',
    }

    init() {
        this._label = this.el.querySelector('.sw-header-widget__label');

        // TODO: Listeners for AddToCart / OffCanvasCart close

        this.fetchCartInfo();
    }

    fetchCartInfo() {
        fetch(window.router['frontend.checkout.info'], {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(response => {
                if (response.status >= 500) {
                    this.renderEmptyCart();
                    return;
                }

                if (response.status === 204) {
                    this.renderEmptyCart();
                    return;
                }

                return response.text();
            })
            .then((content) => {
                // TODO: Cart value is extracted from legacy template. Migrate to new JSON route.
                const parser = new DOMParser();
                const dom = parser.parseFromString(content, 'text/html');
                const currentCartValue = dom.querySelector('.header-cart-total')?.textContent;

                if (!currentCartValue) {
                    this.renderEmptyCart();
                    return;
                }

                this._label.innerHTML = currentCartValue;
            });
    }

    renderEmptyCart() {
        this._label.innerHTML = this.options.emptyValue;
    }
}
