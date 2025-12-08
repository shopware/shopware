import EventAwareAnalyticsEvent from 'src/plugin/google-analytics/event-aware-analytics-event';
import ProductPageHelper from 'src/plugin/google-analytics/product-page.helper';

export default class AddToWishlistEvent extends EventAwareAnalyticsEvent
{
    supports() {
        return true;
    }

    getPluginName() {
        return 'WishlistStorage';
    }

    getEvents() {
        return {
            'Wishlist/onProductAdded': this._onProductAdded.bind(this),
        };
    }

    _onProductAdded(event) {
        if (!this.active) {
            return;
        }

        const productId = event.detail?.productId;
        if (!productId) {
            return;
        }

        const productData = ProductPageHelper.getProductData(productId);
        const categories = ProductPageHelper.getCategories();

        gtag('event', 'add_to_wishlist', {
            'currency': productData.currency,
            'value': productData.value,
            'items': [{
                'id': productId,
                'name': productData.name,
                'brand': productData.brand,
                ...categories,
            }],
        });
    }
}
