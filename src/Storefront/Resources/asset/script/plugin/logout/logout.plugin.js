import DomAccess from 'asset/script/helper/dom-access.helper';
import HttpClient from 'asset/script/service/http-client.service';
import ButtonLoadingIndicator from 'asset/script/util/loading-indicator/button-loading-indicator.util';

const LOGOUT_TRIGGER_SELECTOR = '[data-logout]';

export default class Logout {

    /**
     * Constructor.
     */
    constructor() {
        this._client = new HttpClient(window.accessKey, window.contextToken);
        this._registerFormEvents();
    }

    /**
     * Register event to handle form submission for the logout
     * @private
     */
    _registerFormEvents() {
        const forms = document.querySelectorAll(LOGOUT_TRIGGER_SELECTOR);

        forms.forEach(form => {
            form.addEventListener('submit', this._onFormSubmit.bind(this));
        });
    }

    /**
     * On submitting the form the user is logged out and redirected to the given url
     * @param {Event} e
     * @private
     */
    _onFormSubmit(e) {
        e.preventDefault();

        const form = e.target;
        const requestUrl = DomAccess.getAttribute(form, 'action');
        const redirectTo = DomAccess.getAttribute(form, 'data-redirect');

        (new ButtonLoadingIndicator(
            form.querySelector('button[type="submit"]')
        )).create();

        this._client.post(requestUrl.toLowerCase(), JSON.stringify({}), () => {
            window.location.replace(redirectTo)
        });
    }

}
