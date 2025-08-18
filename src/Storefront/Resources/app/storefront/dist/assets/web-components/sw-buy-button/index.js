export default class SwBuyButton extends HTMLElement {
    constructor() {
        super();

        this.id = this.getAttribute('product-id');
        this.redirectTo = this.getAttribute('redirect-to');
        this.productName = this.getAttribute('product-name');

        this.attachShadow({ mode: 'open' });
        this.shadowRoot.innerHTML = this.template();
    }

    template() {
        return `
            <form action="${this.redirectTo}" method="post" class="buy-widget d-grid" data-add-to-cart="true">
                <input type="hidden" name="redirectTo" value="frontend.cart.offcanvas">
                <input type="hidden" name="redirectParameters" value="{&quot;productId&quot;:&quot;${this.id}&quot;}" data-redirect-parameters="true" disabled="">
                <input type="hidden" name="lineItems[${this.id}][id]" value="${this.id}">
                <input type="hidden" name="lineItems[${this.id}][referencedId]" value="${this.id}">
                <input type="hidden" name="lineItems[${this.id}][type]" value="product">
                <input type="hidden" name="lineItems[${this.id}][stackable]" value="1">
                <input type="hidden" name="lineItems[${this.id}][removable]" value="1">
                <input type="hidden" name="lineItems[${this.id}][quantity]" value="1">
                <input type="hidden" name="lineItems[${this.id}][product-name]" value="Aerodynamic Bronze Hot Poddy">
                <input type="hidden" name="foo" value="bar">

                <sw-button variant="primary">
                    <slot>Add to shopping cart</slot>
                </sw-button>
            </form>
        `;
    }
}
