import Plugin from 'src/plugin-system/plugin.class';
/** @deprecated tag:v6.8.0 - HttpClient is deprecated. Use native fetch API instead. */
import HttpClient from 'src/service/http-client.service';

/**
 * @package discovery
 */
export default class CountryStateSelectPlugin extends Plugin {

    static options = {
        countrySelectSelector: '.country-select',
        initialCountryAttribute: 'data-initial-country-id',
        countryStateSelectSelector: '.country-state-select',
        initialCountryStateAttribute: 'data-initial-country-state-id',
        countryStatePlaceholderSelector: '[data-placeholder-option="true"]',
        vatIdFieldInput: '#vatIds',
        zipcodeFieldInput: '[data-input-name="zipcodeInput"]',
        vatIdRequired: 'data-vat-id-required',
        vatIdPattern: 'data-vat-id-pattern',
        checkVatIdPattern: 'data-check-vat-id-pattern',
        stateRequired: 'data-state-required',
        stateDisplayed: 'data-display-state-in-registration',
        zipcodeRequired: 'data-zipcode-required',
        zipcodePattern: 'data-zipcode-pattern',
        checkZipcodePattern: 'data-check-zipcode-pattern',
        scopeElementSelector: null,
        prefix: null,
    };

    init() {
        /** @deprecated tag:v6.8.0 - initClient is deprecated because client instance is no longer needed. Use native fetch API instead. */
        this.initClient();
        this.initSelects();

        this._getFormFieldToggleInstance();

        if (this._formFieldToggleInstance) {
            this._formFieldToggleInstance.$emitter.subscribe('onChange', this._onFormFieldToggleChange.bind(this));
        }
    }

    /** @deprecated tag:v6.8.0 - initClient is deprecated because client instance is no longer needed. Use native fetch API instead. */
    initClient() {
        /** @deprecated tag:v6.8.0 - HttpClient is deprecated. Use native fetch API instead. */
        this._client = new HttpClient();
    }

    initSelects() {
        this.scopeElement = this.el;

        if (this.options.scopeElementSelector) {
            this.scopeElement = document.querySelector(this.options.scopeElementSelector);
        }

        const { countrySelectSelector, countryStateSelectSelector, initialCountryAttribute, initialCountryStateAttribute } = CountryStateSelectPlugin.options;
        const countrySelect = this.scopeElement.querySelector(countrySelectSelector);
        const countryStateSelect = this.scopeElement.querySelector(countryStateSelectSelector);
        const initialCountryId = countrySelect.getAttribute(initialCountryAttribute);
        const initialCountryStateId = countryStateSelect.getAttribute(initialCountryStateAttribute);
        const countrySelectCurrentOption = countrySelect.options[countrySelect.selectedIndex];
        const vatIdRequired = !!countrySelectCurrentOption.getAttribute(this.options.vatIdRequired);
        const vatIdPattern = countrySelectCurrentOption.getAttribute(this.options.vatIdPattern);
        const checkVatIdPattern = countrySelectCurrentOption.getAttribute(this.options.checkVatIdPattern) === '1';
        const vatIdInput = document.querySelector(this.options.vatIdFieldInput);
        const stateRequired = !!countrySelectCurrentOption.getAttribute(this.options.stateRequired);
        const stateDisplayed = this._getStateDisplayed(countrySelectCurrentOption, stateRequired);

        const zipcodeInputs = this.scopeElement.querySelectorAll(this.options.zipcodeFieldInput);
        const zipcodeRequired = !!countrySelectCurrentOption.getAttribute(this.options.zipcodeRequired);
        const zipcodePattern = countrySelectCurrentOption.getAttribute(this.options.zipcodePattern);
        const checkZipcodePattern = countrySelectCurrentOption.getAttribute(this.options.checkZipcodePattern) === '1';

        countrySelect.addEventListener('change', this.onChangeCountry.bind(this));

        if (!initialCountryId) {
            return;
        }
        this.requestStateData(initialCountryId, initialCountryStateId, stateRequired, stateDisplayed);

        if (zipcodeRequired) {
            this._updateZipcodeFields(zipcodeInputs, zipcodeRequired);
        }

        this._updateZipcodePattern(zipcodeInputs, zipcodePattern, checkZipcodePattern);

        if (!vatIdInput) {
            return;
        }

        this._updateVatIdField(vatIdInput, vatIdRequired, vatIdPattern, checkVatIdPattern);
    }

