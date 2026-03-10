import EventAwareAnalyticsEvent from 'src/plugin/google-analytics/event-aware-analytics-event';
import ProductPageHelper from 'src/plugin/google-analytics/product-page.helper';

export default class ViewItemListEvent extends EventAwareAnalyticsEvent
{
    /**
     * @param {string} controllerName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} actionName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} activeRoute
     * @returns {boolean}
     */
    supports() {
        const listingWrapper = document.querySelector('.cms-element-product-listing-wrapper');
        return !!listingWrapper;
    }

    getPluginName() {
        return 'Listing';
    }

    getEvents() {
        return {
            'Listing/afterRenderResponse': this._onListingChange.bind(this),
        };
    }

    execute() {
        // Fire on initial page load
        this._fireViewItemListEvent();

        // Subscribe to listing updates (pagination, filters)
        super.execute();
    }

    _onListingChange() {
        this._fireViewItemListEvent();
    }

    _fireViewItemListEvent() {
        if (!this.active) {
            return;
        }

        const items = this.getListItems();
        if (items.length === 0) {
            return;
        }

        // Calculate total value of all visible items
        const value = items.reduce((sum, item) => sum + (parseFloat(item.price) || 0), 0);

        gtag('event', 'view_item_list', {
            'currency': ProductPageHelper.getCurrency(),
            'value': value.toFixed(2),
            'items': items,
        });
    }

    getListItems() {
        const productBoxes = document.querySelectorAll('.product-box');
        const lineItems = [];

        if (!productBoxes) {
            return lineItems;
        }

        // Get category from breadcrumbs (same for all items on this page)
        const categories = ProductPageHelper.getCategories();

        productBoxes.forEach(item => {
            if (item.dataset.productInformation) {
                const productData = JSON.parse(item.dataset.productInformation);
                lineItems.push({
                    ...productData,
                    ...categories,
                });
            }
        });

        return lineItems;
    }
}
