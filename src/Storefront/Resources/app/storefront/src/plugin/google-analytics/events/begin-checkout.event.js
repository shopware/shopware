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
            'openOffCanvasCart': this._onOpenOffCanvasCart.bind(this)
        };
    }

    getPluginName() {
        return 'AddToCart'
    }

    _onOpenOffCanvasCart() {
        DomAccessHelper.querySelector(document, '.begin-checkout-btn').addEventListener('click', this._onBeginCheckout.bind(this));
    }

    _onBeginCheckout() {
        gtag('event', 'begin_checkout', {
            'transaction_id': window.contextToken,
            'items': LineItemHelper.getLineItems()
        });
    }
}
