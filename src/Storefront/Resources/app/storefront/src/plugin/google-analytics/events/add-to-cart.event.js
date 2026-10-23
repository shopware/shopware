import EventAwareAnalyticsEvent from 'src/plugin/google-analytics/event-aware-analytics-event';
import ProductPageHelper from 'src/plugin/google-analytics/product-page.helper';
import LineItemHelper from 'src/plugin/google-analytics/line-item.helper';

export default class AddToCartEvent extends EventAwareAnalyticsEvent
{
    /* eslint-disable no-unused-vars */
    /**
     * @param {string} controllerName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} actionName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} activeRoute
     * @returns {boolean}
     */
    supports(controllerName, actionName, activeRoute) {
        return true;
    }
    /* eslint-enable no-unused-vars */

    getPluginName() {
        return 'AddToCart';
    }

    getEvents() {
        return {
            'beforeFormSubmit':  this._beforeFormSubmit.bind(this),
        };
    }

    _beforeFormSubmit(event) {
        if (!this.active) {
            return;
        }

        const formData = event.detail;
        const formElement = event.target;

        // the reorder in the account posts only the order, its products are rendered into the form for analytics
        const lineItemElements = formElement.querySelectorAll('[data-analytics-line-item]');
        if (lineItemElements.length > 0) {
            this._awaitReorder(formElement, lineItemElements);
            return;
        }

        let productId = null;
        let isLineItemForm = false;

        formData.forEach((value, key) => {
            if (key.startsWith('lineItems[')) {
                isLineItemForm = true;
            }

            if (key.endsWith('[id]')) {
                productId = value;
            }
        });

        // a form without line items, such as the reorder without the analytics markup, has no product to report
        if (!isLineItemForm) {
            return;
        }

        if (!productId) {
            console.warn('[Google Analytics Plugin] Product ID could not be fetched. Skipping.');
            return;
        }

        // Get product data - uses detail page meta tags or falls back to product card data
        const productData = ProductPageHelper.getProductData(productId, formElement);

        this._addPayload(formData, productId, formElement);

        const quantity = formData.get(`lineItems[${productId}][quantity]`);
        // The meta price is the cheapest tier, so a graduated price is resolved from the quantity.
        // The cart adds the quantity to a line that already holds the product and prices the sum,
        // which the page knows when it rendered the cart, for example after an earlier add.
        const tierQuantity = (Number(quantity) || 1) + this._getQuantityInCart(productId);
        const price = ProductPageHelper.getGraduatedPrice(formElement, tierQuantity)
            ?? productData.value
            ?? ProductPageHelper.getValue();
        // GA4 expects the value of the added quantity, not the unit price
        const value = price === undefined ? undefined : Number(price) * (Number(quantity) || 1);

        this.pushEvent('add_to_cart', {
            'currency': productData.currency || ProductPageHelper.getCurrency(),
            'value': value,
            'items': [{
                'item_id': productData.id ?? productId,
                'item_name': formData.get('product-name') || productData.name,
                'quantity': quantity,
                'price': price,
                'item_brand': formData.get('brand-name') || productData.brand,
                'item_variant': productData.variant,
                ...ProductPageHelper.getCategoriesFor(productId, formElement),
            }],
        });
    }

    /**
     * The quantity of the product in the cart, if the page rendered the cart. Nothing is requested
     * for it, an add on a page without the cart markup prices the added quantity only.
     *
     * @param {string} productId
     * @returns {number}
     * @private
     */
    _getQuantityInCart(productId) {
        // The cart only stacks onto the line with the same id, which the storefront sets to the
        // product id. A line an API client added under its own id is a separate line, so the product
        // id is only compared for a theme's markup that carries no line item id.
        const lineItem = [...document.querySelectorAll('.hidden-line-item')].find(element => {
            return element.hasAttribute('data-line-item-id')
                ? element.getAttribute('data-line-item-id') === productId
                : element.getAttribute('data-id') === productId;
        });

        return parseInt(lineItem?.getAttribute('data-quantity'), 10) || 0;
    }

