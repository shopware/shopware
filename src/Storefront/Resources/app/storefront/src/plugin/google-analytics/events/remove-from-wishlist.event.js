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

        // A handler that prevents the submit removes the product itself. The guest wishlist does so
        // through the wishlist storage, which is reported by `_onProductRemoved`.
        if (event.defaultPrevented) {
            return;
        }

        const productId = this._extractProductIdFromForm(form);
        if (!productId) {
            return;
        }

        // The wishlist page removes a product with a plain form submit, which leaves the page before
        // the category path of the product could be requested. The submit is held back until the
        // event is sent. The category request gives up after `ProductPageHelper.CATEGORY_REQUEST_TIMEOUT`, so a slow
        // request only sends the event without categories and never blocks the removal.
        event.preventDefault();

        return this._sendEvent(productId, form).finally(() => form.submit());
    }

    _onProductRemoved(event) {
        if (!this.active) {
            return;
        }

        const productId = event.detail?.productId;
        if (!productId) {
            return;
        }

        return this._sendEvent(productId);
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
     * Sends the remove_from_wishlist event. The product data is read before the first await, while
     * the product element is still on the page.
     * @param {string} productId
     * @param {HTMLFormElement|null} form
     * @private
     */
    async _sendEvent(productId, form = null) {
        // Try to get product data from product detail/listing page first
        let productData = ProductPageHelper.getProductData(productId, form);
        let categories;

        // Fallback to line item data (cart/checkout/finish pages)
        const lineItemData = LineItemHelper.getProductData(productId);
        if (!productData.name && lineItemData) {
            productData = lineItemData;
            categories = lineItemData.categories;
        }

        if (!categories || Object.keys(categories).length === 0) {
            categories = await ProductPageHelper.resolveCategories(productId, form, () => this.active);
        }

        // the shopper can revoke the tracking consent while the categories are requested
        if (!this.active) {
            return;
        }

        this.pushEvent('remove_from_wishlist', {
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

