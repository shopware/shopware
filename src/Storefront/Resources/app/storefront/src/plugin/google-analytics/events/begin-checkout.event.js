import CheckoutStepHelper from 'src/plugin/google-analytics/checkout-step.helper';
import EventAwareAnalyticsEvent from 'src/plugin/google-analytics/event-aware-analytics-event';
import LineItemHelper from 'src/plugin/google-analytics/line-item.helper';

export default class BeginCheckoutEvent extends EventAwareAnalyticsEvent
{
    /**
     * @returns {boolean}
     */
    supports() {
        return true;
    }

    getEvents() {
        this._boundOnBeginCheckout = this._onBeginCheckout.bind(this);

        return {
            'offCanvasOpened': this._offCanvasOpened.bind(this),
        };
    }

    getPluginName() {
        return 'OffCanvasCart';
    }

    _offCanvasOpened() {
        const beginCheckoutBtn = document.querySelector('.begin-checkout-btn');
        if (!beginCheckoutBtn) {
            return;
        }

        beginCheckoutBtn.removeEventListener('click', this._boundOnBeginCheckout);
        beginCheckoutBtn.addEventListener('click', this._boundOnBeginCheckout);
    }

    _onBeginCheckout() {
        if (!this.active) {
            return;
        }

        // a new checkout starts, so its shipping and payment steps are reported again
        CheckoutStepHelper.reset();

        const additionalProperties = LineItemHelper.getAdditionalProperties();

        this.pushEvent('begin_checkout', {
            'currency': additionalProperties.currency,
            'value': additionalProperties.value,
            'coupon': additionalProperties.coupon,
            'items': LineItemHelper.getLineItems(),
        });
    }
}
