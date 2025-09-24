import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';

export default class RemoveFromCart extends AnalyticsEvent
{
    /* eslint-disable no-unused-vars */
    /**
     * @param {string} controllerName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} actionName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} activeRoute
     * @returns {boolean}
     */
    supports(controllerName, actionName, activeRoute) {
        return true;
    }
    /* eslint-enable no-unused-vars */

    execute() {
        document.addEventListener('click', this._onRemoveFromCart.bind(this));
    }

    _onRemoveFromCart(event) {
        if (!this.active) {
            return;
        }

        const closest = event.target.closest('.line-item-remove-button');
        if (!closest) {
            return;
        }

        gtag('event', 'remove_from_cart', {
            'items': [{
                'id': closest.getAttribute('data-product-id'),
            }],
        });
    }
}
