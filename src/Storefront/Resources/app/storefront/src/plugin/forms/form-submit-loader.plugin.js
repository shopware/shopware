import DomAccess from 'src/helper/dom-access.helper';
import ButtonLoadingIndicator from 'src/utility/loading-indicator/button-loading-indicator.util';
import Plugin from 'src/plugin-system/plugin.class';
import {INDICATOR_POSITION} from 'src/utility/loading-indicator/loading-indicator.util';

/**
 * this plugin shows a loading indicator on the
 * form submit button when the form is submitted
 *
 * @package content
 */
export default class FormSubmitLoaderPlugin extends Plugin {

    /**
     * @type {{formWrapperSelector: string, indicatorPosition: string, skipLoadingIndicator: boolean}}
     */
    static options = {
        formWrapperSelector: 'body',

        /**
         * Possible values: before|after|inner
         */
        indicatorPosition: INDICATOR_POSITION.BEFORE,

        /**
         * If true, the loading indicator will not show
         */
        skipLoadingIndicator: false,
    };

    init() {
        if (!this._getForm() || !this._getSubmitButtons()) {
            return;
        }

        // check if validation plugin is active for this form
        this._validationPluginActive = !!window.PluginManager.getPluginInstanceFromElement(this._form, 'FormValidation');

        this._registerEvents();
    }

    /**
     * Tries to get the closest form
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
     * Tries to get the submit buttons for the form, returns false if
     * no button has been found, true otherwise
     *
     * @returns {boolean}
     * @private
     */
    _getSubmitButtons() {
        this._submitButtons = Array.from(DomAccess.querySelectorAll(this._form, 'button[type=submit]', false));

        const formId = this._form.id;
        if (formId) {
            this._submitButtons = this._submitButtons.concat(Array.from(
                DomAccess.querySelectorAll(
                    this._form.closest(this.options.formWrapperSelector),
                    `:not(form) > button[type=submit][form="${formId}"]`,
                    false
                )
            ));
        }

        return Boolean(this._submitButtons.length);
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
        // Abort when form.validation.plugin is active and form is not valid.
        // The validation plugin handles the submit itself in this case
        if (this._validationPluginActive) {
            if (this.el.checkValidity() === false) {
                return;
            }
        }
        // show loading indicator in submit buttons
        this._submitButtons.forEach((submitButton) => {
            if (this.options.skipLoadingIndicator) {
                submitButton.disabled = true;

                return;
            }

            const loader = new ButtonLoadingIndicator(submitButton, this.options.indicatorPosition);
            loader.create();
        });

        this.$emitter.publish('beforeSubmit');
    }
}
