/**
 * @module FormValidation
 *
 * This class is a service to make HTML form validation easy.
 * It helps to implement all best-practices for accessible form validation.
 *
 * The class is made available as a central instance at the window object.
 * Use the global instance to make use of the default form handling or create your own.
 *
 * @example
 * const form = document.getElementById('contact-form');
 * const invalidFields = window.formValidation.validateForm(form);
 *
 * This service is used by the form handler plugin.
 * You can use the plugin to apply the full form handling to your form.
 * Use the associated data attribute to activate the plugin.
 *
 * @example
 * <form data-form-handler="true">
 *      <input type="email" data-validation="required,email">
 * </form>
 *
 * To get the full set of best practices, you can use the form components
 * in Twig under `storefront/component/form` to render proper form fields in your form.
 * You can use them via `{% sw_include }%` to import the template.
 *
 * @example
 * {% sw_include '@Storefront/storefront/component/form/form-input.html.twig' with {
 *     label: 'account.personalFirstNameLabel'|trans|sw_sanitize,
 *     id: 'personalFirstName',
 *     name: 'firstName',
 *     value: data.get('firstName'),
 *     autocomplete: 'section-personal given-name',
 *     violationPath: violationPath,
 *     validationRules: 'required',
 *     additionalClass: 'col-sm-6',
 * } %}
 */
export default class FormValidation {

    static config = {

        /**
         * The css class that is applied for valid state.
         *
         * @type {string}
         */
        validClass: 'is-valid',

        /**
         * The css class that is applied to feedback text of valid fields.
         *
         * @type {string}
         */
        validFeedbackClass: 'valid-feedback',

        /**
         * The css class that is applied to invalid fields.
         *
         * @type {string}
         */
        invalidClass: 'is-invalid',

        /**
         * The cass class that is applied to feedback text of invalid fields.
         *
         * @type {string}
         */
        invalidFeedbackClass: 'invalid-feedback',

        /**
         * The default min length value of the minlength validator.
         *
         * @type {number}
         */
        defaultMinLength: 8,
    };

    constructor(config) {
        this.config = Object.assign({}, FormValidation.config, config);

        this.errorMessages = new Map();
        this.validators = new Map();

        this._initDefaultValidators();
    }

    /**
     * Initializes the default validators available for form validation.
     *
     * @private
     */
    _initDefaultValidators() {
        this.addValidator('required', this.validateRequired, window.validationMessages['required']);
        this.addValidator('email', this.validateEmail, window.validationMessages['email']);
        this.addValidator('confirmation', this.validateConfirmation, window.validationMessages['confirmation']);
        this.addValidator('minLength', this.validateMinLength, window.validationMessages['minLength']);
    }

    /**
     * Add a validator rule that can be used for form validation.
     *
     * @param {string} validatorName - The technical name under which the validation rule is tracked.
     * @param {function} validationFunction - The function that does the validation of the field value.
     * @param {string} errorMessage - The validation message that should be shown if the validation fails.
     * @returns {boolean}
     */
    addValidator(validatorName, validationFunction, errorMessage) {
        if (!validatorName || !validatorName.length) {
            console.error(`[FormValidation]: Invalid name for validator provided. Given "${validatorName}".`);
            return false;
        }

        if (!(typeof validationFunction === 'function')) {
            console.error(`[FormValidation]: Provided validator for ${validatorName} is no valid function.`);
            return false;
        }

        if (errorMessage && errorMessage.length) {
            this.errorMessages.set(validatorName, errorMessage);
        }

        this.validators.set(validatorName, validationFunction);
        return true;
    }

    /**
     * Add an error message for a matching validator.
     *
     * @param {string} validatorName - The technical name of the validator the error message belongs to.
     * @param {string} errorMessage - The content of the error message.
     * @returns {boolean}
     */
    addErrorMessage(validatorName, errorMessage) {
        if (!validatorName || !validatorName.length) {
            console.error('[FormValidation]: Missing or invalid name for validator provided.');
            return false;
        }

        if (!errorMessage || !errorMessage.length) {
            console.error('[FormValidation]: Missing or invalid error message provided.');
            return false;
        }

        this.errorMessages.set(validatorName, errorMessage);
        return true;
    }

    /**
     * Set a config property of the form validation.
     *
     * @param {string} key
     * @param {any} value
     */
    setConfig(key, value) {
        this.config[key] = value;
    }

    /**
     * Validates all fields of a form with their individual validation config.
     * Returns an array of all invalid fields.
     *
     * @param {HTMLFormElement} form
     * @param {NodeList} formFields
     *
     * @returns {boolean|HTMLElement[]}
     */
    validateForm(form, formFields) {
        if (!this.isFormElement(form)) {
            console.error('[FormValidation]: Provided form is no valid HTML element.');
            return false;
        }

        const invalidFields = [];
        let fields = formFields;

        if (!formFields) {
            fields = form.querySelectorAll('[data-validation], [required]');
        }

        fields.forEach((field) => {
            const fieldErrors = this.validateField(field);

            if (fieldErrors && fieldErrors.length > 0) {
                invalidFields.push(field);
            }
        });

        return invalidFields;
    }

