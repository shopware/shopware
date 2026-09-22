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

        this.pushEvent('view_item_list', {
            'currency': ProductPageHelper.getCurrency(),
            'value': value,
            'items': items,
        });
    }

    getListItems() {
        const productBoxes = document.querySelectorAll('.product-box');
        const lineItems = [];

        if (!productBoxes) {
            return lineItems;
        }

        // The breadcrumb describes the listing rather than the product, so it is only the fallback
        // for product boxes whose page did not load the category associations.
        const breadcrumbCategories = ProductPageHelper.getCategories();

        productBoxes.forEach(item => {
            if (!item.dataset.productInformation) {
                return;
            }

            // The properties are mapped one by one on purpose. Spreading the parsed object would
            // put every key a theme or a later feature adds to `data-product-information` into the
            // GA4 item, where only documented properties belong.
            let productData;
            try {
                productData = JSON.parse(item.dataset.productInformation);
            } catch {
                return;
            }

            const categories = ProductPageHelper.mapCategories(productData.categories);

            lineItems.push({
                item_id: productData.sku ?? productData.id,
                item_name: productData.name,
                item_brand: productData.brand,
                item_variant: productData.variant,
                price: productData.price,
                ...(Object.keys(categories).length > 0 ? categories : breadcrumbCategories),
            });
        });

        return lineItems;
    }
}
