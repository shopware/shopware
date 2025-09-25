import EventAwareAnalyticsEvent from 'src/plugin/google-analytics/event-aware-analytics-event';
import LineItemHelper from 'src/plugin/google-analytics/line-item.helper';

export default class BeginCheckoutEvent extends EventAwareAnalyticsEvent
{
    /* eslint-disable no-unused-vars */
    /**
     * @param {string} controllerName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} actionName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} activeRoute
     * @returns {boolean}
     */
    supports(controllerName, actionName, activeRoute) {
        return !!document.querySelector('.begin-checkout-btn');
    }
    /* eslint-enable no-unused-vars */

    getEvents() {
        return {
            'offCanvasOpened': this._offCanvasOpened.bind(this),
        };
    }

    getPluginName() {
        return 'OffCanvasCart';
    }

    _offCanvasOpened() {
        document.querySelector('.begin-checkout-btn').addEventListener('click', this._onBeginCheckout.bind(this));
    }

    _onBeginCheckout() {
        if (!this.active) {
            return;
        }

        gtag('event', 'begin_checkout', {
            'items': LineItemHelper.getLineItems(),
        });
    }
}
