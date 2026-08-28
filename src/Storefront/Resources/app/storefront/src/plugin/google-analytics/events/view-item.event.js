import Feature from 'src/helper/feature.helper';
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
        let productId = productData.id;
        let productName = productData.name;

        if (!Feature.isActive('JSON_LD_DATA')) {
            const productItemElement = document.querySelector('[itemtype="https://schema.org/Product"]');
            if (!productItemElement) {
                console.warn('[Google Analytics Plugin] Product itemtype ([itemtype="https://schema.org/Product"]) could not be found in document.');
                return;
            }

            const productIdElement = productItemElement.querySelector('[itemprop="sku"]');
            const productNameElement = productItemElement.querySelector('[itemprop="name"]');
            if (!productIdElement || !productNameElement) {
                console.warn('[Google Analytics Plugin] Product ID ([itemprop="sku"]) or product name ([itemprop="name"]) could not be found within product scope.');
                return;
            }

            productId = productIdElement.textContent.trim();
            productName = productNameElement.textContent.trim();
        }

        if (!productId || !productName) {
            console.warn('[Google Analytics Plugin] Product ID or product name is empty, do not track page view.');
            return;
        }

        gtag('event', 'view_item', {
            'currency': productData.currency,
            'value': productData.value,
            'items': [{
                'id': productId,
                'name': productName,
                'brand': productData.brand,
                ...ProductPageHelper.getCategories(),
            }],
        });
    }
}
