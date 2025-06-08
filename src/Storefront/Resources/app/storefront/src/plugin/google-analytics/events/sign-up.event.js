import EventAwareAnalyticsEvent from 'src/plugin/google-analytics/event-aware-analytics-event';

export default class SignUpEvent extends EventAwareAnalyticsEvent
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
        return {
            'beforeSubmit': this._onFormSubmit.bind(this),
        };
    }

    _onFormSubmit(event) {
        if (!this.active) {
            return;
        }

        const target = event.target;

        if (!target.classList.contains('register-form') || !event.detail.validity) {
            return;
        }

        gtag('event', 'sign_up', { method: 'mail'});
    }
}
