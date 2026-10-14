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
        const price = productData.value ?? ProductPageHelper.getValue();
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
                ...ProductPageHelper.getCategoriesFor(productId, formElement),
            }],
        });
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
}
