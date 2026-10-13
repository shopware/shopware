import EventAwareAnalyticsEvent from 'src/plugin/google-analytics/event-aware-analytics-event';

export default class LoginEvent extends EventAwareAnalyticsEvent
{
    /**
     * @param {string} controllerName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} actionName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} activeRoute
     * @returns {boolean}
     */
    supports(controllerName, actionName, activeRoute) {
        return (activeRoute === 'frontend.account.login.page') || (activeRoute === 'frontend.checkout.register.page');
    }

    /**
     * @return string
     */
    getPluginName() {
        // the login and register forms validate through FormHandler, which only publishes
        // `validSubmit` once the form is valid and about to be sent
        return 'FormHandler';
    }

    getEvents() {
        return {
            'validSubmit': this._onFormSubmit.bind(this),
        };
    }

    _onFormSubmit(event) {
        if (!this.active) {
            return;
        }

        const target = event.target;

        if (!target.classList.contains('login-form')) {
            return;
        }

        this.pushEvent('login', { method: 'mail'}, { ecommerce: false });
    }
}
