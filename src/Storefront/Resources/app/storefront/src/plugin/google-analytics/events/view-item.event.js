import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';

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

        const productItemElement = document.querySelector('[itemtype="https://schema.org/Product"]');
        if (!productItemElement) {
            console.warn('[Google Analytics Plugin] Product itemtype ([itemtype="https://schema.org/Product"]) could not be found in document.');

            return;
        }

        const productIdElement = productItemElement.querySelector('meta[itemprop="productID"]');
        const productNameElement = productItemElement.querySelector('[itemprop="name"]');
        if (!productIdElement || !productNameElement) {
            console.warn('[Google Analytics Plugin] Product ID (meta[itemprop="productID"]) or product name ([itemprop="name"]) could not be found within product scope.');

            return;
        }

        const productId = productIdElement.content;
        const productName = productNameElement.textContent.trim();
        if (!productId || !productName) {
            console.warn('[Google Analytics Plugin] Product ID or product name is empty, do not track page view.');

            return;
        }

        gtag('event', 'view_item', {
            'items': [{
                'id': productId,
                'name': productName,
            }],
        });
    }
}
