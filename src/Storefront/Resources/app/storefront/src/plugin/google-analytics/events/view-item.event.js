import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';
import DomAccessHelper from 'src/helper/dom-access.helper';

export default class ViewItemEvent extends AnalyticsEvent
{
    supports(controllerName, actionName) {
        return controllerName === 'product' && actionName === 'index';
    }

    execute() {
        if (!this.active) {
            return;
        }

        const productItemElement = DomAccessHelper.querySelector(
            document,
            '[itemtype="https://schema.org/Product"]',
            false
        );
        if (!productItemElement) {
            console.warn('[Google Analytics Plugin] Product itemtype ([itemtype="https://schema.org/Product"]) could not be found in document.');

            return;
        }

        const productIdElement = DomAccessHelper.querySelector(
            productItemElement,
            'meta[itemprop="productID"]',
            false
        );
        const productNameElement = DomAccessHelper.querySelector(
            productItemElement,
            '[itemprop="name"]',
            false
        );
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
