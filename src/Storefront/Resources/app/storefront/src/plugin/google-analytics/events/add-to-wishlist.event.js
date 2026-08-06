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
        let categories = productData.categories ?? {};

        // Fallback to line item data (cart/checkout/finish pages)
        if (!productData.name) {
            const lineItemData = LineItemHelper.getProductData(productId);
            if (lineItemData) {
                productData = lineItemData;
                categories = lineItemData.categories || {};
            }
        }

        // Last resort: the breadcrumb describes the page rather than the product, so it is only
        // correct on the product detail page
        if (Object.keys(categories).length === 0) {
            categories = ProductPageHelper.getCategories();
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
