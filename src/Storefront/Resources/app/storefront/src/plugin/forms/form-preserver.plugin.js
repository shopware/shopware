import Debouncer from 'src/helper/debouncer.helper';
import DomAccess from 'src/helper/dom-access.helper';
import Plugin from 'src/plugin-system/plugin.class';
import Storage from 'src/helper/storage/storage.helper';

const TYPE_CHECKBOX = 'checkbox';
const TYPE_MULTI_SELECT = 'select-multiple';
const TYPE_RADIO = 'radio';

/**
 * This plugin preserves a form, if the element or the form itself has changed.
 * After a reload of the page the form is filled up with the stored values
 *
 * @package content
 */
export default class FormPreserverPlugin extends Plugin {

    static options = {
        /**
         * Indicates whether to throw an error if no form elements are found or not
         *
         * @type boolean
         */
        strictMode: false,

        /**
         * Form element types which should not be considered for preserving
         *
         * @type Array
         */
        ignoredElementTypes: ['button', 'file', 'hidden', 'image', 'password', 'reset', 'submit'],

        /**
         * Form element types which should be preserved with a delay once the input event is triggered.
         * Other types are preserved on the change event immediately. By default these types are:
         * ['checkbox', 'color', 'radio', 'select-one', 'select-multiple']
         *
         * @type Array
         */
        elementTypesForInputEvent: ['date', 'datetime-local', 'email', 'month', 'number', 'search', 'tel', 'text', 'textarea', 'time', 'week', 'url'],

        /**
         * Delay for the input events
         *
         * @type Number
         */
        delay: 300,
    };

    init() {
        this.storage = Storage;
        this.storedKeys = [];

        this._prepareElements();
        this._registerFormEvent();
    }

    /**
     * Iterates through all form elements, sets a stored value if existing and registers events
     *
     * @private
     */
    _prepareElements() {
        let formElements = this.el.elements;
        const outSideFormElements = DomAccess.querySelectorAll(document, `:not(form) > [form="${this.el.id}"]`, this.options.strictMode);

        formElements = Array.from(formElements);
        this.formElements = formElements.concat(Array.from(outSideFormElements));

        this.formElements.forEach((formElement) => {
            const elementType = formElement.type;
            if (this.options.ignoredElementTypes.includes(elementType)) {
                return;
            }

            this._registerFormElementEvent(formElement);
            this._setElementValue(formElement, elementType);
        });
    }

    /**
     * Registers needed event on provided form element
     *
     * @param {HTMLElement} formElement
     * @private
     */
    _registerFormElementEvent(formElement) {
        const onInput = Debouncer.debounce(this._onInput.bind(this), this.options.delay);
        if (this.options.elementTypesForInputEvent.includes(formElement.type)) {
            formElement.addEventListener('input', onInput);

            return;
        }

        formElement.addEventListener('change', this._onChange.bind(this));
    }

    /**
     * Sets the stored values to the form elements
     *
     * @param {HTMLElement} formElement
     * @param {String} elementType
     * @private
     */
    _setElementValue(formElement, elementType) {
        const key = this._generateKey(formElement.name);
        const storedValue = this.storage.getItem(key);
        if (storedValue === null) {
            return;
        }

        this.storedKeys.push(key);

        if (elementType === TYPE_CHECKBOX) {
            formElement.checked = storedValue;

            return;
        }

        if (elementType === TYPE_MULTI_SELECT) {
            this._setMultiSelectValues(formElement, storedValue);

            return;
        }

        if (elementType === TYPE_RADIO) {
            if (storedValue === formElement.value) {
                formElement.checked = true;
            }

            return;
        }

        formElement.value = storedValue;
    }

    /**
     * @param {InputEvent} event
     * @private
     */
    _onInput(event) {
        this._setToStorage(event.target);
    }

    /**
     * @param {Event} event
     * @private
     */
    _onChange(event) {
        this._setToStorage(event.target);
    }

    /**
     * Writes the value of the form element to the storage.
     *
     * @param {EventTarget|HTMLElement} formElement
     * @private
     */
    _setToStorage(formElement) {
        const key = this._generateKey(formElement.name);
        this.storedKeys.push(key);
        const elementType = formElement.type;

        if (elementType === TYPE_CHECKBOX) {
            if (formElement.checked) {
                this.storage.setItem(key, true);
            } else {
                this.storage.removeItem(key);
            }

            return;
        }

        if (elementType === TYPE_MULTI_SELECT) {
            this._storeMultiSelect(formElement, key);

            return;
        }

        const elementValue = formElement.value;
        if (elementValue !== '') {
            this.storage.setItem(key, formElement.value);

            return;
        }

        this.storage.removeItem(key);
    }

    /**
     * @param {HTMLSelectElement} multiSelectElement
     * @param {String} key
     * @private
     */
    _storeMultiSelect(multiSelectElement, key) {
        const selectedOptions = multiSelectElement.selectedOptions;
        if (selectedOptions.length === 0) {
            this.storage.removeItem(key);

            return;
        }

        const values = Array.from(selectedOptions).map(
            selectedOption => selectedOption.value
        );
        this.storage.setItem(key, values);
    }

    /**
     * @param {HTMLSelectElement} multiSelectElement
     * @param {String} storedValue
     * @private
     */
    _setMultiSelectValues(multiSelectElement, storedValue) {
        const selectedOptions = storedValue.split(',');
        const options = multiSelectElement.options;

        for (let index = 0; index < options.length; index++) {
            const option = options[index];
            if (selectedOptions.includes(option.value)) {
                option.selected = true;
            }
        }
    }

    /**
     * Registers a submit / reset event handler to the form to clear the storage.
     *
     * @private
     */
    _registerFormEvent() {
        this.el.addEventListener('submit', () => this._clearStorage());
        this.el.addEventListener('reset', () => this._clearStorage());
    }

    /**
     * If the form is submitted there is no longer the need to preserve the form element values,
     * so they are removed from the storage.
     *
     * @private
     */
    _clearStorage() {
        this.storedKeys.forEach((storedKey) => {
            this.storage.removeItem(storedKey);
        });
    }

    /**
     *
     * @param {String} elementName
     * @returns {String}
     * @private
     */
    _generateKey(elementName) {
        return `${this.el.id}.${elementName}`;
    }
}
