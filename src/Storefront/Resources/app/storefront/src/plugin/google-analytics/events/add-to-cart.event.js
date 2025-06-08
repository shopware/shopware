import EventAwareAnalyticsEvent from 'src/plugin/google-analytics/event-aware-analytics-event';

export default class AddToCartEvent extends EventAwareAnalyticsEvent
{
    supports() {
        return true;
    }

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

        gtag('event', 'add_to_cart', {
            'items': [{
                'id': productId,
                'name': formData.get('product-name'),
                'quantity': formData.get('lineItems[' + productId + '][quantity]'),
            }],
        });
    }
}
