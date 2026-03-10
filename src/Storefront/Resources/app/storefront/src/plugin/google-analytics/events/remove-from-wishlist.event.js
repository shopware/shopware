import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';
import LineItemHelper from 'src/plugin/google-analytics/line-item.helper';
import ProductPageHelper from 'src/plugin/google-analytics/product-page.helper';

export default class RemoveFromWishlistEvent extends AnalyticsEvent
{
    supports() {
        return true;
    }

    execute() {
        this._registerWishlistStorageEvents();
        document.addEventListener('submit', this._onFormSubmit.bind(this));
    }

    _registerWishlistStorageEvents() {
        let instances;
        try {
            instances = window.PluginManager.getPluginInstances('WishlistStorage');
        } catch {
            return; // Optional plugin
        }

        if (!instances?.length) {
            return;
        }

        instances.forEach((pluginInstance) => {
            pluginInstance.$emitter.subscribe('Wishlist/onProductRemoved', this._onProductRemoved.bind(this));
        });
    }

    _onFormSubmit(event) {
        if (!this.active) {
            return;
        }

        const form = event.target;
        if (!form.classList.contains('product-wishlist-form')) {
            return;
        }

        const productId = this._extractProductIdFromForm(form);
        if (!productId) {
            return;
        }

        this._sendEvent(productId, form);
    }

    _onProductRemoved(event) {
        if (!this.active) {
            return;
        }

        const productId = event.detail?.productId;
        if (!productId) {
            return;
        }

        this._sendEvent(productId);
    }

    /**
     * Extracts product ID from wishlist form action URL
     * @param {HTMLFormElement} form
     * @returns {string|null}
     * @private
     */
    _extractProductIdFromForm(form) {
        const actionUrl = form.getAttribute('action');
        const match = actionUrl.match(/\/wishlist\/product\/delete\/([a-f0-9-]+)/i);
        return match ? match[1] : null;
    }

    /**
     * Sends the remove_from_wishlist event
     * @param {string} productId
     * @param {HTMLFormElement|null} form
     * @private
     */
    _sendEvent(productId, form = null) {
        // Try to get product data from product detail/listing page first
        let productData = ProductPageHelper.getProductData(productId, form);
        let categories = ProductPageHelper.getCategories();

        // Fallback to line item data (cart/checkout/finish pages)
        const lineItemData = LineItemHelper.getProductData(productId);
        if (!productData.name && lineItemData) {
            productData = lineItemData;
            categories = lineItemData.categories || {};
        }

        gtag('event', 'remove_from_wishlist', {
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