    onChangeCountry(event) {
        const countryId = event.target.value;

        const countrySelect = event.target.options[event.target.selectedIndex];
        const stateRequired = !!countrySelect.getAttribute(this.options.stateRequired);
        const stateDisplayed = this._getStateDisplayed(countrySelect, stateRequired);
        this.requestStateData(countryId, null, stateRequired, stateDisplayed);
        const vatIdRequired = !!countrySelect.getAttribute(this.options.vatIdRequired);
        const vatIdPattern = countrySelect.getAttribute(this.options.vatIdPattern);
        const checkVatIdPattern = countrySelect.getAttribute(this.options.checkVatIdPattern) === '1';
        const vatIdInput = document.querySelector(this.options.vatIdFieldInput);

        const zipcodeInputs = this.scopeElement.querySelectorAll(this.options.zipcodeFieldInput);
        const zipcodeRequired = !!countrySelect.getAttribute(this.options.zipcodeRequired);
        const zipcodePattern = countrySelect.getAttribute(this.options.zipcodePattern);
        const checkZipcodePattern = countrySelect.getAttribute(this.options.checkZipcodePattern) === '1';

        this._updateZipcodeFields(zipcodeInputs, zipcodeRequired);
        this._updateZipcodePattern(zipcodeInputs, zipcodePattern, checkZipcodePattern);
        this._revalidateFilledFields(zipcodeInputs);

        if (vatIdInput) {
            this._updateVatIdField(vatIdInput, vatIdRequired, vatIdPattern, checkVatIdPattern);
            this._revalidateVatIdField(vatIdInput);
        }
    }

