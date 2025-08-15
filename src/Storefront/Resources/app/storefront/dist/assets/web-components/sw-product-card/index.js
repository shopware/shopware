export default class SwProductCard extends HTMLElement {

    /**
     * @return {string[]}
     */
    static get observedAttributes() {
        return [
            'product-name',
            'product-price',
            'display-buy-button',
            'detail-route',
        ];
    }

    constructor() {
        super();

        this.attachShadow({ mode: 'open' });
        this.shadowRoot.appendChild(SwProductCard.staticTemplate.content.cloneNode(true));

        /**
         * @type {HTMLElement}
         */
        this.productNameEl = this.shadowRoot.getElementById('product-name');

        /**
         * @type {HTMLElement}
         */
        this.productPriceEl = this.shadowRoot.getElementById('product-price');

        /**
         * @type {HTMLElement}
         */
        this.actions = this.shadowRoot.getElementById('actions');
    }

    /**
     * @return {HTMLTemplateElement}
     */
    static staticTemplate = (() => {
        const template = document.createElement('template');
        template.innerHTML = `
            <link href="${window.themeWebComponentsPath}/vendor/bootstrap.min.css" rel="stylesheet">
            <link href="${window.themeWebComponentsPath}/sw-product-card/sw-product-card.css" rel="stylesheet">

            <div class="card h-100 sw-product-card">
                <div class="sw-product-card-header m-2 position-relative overflow-hidden">
                    <slot name="image"></slot>
                </div>
                <div class="sw-product-card-body card-body pb-2">
                    <h2 class="sw-product-card-title card-title">
                        <a id="product-name" href="#" class="sw-product-card-title-link stretched-link">
                            Product name
                        </a>
                    </h2>
                    <!-- Variants -->
                </div>
                <div class="sw-product-card-footer card-footer border-0">

                    <div class="sw-product-card-price mb-3 card-text">
                        <div class="fw-bold" id="product-price">$49,99</div>
                        <a 
                            role="button"
                            class="" 
                            type="button" 
                            data-ajax-modal="true" 
                            data-url="#">
                            incl. VAT plus shipping costs
                        </a>
                        <sw-ajax-modal data-url="/a-funny/route">Somewhere</sw-ajax-modal>
                    </div>

                    <div class="sw-product-card-actions d-grid">
                        <slot name="actions" id="actions">
                            <!-- Action buttons will be added dynamically -->
                        </slot>
                    </div>
                </div>
            </div>
        `;
        return template;
    })();

    /**
     * @return void
     */
    connectedCallback() {
        this.#updateTemplate();
    }

    /**
     * @param name
     * @param oldValue
     * @param newValue
     * @return void
     */
    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue !== newValue) {
            this.#updateTemplate();
        }
    }

    /**
     * Update the static template with the dynamic content.
     *
     * @return void
     */
    #updateTemplate() {
        this.productNameEl.textContent = this.productName;
        this.productNameEl.setAttribute('href', this.detailRoute);
        this.productPriceEl.textContent = this.productPrice;
        this.actions.innerHTML = this.renderActions();
    }

    renderActions() {
        if (this.displayBuyButton) {
            return `
                <sw-button variant="primary">Buy</sw-button>
            `;
        }

        return `
            <sw-button variant="light" href="${this.detailRoute}">Details</sw-button>
        `;
    }

    /**
     * @return {string}
     */
    get productName() {
        return this.getAttribute('product-name');
    }

    /**
     * @param value
     */
    set productName(value) {
        this.setAttribute('product-name', value);
    }

    /**
     * @return {string}
     */
    get productPrice() {
        return this.getAttribute('product-price');
    }

    /**
     * @param value
     */
    set productPrice(value) {
        this.setAttribute('product-price', value);
    }

    /**
     * @return {boolean}
     */
    get displayBuyButton() {
        return (this.getAttribute('display-buy-button') === '1');
    }

    /**
     * @param value
     */
    set displayBuyButton(value) {
        this.setAttribute('display-buy-button', value);
    }

    /**
     * @return {string}
     */
    get detailRoute() {
        return this.getAttribute('detail-route');
    }

    /**
     * @param value
     */
    set detailRoute(value) {
        this.setAttribute('detail-route', value);
    }
}
