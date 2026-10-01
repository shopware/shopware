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
        return true;
    }
    /* eslint-enable no-unused-vars */

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

        const additionalProperties = LineItemHelper.getAdditionalProperties();

        gtag('event', 'begin_checkout', {
            'currency': additionalProperties.currency,
            'value': additionalProperties.value,
            'items': LineItemHelper.getLineItems(),
        });
    }
}
