import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';
import DomAccessHelper from 'src/helper/dom-access.helper';

export default class ViewItemListEvent extends AnalyticsEvent
{
    supports() {
        const listingWrapper = DomAccessHelper.querySelector(document, '.cms-element-product-listing-wrapper', false);
        return !!listingWrapper;
    }

    execute() {
        gtag('event', 'view_item_list', {
            'items': this.getListItems()
        });
    }

    getListItems() {
        const productBoxes = DomAccessHelper.querySelectorAll(document, '.product-box', false);
        const lineItems = [];

        productBoxes.forEach(item => {
            const form = DomAccessHelper.querySelector(item, '.buy-widget');
            const id = this.fetchProductId(DomAccessHelper.querySelectorAll(form, 'input'));
            const name = DomAccessHelper.querySelector(form, 'input[name=product-name]').value;

            if (!id || !name) {
                return;
            }

            lineItems.push({
                id,
                name
            });
        });

        return lineItems;
    }

    fetchProductId(inputs) {
        let productId = null;

        inputs.forEach(item => {
            if (DomAccessHelper.getAttribute(item, 'name').endsWith('[id]')) {
                productId = item.value;
            }
        });

        return productId;
    }
}
