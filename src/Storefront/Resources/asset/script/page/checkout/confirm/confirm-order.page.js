import DomAccess from "../../../helper/dom-access.helper";
import ButtonLoadingIndicator from "../../../util/loading-indicator/button-loading-indicator.util";

const CONFIRM_ORDER_FORM_ID = 'confirmOrderForm';

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
     * Show loading indicator after submitting the order
     * @private
     */
    _onFormSubmit() {
        const submitButton = DomAccess.querySelector(this._form, `button[type=submit]`);

        // show loading indicator in submit button
        let loader = new ButtonLoadingIndicator(submitButton);
        loader.create();
    }
}
