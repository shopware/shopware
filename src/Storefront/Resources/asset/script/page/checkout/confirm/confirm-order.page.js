import DomAccess from "../../../helper/dom-access.helper";
import ButtonLoadingIndicator from "../../../plugin/loading-indicator/button-loading-indicator.plugin";
import HttpClient from "../../../service/http-client.service";

const CONFIRM_ORDER_FORM_ID = 'confirmOrderForm';
const CONFIRM_ORDER_FINISH_ROUTE = window.router['frontend.checkout.finish.page'];

export default class ConfirmOrder {

    /**
     * Constructor.
     */
    constructor() {
        this._form = DomAccess.querySelector(document, `#${CONFIRM_ORDER_FORM_ID}`, false);

        // early return if form hasn't been found
        if (this._form === false) {
            return;
        }

        this._registerEvents();
    }

    /**
     * Register event listeners
     * @private
     */
    _registerEvents() {
        // the submit event will be triggered only if the HTML5 validation has been successful
        this._form.addEventListener('submit', this._onFormSubmit.bind(this));
    }

    /**
     * Handle form submit event manually by preventing the usual form submission first.
     * Show loading indicator while placing the order via POST request against the storefront-api
     * and if successful redirect the user to the finish page.
     * @param {Event} e
     * @private
     */
    _onFormSubmit(e) {
        e.preventDefault();

        const client = new HttpClient(window.accessKey, window.contextToken);
        const url = DomAccess.getAttribute(this._form, 'action');
        const submitButton = DomAccess.querySelector(this._form, `button[type=submit]`);

        // show loading indicator in submit button
        let loader = new ButtonLoadingIndicator(submitButton);
        loader.create();

        client.post(url.toLowerCase(), '{}', (response) => {
            let obj = JSON.parse(response);

            if (obj.data.id) {
                window.location.replace(this._createRedirectUrl(obj.data.id));
            }

            // remove the loading indicator at the end for correct visualization
            loader.remove();
        });

    }

    /**
     * Build the route to redirect when order has been placed successfully
     * @param {string} orderId
     * @returns {string}
     * @private
     */
    _createRedirectUrl(orderId) {
        return `${CONFIRM_ORDER_FINISH_ROUTE}?orderId=${orderId}`;
    }
}