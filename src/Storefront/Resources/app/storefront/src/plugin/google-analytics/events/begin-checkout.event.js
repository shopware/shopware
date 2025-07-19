import EventAwareAnalyticsEvent from 'src/plugin/google-analytics/event-aware-analytics-event';
import LineItemHelper from 'src/plugin/google-analytics/line-item.helper';

export default class BeginCheckoutEvent extends EventAwareAnalyticsEvent
{
    supports() {
        return !!document.querySelector('.begin-checkout-btn');
    }

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
