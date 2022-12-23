import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';
import Debouncer from 'src/helper/debouncer.helper';
import Iterator from 'src/helper/iterator.helper';

/**
 * This plugin validates fields of a form.
 * Also styles the field elements with the bootstrap style if enabled.
 *
 * Usage:
 *
 * To check if two fields are equal, set the data attribute this.options.equalAttr to
 * the same value on both fields.
 * If a field has the options.equalAttr suffixed with `-message`, it will show this
 * message below the field if invalid.
 *
 * <input data-form-validation-equal='myEqualValidation'>
 * <input data-form-validation-equal='myEqualValidation' data-form-validation-equal-message='the fields should be equal'>
 *
 * To check for min length on a field you have to pass the min number to the options.lengthAttr.
 * If a field has the options.lengthAttr suffixed with `-message`, it will show this
 * message below the field if invalid.
 *
 * <input data-form-validation-length='8' data-form-validation-equal-message='this field must be at least 8 characters long'>
 *
 * @package content
 */
export default class FormValidation extends Plugin {

    static options = {

        /**
         * whether or not the input styling is enabled
         */
        stylingEnabled: true,

        /**
         * class to add when the field should have styling
         */
        styleCls: 'was-validated',

        hintCls: 'invalid-feedback',

        debounceTime: '150',

        eventName: 'ValidateEqual',

        equalAttr: 'data-form-validation-equal',

        lengthAttr: 'data-form-validation-length',

        /**
         * Use an already visible text as a hint for the length-validation
         */
        lengthTextAttr: 'data-form-validation-length-text',

        requiredAttr: 'data-form-validation-required',
    };

    init() {
        if (this._isFormElement() === false) {
            throw Error('Element is not of type <form>');
        }

        if (this.options.stylingEnabled) {
            this._setNoValidate();
        }

        this._registerEvents();
    }

    /**
     * Verify whether the plugin element is of type <form> or not
     *
     * @returns {boolean}
     *
     * @private
     */
    _isFormElement() {
        return (this.el.tagName.toLowerCase() === 'form');
    }

    /**
     * Prepares the form for custom Bootstrap form validation
     *
     * @private
     */
    _setNoValidate() {
        this.el.setAttribute('novalidate', '');
    }

    /**
     * Registers all needed events
     *
     * @private
     */
    _registerEvents() {
        if (this.options.stylingEnabled) {
            this.el.addEventListener('submit', this._onFormSubmit.bind(this));
        }

        // equal validation
        this._registerValidationListener(this.options.equalAttr, this._onValidateEqualTrigger.bind(this), ['change']);
        this._registerValidationListener(this.options.equalAttr, Debouncer.debounce(this._onValidateEqual.bind(this), this.options.debounceTime), [this.options.eventName]);

        // length validation
        this._registerValidationListener(this.options.lengthAttr, this._onValidateLength.bind(this), ['change']);

        // required validation
        this._registerValidationListener(this.options.requiredAttr, this._onValidateRequired.bind(this), ['change']);
    }

    /**
     * @param {string} attribute
     * @param {function} listener
     * @param {Event} events
     *
     * @private
     */
    _registerValidationListener(attribute, listener, events) {
        const fields = DomAccess.querySelectorAll(this.el, `[${attribute}]`, false);
        if (fields) {
            Iterator.iterate(fields, field => {
                Iterator.iterate(events, event => {
                    field.removeEventListener(event, listener);
                    field.addEventListener(event, listener);
                });
            });
        }
    }

    /**
     * Checks form validity before submit
     *
     * @param {Event} event
     *
     * @private
     */
    _onFormSubmit(event) {
        const validity = this.el.checkValidity();
        if (validity === false) {
            event.preventDefault();
            event.stopPropagation();
        }

        this.el.classList.add(this.options.styleCls);

        this.$emitter.publish('beforeSubmit', { validity });
    }

    /**
     * trigger the equal validation event
     * if one of the fields has changed
     *
     * @param {Event} event
     *
     * @private
     */
    _onValidateEqualTrigger(event) {
        const selector = DomAccess.getDataAttribute(event.target, this.options.equalAttr);
        const fields = DomAccess.querySelectorAll(this.el, `[${this.options.equalAttr}='${selector}']`);
        const confirmField = fields[1];
        const confirmFieldValue = confirmField.value.trim();

        if (confirmFieldValue.length > 0) {
            Iterator.iterate(fields, field => {
                field.dispatchEvent(new CustomEvent(this.options.eventName, {target: event.target}));
            });
        }

        this.$emitter.publish('onValidateEqualTrigger');
    }

