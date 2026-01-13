import EventAwareAnalyticsEvent from 'src/plugin/google-analytics/event-aware-analytics-event';
import LineItemHelper from 'src/plugin/google-analytics/line-item.helper';
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

        // Try to get product data from product detail/listing page first
        let productData = ProductPageHelper.getProductData(productId);
        let categories = ProductPageHelper.getCategories();

        // Fallback to line item data (cart/checkout/finish pages)
        if (!productData.name) {
            const lineItemData = LineItemHelper.getProductData(productId);
            if (lineItemData) {
                productData = lineItemData;
                categories = lineItemData.categories || {};
            }
        }

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
