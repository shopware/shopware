import Plugin from 'src/plugin-system/plugin.class';
import Debouncer from 'src/helper/debouncer.helper';
import ButtonLoadingIndicator from 'src/utility/loading-indicator/button-loading-indicator.util';

/**
 * @module FormHandler - Form Handler Plugin
 *
 * This plugin is used to apply form handling to an HTML form.
 * It's main features are best-practices for accessible form validation.
 * The validation makes use of the global `FormValidation` class.
 *
 * You can use the Twig form templates in `storefront/component/form` to get
 * ready to use form fields that implement all the best-practices.
 *
 * @example
 * <form data-form-handler="true">
 *
 *     {% sw_include '@Storefront/storefront/component/form/form-input.html.twig' with {
 *         label: 'account.personalFirstNameLabel'|trans|sw_sanitize,
 *         id: 'personalFirstName',
 *         name: 'firstName',
 *         value: data.get('firstName'),
 *         autocomplete: 'section-personal given-name',
 *         violationPath: violationPath,
 *         validationRules: 'required',
 *         additionalClass: 'col-sm-6',
 *     } %}
 *
 *     <button type="submit">Submit</button>
 * </form>
 */
export default class FormHandler extends Plugin {

    static options = {

        /**
         * Define if the form should use form validation.
         *
         * @type {boolean}
         */
        validation: true,

        /**
         * Define if the form should be validated before submit.
         *
         * @type {boolean}
         */
        validateOnSubmit: true,

        /**
         * Define if the first invalid field should be focused,
         * if the form is validated on submit.
         *
         * @type {boolean}
         */
        focusInvalidField: true,

        /**
         * Define the debounce time of the field validation.
         *
         * @type {string}
         */
        debounceTime: '200',

        /**
         * Define the selector for gathering all fields of the form.
         *
         * @type {string}
         */
        formFieldSelector: 'input, textarea, select',

        /**
         * Define if a loading indicator should be shown on the submit button.
         *
         * @type {boolean}
         */
        loadingIndicator: true,

        /**
         * @type {'before' | 'after' | 'inner'} loadingIndicatorPosition
         */
        loadingIndicatorPosition: 'before',
    };

    init() {
        if (this._isFormElement(this.el) === false) {
            throw Error('Element is not of type <form>');
        }

        this.form = this.el;
        this.formFields = this.form.querySelectorAll(this.options.formFieldSelector);
        this.submitButtons = this._getSubmitButtons();

        if (this.options.validation === true) {
            this._initFormValidation();
        }

        this._initFormEvents();
    }

    /**
     * Initializes the necessary events on the form element.
     *
     * @private
     */
    _initFormEvents() {
        this.form.addEventListener('submit', this._onFormSubmit.bind(this));
    }

    /**
     * Initializes validation on the form.
     * It uses the central `FormValidation` class.
     *
     * @private
     */
    _initFormValidation() {
        // The native browser validation is disabled because it is not accessible.
        window.formValidation.setNoValidate(this.form);

        // Overriding the native checkValidity method to provide the native API with custom validation.
        this.form.checkValidity = this._checkValidity.bind(this);

        this._initFieldValidationEvents();
    }

    /**
     * Iterates through a set of form fields and initializes
     * the necessary validation on each field.
     *
     * @private
     */
    _initFieldValidationEvents() {
        this.formFields.forEach((field) => {
            this._initFieldValidation(field);
        });
    }

    /**
     * Initializes form validation on a form field.
     *
     * @param {HTMLElement} field
     * @private
     */
    _initFieldValidation(field) {
        const fieldType = field.tagName.toLowerCase();
        const validationEvent = fieldType === 'select' ? 'change' : 'input';

        field.addEventListener(
            validationEvent,
            Debouncer.debounce(
                window.formValidation.validateField.bind(window.formValidation, field),
                this.options.debounceTime
            )
        );
    }

    /**
     * The event handler will validate the form and handle the submitting.
     *
     * @param {SubmitEvent} event
     * @private
     */
    _onFormSubmit(event) {
        // Handle form validation
        if (this.options.validateOnSubmit === true) {
            const invalidFields = window.formValidation.validateForm(this.form, this.formFields);

            if (invalidFields.length > 0) {
                // If local validation failed, the form submit is prevented.
                event.preventDefault();

                // The focus will be set to the first invalid field.
                // The page will automatically scroll to the field with focus.
                if (this.options.focusInvalidField === true) {
                    invalidFields[0].focus();
                }

                return;
            }
        }

        // Form is valid and can be submitted.

        if (this.options.loadingIndicator === true) {
            this.submitButtons.forEach((submitButton) => {
                const loader = new ButtonLoadingIndicator(submitButton, this.options.loadingIndicatorPosition);
                loader.create();
            });
        }
    }

    /**
     * This method is used to override the native checkValidity method of the form.
     *
     * @return {boolean}
     * @private
     */
    _checkValidity() {
        const invalidFields = window.formValidation.validateForm(this.form, this.formFields);

        return invalidFields.length <= 0;
    }

    /**
     * Returns an array containing all associated submit buttons of the form.
     *
     * @return {HTMLElement[]}
     * @private
     */
    _getSubmitButtons() {
        const submitButtons = Array.from(this.form.querySelectorAll('button[type=submit]'));

        const formId = this.form.id;

        if (formId) {
            const outerSubmitButtons = Array.from(document.querySelectorAll(`button[type=submit][form="${formId}"]`));
            submitButtons.push(...outerSubmitButtons);
        }

        return submitButtons;
    }

    /**
     * Checks if the given element is a form element.
     *
     * @param {HTMLElement} el
     * @returns {boolean}
     * @private
     */
    _isFormElement(el) {
        return (el.tagName.toLowerCase() === 'form');
    }
}