    /**
     * Validates a single field based on its validation rules.
     * You can define the rules via the data attribute `data-validation`.
     * Add a comma separated list of validator names in the attribute.
     * The validators and associated messages are prioritized by their order.
     * The first validator has the highest priority and so forth.
     * Some validators might access additional attributes of the field,
     * like the `minlength` attribute for the "minLength" validator.
     *
     * @example
     * <input type="email" data-validation="required,email">
     *
     * @param {HTMLElement} field
     * @returns {boolean|string[]}
     */
    validateField(field) {
        if (!(field instanceof HTMLElement)) {
            console.error(`[FormValidation]: Provided field is no valid HTML element. Given ${field}.`);
            return false;
        }

        const isVisible = this.checkVisibility(field);

        // If the field is hidden then skip validation.
        // This can happen due to optional fields which are only active in certain conditions.
        if (!isVisible && field.type !== 'hidden') {
            // You can still validate non visible fields by setting the `data-validate-hidden` attribute.
            const validateHiddenAttr = field.getAttribute('data-validate-hidden');

            if (!validateHiddenAttr) {
                this.setFieldNeutral(field);
                return true;
            }
        }

        const validationConfig = field.getAttribute('data-validation');
        const validationRules = validationConfig ? validationConfig.split(',') : [];
        const hasRequiredAttribute = field.hasAttribute('required');

        // Support for the native `required` attribute.
        if (hasRequiredAttribute && !validationRules.includes('required')) {
            validationRules.push('required');
        }

        // Field has no validation rules.
        if (validationRules.length === 0) {
            this.setFieldNeutral(field);
            return true;
        }

        const value = field.value.trim();

        const validationErrors = validationRules.reduce((errors, validationRule) => {
            const validator = this.validators.get(validationRule);

            if (!validator) {
                console.warn(`[FormValidation]: No validator registered for validation rule "${validationRule}".`);
                return errors;
            }

            if (validator.call(this, value, field) === false) {
                errors.push(validationRule);
            }

            return errors;
        }, []);

        if (validationErrors.length > 0) {
            this.setFieldInvalid(field, validationErrors);
        } else {
            this.setFieldNeutral(field);
        }

        return validationErrors;
    }

    /**
     * Checks if the value is not empty.
     *
     * @param {string} value
     * @param {HTMLElement} field
     * @returns {boolean}
     */
    validateRequired(value, field) {
        const fieldType = field.getAttribute('type');

        if (fieldType && fieldType === 'checkbox') {
            return field.checked;
        }

        return !!value && value.length && value.length > 0;
    }

    /**
     * Checks if the value is a valid email address.
     *
     * @param {string} value
     * @returns {boolean}
     */
    validateEmail(value) {
        const emailRegEx = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;

        return emailRegEx.test(value);
    }

    /**
     * Checks if the confirmation field is equal to the main field.
     * The validator works based on the ID naming of the field.
     * The confirmation field should have the same ID as the original field,
     * but with the suffix "Confirmation" to it.
     *
     * @param {string} value
     * @param {HTMLElement} field
     */
    validateConfirmation(value, field) {
        if (!(field instanceof HTMLElement)) {
            console.error('[FormValidation]: Missing or invalid required parameter "field".');
            return true;
        }

        const fieldId = field.getAttribute('id');
        const comparisonFieldId = fieldId.replace('Confirmation', '');
        const comparisonField = document.getElementById(comparisonFieldId);

        if (!(comparisonField instanceof HTMLElement)) {
            console.warn(`[FormValidation]: No matching comparison field found for "${fieldId}".`);
            return true;
        }

        return field.value.trim() === comparisonField.value.trim();
    }

    /**
     * Checks the value for a minimum length.
     * If the field has a minlength attribute it will be checked against the value of the attribute,
     * otherwise the `defaultMinLength` config will be used.
     *
     * @param {string} value
     * @param {HTMLElement} field
     * @return {boolean}
     */
    validateMinLength(value, field) {
        if (!(field instanceof HTMLElement)) {
            console.error('[FormValidation]: Missing or invalid required parameter "field".');
            return true;
        }

        const minLengthAttr = field.getAttribute('minlength');
        const minLength = minLengthAttr ? minLengthAttr : this.config.defaultMinLength;

        return value.length >= minLength;
    }

    /**
     * Sets the field status to valid.
     * Applies all necessary styles and attributes.
     *
     * @param {HTMLElement} field
     */
    setFieldValid(field) {
        field.removeAttribute('aria-invalid');
        field.classList.remove(this.config.invalidClass);
        field.classList.add(this.config.validClass);

        this.resetFieldFeedback(field);
    }

    /**
     * Sets the field status to invalid.
     * Applies all necessary styles and attributes.
     *
     * @param {HTMLElement} field
     * @param validationErrors
     */
    setFieldInvalid(field, validationErrors) {
        field.setAttribute('aria-invalid', 'true');
        field.classList.remove(this.config.validClass);
        field.classList.add(this.config.invalidClass);

        this.setFieldValidationMessage(field, validationErrors);
    }

