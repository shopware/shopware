import EventAwareAnalyticsEvent from 'src/plugin/google-analytics/event-aware-analytics-event';
import SessionStorage from 'src/helper/storage/session-storage.helper';

const PENDING_SIGN_UP_KEY = 'swGaPendingSignUp';

export default class SignUpEvent extends EventAwareAnalyticsEvent
{
    /**
     * @returns {boolean}
     */
    supports() {
        // the sign-up is reported on the page the shopper lands on, which can be any page
        return true;
    }

    execute() {
        super.execute();
        this._reportPendingSignUp();
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

        if (!target.classList.contains('register-form')) {
            return;
        }

        // a guest checkout leaves "Create customer account" unchecked and creates no account
        if (!new FormData(target).get('createCustomerAccount')) {
            return;
        }

        // the server validates the registration only after the submit, so the sign-up is reported on the next page
        SessionStorage.setItem(PENDING_SIGN_UP_KEY, '1');
    }

    _reportPendingSignUp() {
        if (!SessionStorage.getItem(PENDING_SIGN_UP_KEY)) {
            return;
        }

        SessionStorage.removeItem(PENDING_SIGN_UP_KEY);

        // a failed registration renders the register form again with its fields marked as invalid
        if (!this.active || document.querySelector('.register-form .is-invalid')) {
            return;
        }

        this.pushEvent('sign_up', { method: 'mail'}, { ecommerce: false });
    }
}