    /**
     * validates if the fields with matching data attributes
     * have the same value.
     *
     * @param {Event} event
     *
     * @private
     */
    _onValidateEqual(event) {
        const selector = DomAccess.getDataAttribute(event.target, this.options.equalAttr);
        const fields = DomAccess.querySelectorAll(this.el, `[${this.options.equalAttr}='${selector}']`);

        let valid = true;

        [...fields].reduce((field, nextField) => {
            if (field.value.trim() !== nextField.value.trim()) {
                valid = false;
            }
        });

        Iterator.iterate(fields, field => {
            if (!valid) {
                this._setFieldToInvalid(field, this.options.equalAttr);
            } else {
                this._setFieldToValid(field, this.options.equalAttr);
            }
        });

        this.$emitter.publish('onValidateEqual');
    }

    /**
     * validate if the field character count
     * has reached the minimum of the given value
     * within the data attribute.
     *
     * @param {Event} event
     *
     * @private
     */
    _onValidateLength(event) {
        const field = event.target;
        const value = field.value.trim();
        const expectedLength = DomAccess.getDataAttribute(event.target, this.options.lengthAttr);
        const formText = field.nextElementSibling;

        if (value.length < expectedLength) {
            this._setFieldToInvalid(field, this.options.lengthAttr);

            // if a form text exists, give it the invalid styling
            if (formText && formText.hasAttribute(this.options.lengthTextAttr)) {
                formText.classList.add(this.options.hintCls);
            }
        } else {
            this._setFieldToValid(field, this.options.lengthAttr);

            if (formText && formText.hasAttribute(this.options.lengthTextAttr)) {
                formText.classList.remove(this.options.hintCls);
            }
        }

        this.$emitter.publish('onValidateLength');
    }

    /**
     * validate if the field value is blank
     * within the data attribute.
     *
     * @param event
     *
     * @private
     */
    _onValidateRequired(event) {
        const field = event.target;

        if (field.value.trim() === '') {
            this._setFieldToInvalid(field, this.options.requiredAttr);
        } else {
            this._setFieldToValid(field, this.options.requiredAttr);
        }

        this.$emitter.publish('onValidateRequired');
    }

    /**
     *  sets the field to invalid
     *
     * @param {HTMLElement} field
     * @param {string} attribute
     *
     * @private
     */
    _setFieldToInvalid(field, attribute) {
        this._showInvalidMessage(field, attribute);
        field.setAttribute('invalid', true);

        this.$emitter.publish('setFieldToInvalid');
    }

    /**
     * shows the custom validation message
     *
     * @param {HTMLElement} field
     * @param {string} attribute
     *
     * @private
     */
    _showInvalidMessage(field, attribute) {
        const parent = field.parentElement;

        // add the was-validated class to the parent element
        // so that the field gets the bootstrap style
        if (parent && this.options.stylingEnabled) {
            parent.classList.add(this.options.styleCls);
        }

        const message = DomAccess.getDataAttribute(field, `${attribute}-message`, false);

        if (message) {
            if (!parent.querySelector('.js-validation-message')) {
                field.insertAdjacentHTML('afterEnd', `<div class="invalid-feedback js-validation-message" data-type="${attribute}">${message}</div>`);
            }
            field.setCustomValidity(message);
        }

        this.$emitter.publish('showInvalidMessage');
    }

    /**
     * sets the field to valid
     *
     * @param {HTMLElement} field
     * @param {string} attribute
     *
     * @private
     */
    _setFieldToValid(field, attribute) {
        this._hideInvalidMessage(field, attribute);
        field.removeAttribute('invalid');

        // removes the custom validity state
        field.setCustomValidity('');

        this.$emitter.publish('setFieldToValid');
    }

    /**
     * hides the custom validation message
     * if present
     *
     * @param {HTMLElement} field
     * @param {string} attribute
     *
     * @private
     */
    _hideInvalidMessage(field, attribute) {
        const parent = field.parentElement;

        // remove the was-validated class to the parent element
        // to remove the bootstrap style
        if (parent && this.options.stylingEnabled) {
            parent.classList.remove(this.options.styleCls);
        }

        if (parent) {
            const message = DomAccess.querySelector(parent, `.js-validation-message[data-type=${attribute}]`, false);
            if (message) {
                message.remove();
            }
        }

        this.$emitter.publish('hideInvalidMessage');
    }
}
