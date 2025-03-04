import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';

export default class ViewItemListEvent extends AnalyticsEvent
{
    supports() {
        const listingWrapper = document.querySelector('.cms-element-product-listing-wrapper');
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
        const productBoxes = document.querySelectorAll('.product-box');
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
