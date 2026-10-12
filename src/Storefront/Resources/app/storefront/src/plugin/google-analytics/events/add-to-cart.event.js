import EventAwareAnalyticsEvent from 'src/plugin/google-analytics/event-aware-analytics-event';
import ProductPageHelper from 'src/plugin/google-analytics/product-page.helper';

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
        let productId = null;

        formData.forEach((value, key) => {
            if (key.endsWith('[id]')) {
                productId = value;
            }
        });

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
     * Hands brand and category to the cart with the add, so the checkout events can read them
     * from the line item payload without the cart or the checkout loading anything. This only
     * runs for a sales channel with analytics and after consent, so a shop without tracking
     * sends nothing extra.
     *
     * The buy widget of the product detail page already posts both, from the category of the
     * product. A product box sends its own brand and the category the shopper is browsing. A page
     * without breadcrumb, such as the homepage or the search, sends no category, and neither does
     * a product box on a product detail page, whose breadcrumb belongs to the other product.
     *
     * @param {FormData} formData
     * @param {string} productId
     * @param {HTMLElement|null} formElement
     * @private
     */
    _addPayload(formData, productId, formElement) {
        const productBox = formElement?.closest?.('.product-box');
        if (!productBox) {
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
}
