import DomAccess from 'src/helper/dom-access.helper';
import ButtonLoadingIndicator from 'src/utility/loading-indicator/button-loading-indicator.util';
import Plugin from 'src/plugin-system/plugin.class';

/**
 * this plugin shows a loading indicator on the
 * form submit button when the form is submitted
 */
export default class FormSubmitLoaderPlugin extends Plugin {

    init() {
        if (!this._getForm() || !this._getSubmitButton()) {
            return;
        }

        this._registerEvents();
    }

    /**
     * tries to get the closest form
     *
     * @returns {HTMLElement|boolean}
     * @private
     */
    _getForm() {
        if (this.el && this.el.nodeName === 'FORM') {
            this._form = this.el;
            return true;
        }

        this._form = this.el.closest('form');

        return this._form;
    }

    /**
     * tries to get the submit button fo the form
     *
     * @returns {HTMLElement|boolean}
     * @private
     */
    _getSubmitButton() {
        this._submitButton = DomAccess.querySelector(this._form, 'button[type=submit]');

        if (!this._submitButton) {
            return this._getSubmitButtonWithId();
        }

        return true;
    }

    /**
     * tries to get the submit button
     * with the form id
     *
     * @returns {HTMLElement|boolean}
     * @private
     */
    _getSubmitButtonWithId() {
        const id = this._form.id;
        if (!id) return false;

        this._submitButton = DomAccess.querySelector(this._form, `button[type=submit][form=${id}]`);

        return this._submitButton;
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
        // show loading indicator in submit button
        const loader = new ButtonLoadingIndicator(this._submitButton);

        loader.create();

        /**
         * @deprecated tag:v6.5.0 - onFormSubmit event will be removed, use beforeSubmit instead
         */
        this.$emitter.publish('onFormSubmit');
        this.$emitter.publish('beforeSubmit');
    }
}
