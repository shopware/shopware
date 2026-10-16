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
            this._pushLineItems(formElement, lineItemElements);
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

        const parameters = {
            'currency': productData.currency || ProductPageHelper.getCurrency(),
            'value': value,
            'items': [{
                'item_id': productData.id ?? productId,
                'item_name': formData.get('product-name') || productData.name,
                'quantity': quantity,
                'price': price,
                'item_brand': formData.get('brand-name') || productData.brand,
                'item_variant': productData.variant,
            }],
        };

        const categories = ProductPageHelper.getCategoriesFor(productId, formElement);
        if (Object.keys(categories).length > 0) {
            this._push(parameters, categories);
            return;
        }

        // the page does not describe the categories of the product, so they are requested
        return ProductPageHelper.resolveCategories(productId, formElement).then(resolved => {
            // the shopper can revoke the tracking consent while the categories are requested
            if (this.active) {
                this._push(parameters, resolved);
            }
        });
    }

    /**
     * @param {Object} parameters
     * @param {Object} categories
     * @private
     */
    _push(parameters, categories) {
        parameters.items[0] = { ...parameters.items[0], ...categories };

        this.pushEvent('add_to_cart', parameters);
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
     * A product element sends its own brand, and a product of a category listing the category the
     * shopper is browsing. Any other product element sends no category, such as one on the
     * homepage, in the search, in a slider or on a product detail page. Its categories are requested
     * with the add and kept for the cart, see `ProductPageHelper.resolveCategories()`.
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
        if (hasCategories || !ProductPageHelper.isDescribedByBreadcrumb(productId, formElement)) {
            return;
        }

        ProductPageHelper.getCategoryNames().forEach((name, index) => {
            formData.set(`${prefix}[categoryNames][${index}]`, name);
        });
    }

    /**
     * Reports all line items of a form that adds several of them, such as the reorder of an order.
     * Their data comes from the order, so prices and currency are those of the original order.
     *
     * @param {FormData} formData
     * @param {HTMLElement} formElement
     * @param {NodeList} lineItemElements
     * @private
     */
    _pushLineItems(formElement, lineItemElements) {
        const items = [...lineItemElements].map(element => {
            const id = element.getAttribute('data-id');

            return {
                'item_id': element.getAttribute('data-sku') || id,
                'item_name': element.getAttribute('data-name'),
                'quantity': element.getAttribute('data-quantity'),
                'price': element.getAttribute('data-price'),
                'item_brand': element.getAttribute('data-brand'),
                'item_variant': element.getAttribute('data-variant'),
                ...LineItemHelper.getCategoriesFromElement(element),
            };
        });

        const value = items.reduce((sum, item) => sum + (Number(item.price) || 0) * (Number(item.quantity) || 1), 0);

        this.pushEvent('add_to_cart', {
            'currency': formElement.getAttribute('data-analytics-currency') || ProductPageHelper.getCurrency(),
            'value': value,
            'items': items,
        });
    }
}
