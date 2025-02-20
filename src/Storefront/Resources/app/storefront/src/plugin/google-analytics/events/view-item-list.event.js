import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';
import DomAccessHelper from 'src/helper/dom-access.helper';

export default class ViewItemListEvent extends AnalyticsEvent
{
    supports() {
        const listingWrapper = DomAccessHelper.querySelector(document, '.cms-element-product-listing-wrapper', false);
        return !!listingWrapper;
    }

    execute() {
        if (!this.active) {
            return;
        }

        gtag('event', 'view_item_list', {
            'items': this.getListItems(),
        });
    }

    getListItems() {
        const productBoxes = DomAccessHelper.querySelectorAll(document, '.product-box', false);
        const lineItems = [];

        if (!productBoxes) {
            return;
        }

        productBoxes.forEach(item => {
            if (item.dataset['productInformation']) {
                lineItems.push(JSON.parse(item.dataset['productInformation']));
            }
        });

        return lineItems;
    }
}
