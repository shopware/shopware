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
            /** @deprecated tag:v6.7.0 - Data will be retrieved from the data attribute "data-product-information" instead of hidden inputs. */
            if (window.Feature.isActive('v6.7.0.0')) {
                if (item.dataset['productInformation']) {
                    lineItems.push(JSON.parse(item.dataset['productInformation']));
                }
            } else {
                const id = DomAccessHelper.querySelector(item, 'input[name=product-id]').value;
                const name = DomAccessHelper.querySelector(item, 'input[name=product-name]').value;

                if (!id || !name) {
                    return;
                }

                lineItems.push({id, name});
            }
        });

        return lineItems;
    }

    /**
     * @deprecated tag:v6.7.0 - Unused function will be removed without replacement
     */
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
