import Plugin from 'src/script/helper/plugin/plugin.class';
import DomAccess from 'src/script/helper/dom-access.helper';
import Debouncer from 'src/script/helper/debouncer.helper';
import Iterator from 'src/script/helper/iterator.helper';

const EQUAL_VALIDATION_ATTRIBUTE = 'data-form-validation-equal';
const LENGTH_VALIDATION_ATTRIBUTE = 'data-form-validation-length';

const VALIDATE_EQUAL_EVENT = 'ValidateEqual';
const VALIDATE_EQUAL_DEBOUNCE_TIME = 150;

/**
 * This plugin validates fields of a form.
 * Also styles the field elements with the bootstrap style if enabled.
 *
 * Usage:
 *
 * To check if two fields are equal, set the data attribute EQUAL_VALIDATION_ATTRIBUTE to
 * the same value on both fields.
 * If a field has the EQUAL_VALIDATION_ATTRIBUTE post-fixed with -message, it will show this
 * message below the field if invalid.
 *
 * <input data-form-validation-equal='myEqualValidation'>
 * <input data-form-validation-equal='myEqualValidation' data-form-validation-equal-message='the fields should be equal'>
 *
 * To check for min length on a field you have to pass the min number to the LENGTH_VALIDATION_ATTRIBUTE.
 * If a field has the LENGTH_VALIDATION_ATTRIBUTE post-fixed with -message, it will show this
 * message below the field if invalid.
 *
 * <input data-form-validation-length='8' data-form-validation-equal-message='this field must be at lest 8 characters long'>
 *
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
        this._registerValidationListener(EQUAL_VALIDATION_ATTRIBUTE, this._onValidateEqualTrigger.bind(this), ['keyup', 'change']);
        this._registerValidationListener(EQUAL_VALIDATION_ATTRIBUTE, Debouncer.debounce(this._onValidateEqual.bind(this), VALIDATE_EQUAL_DEBOUNCE_TIME), [VALIDATE_EQUAL_EVENT]);

        // length validation
        this._registerValidationListener(LENGTH_VALIDATION_ATTRIBUTE, this._onValidateLength.bind(this), ['keyup', 'change']);
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
        if (this.el.checkValidity() === false) {
            event.preventDefault();
            event.stopPropagation();
        }

        this.el.classList.add(this.options.styleCls);
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
        const selector = DomAccess.getDataAttribute(event.target, EQUAL_VALIDATION_ATTRIBUTE);
        const fields = DomAccess.querySelectorAll(this.el, `[${EQUAL_VALIDATION_ATTRIBUTE}='${selector}']`);

        Iterator.iterate(fields, field => {
            field.dispatchEvent(new CustomEvent(VALIDATE_EQUAL_EVENT, { target: event.target }));
        });
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
        const selector = DomAccess.getDataAttribute(event.target, EQUAL_VALIDATION_ATTRIBUTE);
        const fields = DomAccess.querySelectorAll(this.el, `[${EQUAL_VALIDATION_ATTRIBUTE}='${selector}']`);

        let valid = true;

        [...fields].reduce((field, nextField) => {
            if (field.value.trim() !== nextField.value.trim()) {
                valid = false;
            }
        });

        Iterator.iterate(fields, field => {
            if (!valid) {
                this._setFieldToInvalid(field, EQUAL_VALIDATION_ATTRIBUTE);
            } else {
                this._setFieldToValid(field, EQUAL_VALIDATION_ATTRIBUTE);
            }
        });
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
        const expectedLength = DomAccess.getDataAttribute(event.target, LENGTH_VALIDATION_ATTRIBUTE);

        if (value.length < expectedLength) {
            this._setFieldToInvalid(field, LENGTH_VALIDATION_ATTRIBUTE);
        } else {
            this._setFieldToValid(field, LENGTH_VALIDATION_ATTRIBUTE);
        }
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
                field.insertAdjacentHTML('afterEnd', `<div class="text-danger js-validation-message" data-type="${attribute}">${message}</div>`);
            }
            field.setCustomValidity(message);
        }
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
    }
}
