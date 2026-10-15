import EventAwareAnalyticsEvent from 'src/plugin/google-analytics/event-aware-analytics-event';
import SessionStorage from 'src/helper/storage/session-storage.helper';

const PENDING_LOGIN_KEY = 'swGaPendingLogin';

export default class LoginEvent extends EventAwareAnalyticsEvent
{
    /**
     * @returns {boolean}
     */
    supports() {
        // the login is reported on the page the shopper lands on, which can be any page
        return true;
    }

    execute() {
        super.execute();
        this._reportPendingLogin();
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

        // the server checks the credentials only after the submit, so the login is reported on the next page
        SessionStorage.setItem(PENDING_LOGIN_KEY, '1');
    }

    _reportPendingLogin() {
        if (!SessionStorage.getItem(PENDING_LOGIN_KEY)) {
            return;
        }

        SessionStorage.removeItem(PENDING_LOGIN_KEY);

        // a failed login renders the login form again with its fields marked as invalid
        if (!this.active || document.querySelector('.login-form .is-invalid')) {
            return;
        }

        this.pushEvent('login', { method: 'mail'}, { ecommerce: false });
    }
}
