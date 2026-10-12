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

    async _onProductAdded(event) {
        if (!this.active) {
            return;
        }

        const productId = event.detail?.productId;
        if (!productId) {
            return;
        }

        // Try to get product data from product detail/listing page first
        let productData = ProductPageHelper.getProductData(productId);
        let categories = {};

        // Fallback to line item data (cart/checkout/finish pages)
        if (!productData.name) {
            const lineItemData = LineItemHelper.getProductData(productId);
            if (lineItemData) {
                productData = lineItemData;
                categories = lineItemData.categories || {};
            }
        }

        // a product box on a listing, a slider or a Shopping Experience page carries no path
        if (Object.keys(categories).length === 0) {
            categories = await ProductPageHelper.resolveCategories(productId);
        }

        // the shopper can revoke the tracking consent while the categories are requested
        if (!this.active) {
            return;
        }

        this.pushEvent('add_to_wishlist', {
            'currency': productData.currency,
            'value': productData.value,
            'items': [{
                'item_id': productData.id ?? productId,
                'item_name': productData.name,
                'item_brand': productData.brand,
                'item_variant': productData.variant,
                'price': productData.value,
                ...categories,
            }],
        });
    }
}
