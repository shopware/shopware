import EventAwareAnalyticsEvent from 'src/plugin/google-analytics/event-aware-analytics-event';

export default class LoginEvent extends EventAwareAnalyticsEvent
{
    supports(controllerName, actionName, activeRoute) {
        return (activeRoute === 'frontend.account.login.page') || (activeRoute === 'frontend.checkout.register.page');
    }

    /**
     * @return string
     */
    getPluginName() {
        return 'FormValidation';
    }

    getEvents() {
        return {
            'beforeSubmit':  this._onFormSubmit.bind(this),
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
