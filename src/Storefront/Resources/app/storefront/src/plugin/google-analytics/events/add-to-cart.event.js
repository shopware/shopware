import EventAwareAnalyticsEvent from 'src/plugin/google-analytics/event-aware-analytics-event';

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

        const breadcrumbNodes = document.querySelectorAll('nav[aria-label="breadcrumb"] .breadcrumb-title');
        const categories = {};
        breadcrumbNodes.forEach((node, index) => {
            if (index < 5) {
                // GA4 uses item_category, item_category2, item_category3, etc.
                const key = index === 0 ? 'item_category' : `item_category${index + 1}`;
                categories[key] = node.textContent.trim();
            }
        });

        gtag('event', 'add_to_cart', {
            'items': [{
                'id': productId,
                'name': formData.get('product-name'),
                'quantity': formData.get(`lineItems[${productId}][quantity]`),
                'brand': formData.get('brand-name'),
                ...categories,
            }],
            'currency': document.querySelector('meta[property="product:price:currency"]')?.content,
            'value': document.querySelector('meta[property="product:price:amount"]')?.content,
        });
    }
}