    requestStateData(countryId, countryStateId = null, stateRequired = false, stateDisplayed = true) {
        fetch(`${window.router['frontend.country.country-data']}?countryId=${encodeURIComponent(countryId)}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
            },
        })
            .then(response => response.json())
            .then(content => this._updateStateSelect(content.states, stateRequired, countryStateId, stateDisplayed));
    }

    /**
     * Updates the required state and pattern validation of the VAT id field.
     *
     * @param {HTMLElement} vatIdFieldInput
     * @param {boolean} vatIdRequired
     * @param {string|null} vatIdPattern
     * @param {boolean} checkVatIdPattern
     * @private
     */
    _updateVatIdField(vatIdFieldInput, vatIdRequired, vatIdPattern = null, checkVatIdPattern = false) {
        if (!this._ownsVatIdField()) {
            return;
        }

        if (vatIdRequired) {
            window.formValidation.setFieldRequired(vatIdFieldInput);
        } else {
            window.formValidation.setFieldNotRequired(vatIdFieldInput);
        }

        if (checkVatIdPattern && vatIdPattern) {
            vatIdFieldInput.setAttribute('pattern', vatIdPattern);
        } else {
            vatIdFieldInput.removeAttribute('pattern');
        }
    }

    /**
     * Whether this instance is responsible for the shared VAT ID input.
     *
     * @returns {boolean}
     * @private
     */
    _ownsVatIdField() {
        return !(this._differentShippingCheckbox && this.options.prefix === 'billingAddress');
    }

    /**
     * Re-validates the VAT ID field if this instance owns it.
     *
     * @param {HTMLElement} vatIdFieldInput
     * @private
     */
    _revalidateVatIdField(vatIdFieldInput) {
        if (!this._ownsVatIdField()) {
            return;
        }

        this._revalidateFilledFields([vatIdFieldInput]);
    }

    /**
     * Updates the required state of the zip code fields.
     *
     * @param {NodeList} inputs
     * @param {boolean} required
     * @private
     */
    _updateZipcodeFields(inputs, required = false) {
        if (!inputs) {
            return;
        }

        inputs.forEach((input) => {
            if (required === true) {
                window.formValidation.setFieldRequired(input);
            } else {
                window.formValidation.setFieldNotRequired(input);
            }
        });
    }

    /**
     * Updates the pattern validation of the zip code fields.
     *
     * @param {NodeList} inputs
     * @param {string|null} pattern
     * @param {boolean} checkPattern
     * @private
     */
    _updateZipcodePattern(inputs, pattern = null, checkPattern = false) {
        if (!inputs) {
            return;
        }

        inputs.forEach((input) => {
            if (checkPattern && pattern) {
                input.setAttribute('pattern', pattern);
            } else {
                input.removeAttribute('pattern');
            }
        });
    }

    /**
     * Re-validates fields that already hold a value, so a pending error is resolved against the
     * rules of the newly selected country. Only call this on user interaction: on page load it
     * would reset feedback rendered by the server.
     *
     * @param {NodeList|HTMLElement[]} fields
     * @private
     */
    _revalidateFilledFields(fields) {
        if (!fields) {
            return;
        }

        fields.forEach((field) => {
            if (field && field.value.trim().length > 0) {
                window.formValidation.validateField(field);
            }
        });
    }

    _updateStateSelect(states, stateRequired, countryStateId, stateDisplayed = true) {
        const countryStateSelect = this.scopeElement.querySelector(this.options.countryStateSelectSelector);
        const placeholder = countryStateSelect.querySelector(this.options.countryStatePlaceholderSelector);
        const stateSelectOptions = (stateRequired || stateDisplayed) ? states : [];

        this._removeStateOptions(countryStateSelect);
        this._addStateOptions(stateSelectOptions, countryStateId, countryStateSelect);

        if (stateRequired) {
            window.formValidation.setFieldRequired(countryStateSelect);
            placeholder.setAttribute('disabled', 'disabled');
        } else {
            window.formValidation.setFieldNotRequired(countryStateSelect);
            placeholder.removeAttribute('disabled');
        }
    }

    _removeStateOptions(countryStateSelect) {
        const optionSelector = `option:not(${this.options.countryStatePlaceholderSelector})`;
        let stateSelect = countryStateSelect;

        if (!countryStateSelect) {
            stateSelect = this.scopeElement.querySelector(this.options.countryStateSelectSelector);
        }

        stateSelect.querySelectorAll(optionSelector).forEach((option) => option.remove());
    }

    _addStateOptions(states, countryStateId, countryStateSelect) {
        let stateSelect = countryStateSelect;

        if (!countryStateSelect) {
            stateSelect = this.scopeElement.querySelector(this.options.countryStateSelectSelector);
        }

        if (states.length === 0) {
            stateSelect.parentNode.classList.add('d-none');
            stateSelect.setAttribute('disabled', 'disabled');
            return;
        }

        states.map(option => this._createStateOptionEl(option, countryStateId))
            .forEach((option) => {
                stateSelect.append(option);
            });
        stateSelect.parentNode.classList.remove('d-none');
        stateSelect.removeAttribute('disabled');
    }

    _createStateOptionEl(state, selectedStateId) {
        const option = document.createElement('option');

        option.setAttribute('value', state.id);
        option.innerText = state.translated.name;

        if (state.id === selectedStateId) {
            option.setAttribute('selected', 'selected');
        }

        return option;
    }

    _getFormFieldToggleInstance() {
        const toggleField = document.querySelector('[data-form-field-toggle-target=".js-form-field-toggle-shipping-address"]');
        if (!toggleField) {
            return;
        }

        this._formFieldToggleInstance = window.PluginManager.getPluginInstanceFromElement(toggleField, 'FormFieldToggle');
    }

    _onFormFieldToggleChange(event) {
        this._differentShippingCheckbox = event.target.checked;

        const scopeElementSelector = this._differentShippingCheckbox ? '.register-shipping' : '.register-billing';
        const scopeElement = document.querySelector(scopeElementSelector);

        const countrySelect = scopeElement.querySelector(this.options.countrySelectSelector);
        const countrySelectCurrentOption = countrySelect.options[countrySelect.selectedIndex];

        const vatIdRequired = !!countrySelectCurrentOption.getAttribute(this.options.vatIdRequired);
        const vatIdPattern = countrySelectCurrentOption.getAttribute(this.options.vatIdPattern);
        const checkVatIdPattern = countrySelectCurrentOption.getAttribute(this.options.checkVatIdPattern) === '1';
        const vatIdInput = document.querySelector(this.options.vatIdFieldInput);

        if (!vatIdInput) {
            return;
        }

        this._updateVatIdField(vatIdInput, vatIdRequired, vatIdPattern, checkVatIdPattern);
        this._revalidateVatIdField(vatIdInput);
    }

    _getStateDisplayed(countrySelectOption, stateRequired) {
        if (stateRequired) {
            return true;
        }

        if (!countrySelectOption.hasAttribute(this.options.stateDisplayed)) {
            return true;
        }

        const stateDisplayed = countrySelectOption.getAttribute(this.options.stateDisplayed);

        return stateDisplayed !== '' && stateDisplayed !== '0' && stateDisplayed !== 'false';
    }
}
