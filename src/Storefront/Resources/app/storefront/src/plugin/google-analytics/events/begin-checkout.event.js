import EventAwareAnalyticsEvent from 'src/plugin/google-analytics/event-aware-analytics-event';
import DomAccessHelper from 'src/helper/dom-access.helper';
import LineItemHelper from 'src/plugin/google-analytics/line-item.helper';

export default class BeginCheckoutEvent extends EventAwareAnalyticsEvent
{
    supports() {
        return true;
    }

    getEvents() {
        return {
            'offCanvasOpened': this._offCanvasOpened.bind(this)
        };
    }

    getPluginName() {
        return 'OffCanvasCart'
    }

    _offCanvasOpened() {
        DomAccessHelper.querySelector(document, '.begin-checkout-btn').addEventListener('click', this._onBeginCheckout.bind(this));
    }

    _onBeginCheckout() {
        if (!this.active) {
            return;
        }

        gtag('event', 'begin_checkout', {
            // @deprecated tag:v6.3.0 - context token will be removed
            'transaction_id': window.contextToken,
            'items': LineItemHelper.getLineItems()
        });
    }
}