    /**
     * Sets the field status to neutral.
     * Applies all necessary styles and attributes.
     *
     * @param {HTMLElement} field
     */
    setFieldNeutral(field) {
        field.removeAttribute('aria-invalid');
        field.classList.remove(this.config.invalidClass);
        field.classList.remove(this.config.validClass);

        this.resetFieldFeedback(field);
    }

    /**
     * Sets a form field to be required.
     * It sets all necessary attributes and updates the corresponding label.
     *
     * @param {HTMLElement} field
     */
    setFieldRequired(field) {
        const validationRules = field.getAttribute('data-validation');
        const label = document.querySelector(`[for="${field.id}"]`);

        if (label) {
            const requiredLabel = label.querySelector('.form-required-label');

            if (!requiredLabel) {
                const requiredNote = document.createElement('span');
                requiredNote.classList.add('form-required-label');
                requiredNote.setAttribute('aria-hidden', 'true');
                requiredNote.innerHTML = ' *';

                label.appendChild(requiredNote);
            }
        }

        if (validationRules) {
            if (!validationRules.includes('required')) {
                field.setAttribute('data-validation', `required,${validationRules}`);
            }
        } else {
            field.setAttribute('data-validation', 'required');
        }

        field.setAttribute('aria-required', 'true');
    }

    /**
     * Sets a form field to be not required.
     * It removes all necessary attributes and updates the corresponding label.
     *
     * @param {HTMLElement} field
     */
    setFieldNotRequired(field) {
        const validationRules = field.getAttribute('data-validation');
        const label = document.querySelector(`[for="${field.id}"]`);
        const requiredLabel = label.querySelector('.form-required-label');

        if (validationRules) {
            const rules = validationRules.split(',');
            rules.splice(rules.indexOf('required'), 1);
            field.setAttribute('data-validation', rules.join(','));
        }

        if (requiredLabel) {
            requiredLabel.remove();
        }

        field.removeAttribute('aria-required');
    }

    /**
     * Sets the validation message within the feedback text of the form field.
     * Only the error message with the highest validation priority will be shown.
     *
     * @param {HTMLElement} field
     * @param {string[]} validationErrors
     * @returns {boolean}
     */
    setFieldValidationMessage(field, validationErrors) {
        if (!(field instanceof HTMLElement)) {
            console.error('[FormValidation]: Missing or invalid parameter provided for "field".');
            return false;
        }

        if (!validationErrors || !validationErrors.length) {
            return true;
        }

        const describedBy = field.getAttribute('aria-describedby');

        if (!describedBy) {
            console.warn('[FormValidation]: field is missing the necessary feedback element referenced with "aria-describedby".', field);
            return false;
        }

        const describedByIds = describedBy.split(' ');
        const feedbackElId = describedByIds.find(id => id.includes('feedback'));
        const fieldFeedbackEl = document.getElementById(feedbackElId);

        if (!(fieldFeedbackEl instanceof HTMLElement)) {
            console.warn('[FormValidation]: Missing form field feedback element to show validation messages.');
            return false;
        }

        fieldFeedbackEl.innerHTML = '';

        /**
         * Only the error message with the highest validation priority will be shown.
         * You can define the validation priority simply by the order of validation rules.
         */
        const highestPriorityError = validationErrors[0];
        const errorMessage = this.errorMessages.get(highestPriorityError);

        if (errorMessage && errorMessage.length) {
            const errorText = document.createElement('div');
            errorText.classList.add(this.config.invalidFeedbackClass);
            errorText.textContent = errorMessage;

            fieldFeedbackEl.appendChild(errorText);
        }

        return true;
    }

    /**
     * Restes the form field feedback text.
     *
     * @param {HTMLElement} field
     * @return {boolean}
     */
    resetFieldFeedback(field) {
        const describedBy = field.getAttribute('aria-describedby');

        if (!describedBy) {
            return false;
        }

        const describedByIds = describedBy.split(' ');
        const feedbackId = describedByIds.find(id => id.includes('feedback'));
        const fieldFeedbackEl = document.getElementById(feedbackId);

        if (!(fieldFeedbackEl instanceof HTMLElement)) {
            return false;
        }

        fieldFeedbackEl.innerHTML = '';
        return true;
    }

    /**
     * Checks if an HTML element is visible within the page.
     *
     * @param {HTMLElement} el
     *
     * @returns {boolean}
     */
    checkVisibility(el) {
        if (typeof el.checkVisibility === 'function') {
            return el.checkVisibility();
        }

        return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
    }

    /**
     * Sets the `novalidate` attribute on a form.
     * It is used to disable the standard browser validation.
     * Standard browser validation is seen as not accessible enough.
     * This is why the custom validation is applied.
     *
     * @param {HTMLElement} el
     */
    setNoValidate(el) {
        el.setAttribute('novalidate', 'true');
    }

    /**
     * Checks if the provided element is a form element.
     *
     * @param {HTMLElement} el
     * @returns {boolean}
     */
    isFormElement(el) {
        return (el.tagName.toLowerCase() === 'form');
    }
}
