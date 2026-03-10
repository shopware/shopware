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

        gtag('event', 'add_to_cart', {
            'currency': productData.currency || ProductPageHelper.getCurrency(),
            'value': productData.value,
            'items': [{
                'id': productId,
                'name': formData.get('product-name') || productData.name,
                'quantity': formData.get(`lineItems[${productId}][quantity]`),
                'brand': formData.get('brand-name') || productData.brand,
                ...ProductPageHelper.getCategories(),
            }],
        });
    }
}
