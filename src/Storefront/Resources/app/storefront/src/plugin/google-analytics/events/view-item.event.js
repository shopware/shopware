import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';
import ProductPageHelper from 'src/plugin/google-analytics/product-page.helper';

export default class ViewItemEvent extends AnalyticsEvent
{
    /**
     * @param {string} controllerName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} actionName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} activeRoute
     * @returns {boolean}
     */
    supports(controllerName, actionName, activeRoute) {
        return activeRoute === 'frontend.detail.page';
    }

    execute() {
        if (!this.active) {
            return;
        }

        const productData = ProductPageHelper.getProductDetailData();
        if (!productData.id || !productData.name) {
            console.warn('[Google Analytics Plugin] Product number (.product-detail-ordernumber) or product name (.product-detail-name) could not be found, do not track page view.');
            return;
        }

        this.pushEvent('view_item', {
            'currency': productData.currency,
            'value': productData.value,
            'items': [{
                'item_id': productData.id,
                'item_name': productData.name,
                'item_brand': productData.brand,
                'item_variant': productData.variant,
                'price': productData.value,
                ...ProductPageHelper.getCategories(),
            }],
        });
    }
}