    /**
     * Hands brand and category to the cart with the add, the same payload the buy widget of the
     * product detail page already posts. The checkout events read them from the line item payload,
     * so neither the cart nor the checkout loads anything for analytics. This only runs for a sales
     * channel with analytics and after consent, so a shop without tracking sends nothing extra.
     *
     * A product element sends its own brand and the category the shopper is browsing. A page
     * without breadcrumb, such as the homepage or the search, sends no category, and neither does
     * a product element on a product detail page, whose breadcrumb belongs to the page product.
     *
     * @param {FormData} formData
     * @param {string} productId
     * @param {HTMLElement|null} formElement
     * @private
     */
    _addPayload(formData, productId, formElement) {
        if (!ProductPageHelper.getProductCard(productId, formElement)) {
            return;
        }

        const prefix = `lineItems[${productId}][payload]`;
        const brand = ProductPageHelper.getProductCardData(productId, formElement).brand;

        if (brand && !formData.has(`${prefix}[manufacturerName]`)) {
            formData.set(`${prefix}[manufacturerName]`, brand);
        }

        const hasCategories = [...formData.keys()].some(key => key.startsWith(`${prefix}[categoryNames]`));
        if (hasCategories || window.activeRoute === 'frontend.detail.page') {
            return;
        }

        ProductPageHelper.getCategoryNames().forEach((name, index) => {
            formData.set(`${prefix}[categoryNames][${index}]`, name);
        });
    }

    /**
     * The reorder adds the products of an order on the server, which prices them with the current
     * prices and the currency of the cart. So `add_to_cart` is reported from the cart the server
     * returns: the off-canvas cart, or, if the shop does not open it, the cart requested once after
     * the add. Product number, brand and categories come from the order, because the reorder creates
     * new line items without them.
     *
     * @param {HTMLElement} formElement
     * @param {NodeList} lineItemElements
     * @private
     */
    _awaitReorder(formElement, lineItemElements) {
        const ordered = new Map([...lineItemElements].map(element => [element.getAttribute('data-id'), element]));
        let reported = false;

        const report = (html, requireSuccess) => {
            if (reported || !this.active) {
                return;
            }

            reported = true;
            this._pushReorder(ordered, html, requireSuccess);
        };

        window.PluginManager.getPluginInstances('OffCanvasCart')?.[0]?.$emitter.subscribe(
            'offCanvasOpened',
            event => report(event.detail?.response, true),
            { once: true },
        );

        formElement.addEventListener('addToCartWithoutOffcanvas', () => {
            const url = window.router?.['frontend.cart.offcanvas'];
            if (!url) {
                return;
            }

            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(response => (response.ok ? response.text() : ''))
                .then(html => report(html, false))
                .catch(() => {});
        }, { once: true });
    }

    /**
     * @param {Map<string, HTMLElement>} ordered the products of the order by product ID
     * @param {string} html the cart the server returned after the reorder
     * @param {boolean} requireSuccess the off-canvas cart shows a success alert only if every product was added
     * @private
     */
    _pushReorder(ordered, html, requireSuccess) {
        const cart = new DOMParser().parseFromString(html || '', 'text/html');
        const information = cart.querySelector('.hidden-line-items-information');

        if (!information || (requireSuccess && !cart.querySelector('.alert-success'))) {
            return;
        }

        const items = [...information.querySelectorAll('.hidden-line-item')]
            .filter(element => ordered.has(element.getAttribute('data-id')))
            .map(element => {
                const orderItem = ordered.get(element.getAttribute('data-id'));
                const categories = LineItemHelper.getCategoriesFromElement(orderItem);

                return {
                    'item_id': orderItem.getAttribute('data-sku') || element.getAttribute('data-sku'),
                    'item_name': element.getAttribute('data-name'),
                    // the cart can hold more of the product than the reorder added
                    'quantity': orderItem.getAttribute('data-quantity'),
                    'price': element.getAttribute('data-price'),
                    'item_brand': orderItem.getAttribute('data-brand') || element.getAttribute('data-brand'),
                    'item_variant': orderItem.getAttribute('data-variant') || element.getAttribute('data-variant'),
                    ...(Object.keys(categories).length > 0 ? categories : LineItemHelper.getCategoriesFromElement(element)),
                };
            });

        if (items.length === 0) {
            return;
        }

        const value = items.reduce((sum, item) => sum + (Number(item.price) || 0) * (Number(item.quantity) || 1), 0);

        this.pushEvent('add_to_cart', {
            'currency': information.getAttribute('data-currency'),
            'value': value,
            'items': items,
        });
    }
}
