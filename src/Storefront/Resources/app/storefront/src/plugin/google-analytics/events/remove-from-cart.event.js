import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';
import DomAccessHelper from 'src/helper/dom-access.helper';

export default class RemoveFromCart extends AnalyticsEvent
{
    supports() {
        return true;
    }

    execute() {
        document.addEventListener('click', event => {
            const closest = event.target.closest('.cart-item-remove-button');
            if (!closest) {
                return;
            }

            gtag('event', 'remove_from_cart', {
                'items': [{
                    'id': DomAccessHelper.getDataAttribute(closest, 'product-id')
                }]
            });
        });
    }
}
