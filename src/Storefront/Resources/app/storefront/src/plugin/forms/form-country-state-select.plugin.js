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
        stateRequired: 'data-state-required',
        zipcodeRequired: 'data-zipcode-required',
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
        const vatIdInput = document.querySelector(this.options.vatIdFieldInput);
        const stateRequired = !!countrySelectCurrentOption.getAttribute(this.options.stateRequired);

        const zipcodeInputs = this.scopeElement.querySelectorAll(this.options.zipcodeFieldInput);
        const zipcodeRequired = !!countrySelectCurrentOption.getAttribute(this.options.zipcodeRequired);

        countrySelect.addEventListener('change', this.onChangeCountry.bind(this));

        if (!initialCountryId) {
            return;
        }
        this.requestStateData(initialCountryId, initialCountryStateId, stateRequired);

        if (zipcodeRequired) {
            this._updateZipcodeFields(zipcodeInputs, zipcodeRequired);
        }

        if (!vatIdInput) {
            return;
        }

        this._updateVatIdField(vatIdInput, vatIdRequired);
    }

    onChangeCountry(event) {
        const countryId = event.target.value;

        const countrySelect = event.target.options[event.target.selectedIndex];
        const stateRequired = !!countrySelect.getAttribute(this.options.stateRequired);
        this.requestStateData(countryId, null, stateRequired);
        const vatIdRequired = countrySelect.getAttribute(this.options.vatIdRequired);
        const vatIdInput = document.querySelector(this.options.vatIdFieldInput);

        const zipcodeInputs = this.scopeElement.querySelectorAll(this.options.zipcodeFieldInput);
        const zipcodeRequired = !!countrySelect.getAttribute(this.options.zipcodeRequired);

        this._updateZipcodeFields(zipcodeInputs, zipcodeRequired);

        if (vatIdInput) {
            this._updateVatIdField(vatIdInput, vatIdRequired);
        }
    }

    requestStateData(countryId, countryStateId = null, stateRequired = false) {
        const payload = JSON.stringify({ countryId });

        fetch(window.router['frontend.country.country-data'], {
            method: 'POST',
            body: payload,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
            },
        })
            .then(response => response.json())
            .then(content => this._updateStateSelect(content.states, stateRequired, countryStateId));
    }

    /**
     * Updates the required state of the VAT id field.
     *
     * @param {HTMLElement} vatIdFieldInput
     * @param {boolean} vatIdRequired
     * @private
     */
    _updateVatIdField(vatIdFieldInput, vatIdRequired) {
        if (this._differentShippingCheckbox && this.options.prefix === 'billingAddress') {
            return;
        }

        if (vatIdRequired) {
            window.formValidation.setFieldRequired(vatIdFieldInput);
        } else {
            window.formValidation.setFieldNotRequired(vatIdFieldInput);
        }
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

    _updateStateSelect(states, stateRequired, countryStateId) {
        const countryStateSelect = this.scopeElement.querySelector(this.options.countryStateSelectSelector);
        const placeholder = countryStateSelect.querySelector(this.options.countryStatePlaceholderSelector);

        this._removeStateOptions(countryStateSelect);
        this._addStateOptions(states, countryStateId, countryStateSelect);

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
        const vatIdInput = document.querySelector(this.options.vatIdFieldInput);

        if (!vatIdInput) {
            return;
        }

        this._updateVatIdField(vatIdInput, vatIdRequired);
    }
}
