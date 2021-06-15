import EventAwareAnalyticsEvent from 'src/plugin/google-analytics/event-aware-analytics-event';
import Feature from 'src/helper/feature.helper';

export default class LoginEvent extends EventAwareAnalyticsEvent
{
    supports(controllerName, actionName) {
        return (controllerName === 'auth' && actionName === 'loginpage') || (controllerName === 'register' && actionName === 'checkoutregisterpage');
    }

    /**
     * @return string
     */
    getPluginName() {
        return 'FormValidation';
    }

    getEvents() {
        if (Feature.isActive('FEATURE_NEXT_12455')) {
            return {
                /**
                 * @feature-deprecated tag:v6.5.0 (flag:FEATURE_NEXT_12455) - onFormSubmit event will be removed, use beforeSubmit instead
                 */
                'onFormSubmit': this._onFormSubmit.bind(this),
                'beforeSubmit':  this._onFormSubmit.bind(this),
            };
        }

        return {
            'onFormSubmit': this._onFormSubmit.bind(this),
        };
    }

    _onFormSubmit(event) {
        if (!this.active) {
            return;
        }

        const target = event.target;

        if (!target.classList.contains('login-form') || !event.detail.validity) {
            return;
        }

        gtag('event', 'login', { method: 'mail'});
    }
}
