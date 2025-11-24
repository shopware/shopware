import EventAwareAnalyticsEvent from 'src/plugin/google-analytics/event-aware-analytics-event';

export default class AddToWishlistEvent extends EventAwareAnalyticsEvent
{
    supports() {
        return true;
    }

    getPluginName() {
        return 'AddToWishlist';
    }

    getEvents() {
        return {
            'beforeAddToWishlist': this._beforeAddToWishlist.bind(this),
        };
    }

    _beforeAddToWishlist(event) {
        if (!this.active) {
            return;
        }

        const wishlistButton = event.detail;
        if (!wishlistButton) {
            return;
        }

        const productWishlist = wishlistButton.closest('.product-wishlist');
        if (!productWishlist) {
            return;
        }

        const breadcrumbNodes = document.querySelectorAll('nav[aria-label="breadcrumb"] .breadcrumb-title');
        const categories = {};
        breadcrumbNodes.forEach((node, index) => {
            const key = `item_category${index === 0 ? '' : index + 1}`;
            categories[key] = node.textContent.trim();
        });

        const item = {
            'id': wishlistButton.dataset.addToWishlistOptions.productId,
            'name': document.querySelector('.product-detail-name').textContent.trim(),
            'brand': document.querySelector('div[itemprop="brand"] meta[itemprop="name"]')?.content,
            ...categories,
        };

        gtag('event', 'add_to_wishlist', {
            'currency': document.querySelector('meta[property="product:price:currency"]')?.content,
            'value': document.querySelector('meta[property="product:price:amount"]')?.content,
            'items': [item],
        });
    }
}
