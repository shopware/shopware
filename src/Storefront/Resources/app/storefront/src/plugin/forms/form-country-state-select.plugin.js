import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';
import HttpClient from 'src/service/http-client.service';

/**
 * @package content
 */
export default class CountryStateSelectPlugin extends Plugin {

    static options = {
        countrySelectSelector: '.country-select',
        initialCountryAttribute: 'initial-country-id',
        countryStateSelectSelector: '.country-state-select',
        initialCountryStateAttribute: 'initial-country-state-id',
        countryStatePlaceholderSelector: '[data-placeholder-option="true"]',
        vatIdFieldInput: '#vatIds',
        zipcodeFieldInput: '[data-input-name="zipcodeInput"]',
        vatIdRequired: 'vat-id-required',
        stateRequired: 'state-required',
        zipcodeRequired: 'zipcode-required',
        zipcodeLabel: '#zipcodeLabel',
        scopeElementSelector: null,
        prefix: null,
    };

    init() {
        this.initClient();
        this.initSelects();

        this._getFormFieldToggleInstance();

        if (this._formFieldToggleInstance) {
            this._formFieldToggleInstance.$emitter.subscribe('onChange', this._onFormFieldToggleChange.bind(this));
        }
    }

    initClient() {
        this._client = new HttpClient();
    }

    initSelects() {
        this.scopeElement = this.el;

        if (this.options.scopeElementSelector) {
            this.scopeElement = DomAccess.querySelector(document, this.options.scopeElementSelector);
        }

        const { countrySelectSelector, countryStateSelectSelector, initialCountryAttribute, initialCountryStateAttribute } = CountryStateSelectPlugin.options;
        const countrySelect = DomAccess.querySelector(this.scopeElement, countrySelectSelector);
        const countryStateSelect = DomAccess.querySelector(this.scopeElement, countryStateSelectSelector);
        const initialCountryId = DomAccess.getDataAttribute(countrySelect, initialCountryAttribute, false);
        const initialCountryStateId = DomAccess.getDataAttribute(countryStateSelect, initialCountryStateAttribute, false);
        const countrySelectCurrentOption = countrySelect.options[countrySelect.selectedIndex];
        const vatIdRequired = !!DomAccess.getDataAttribute(countrySelectCurrentOption, this.options.vatIdRequired, false);
        const vatIdInput = document.querySelector(this.options.vatIdFieldInput);
        const stateRequired = !!DomAccess.getDataAttribute(countrySelectCurrentOption, this.options.stateRequired, false);

        // @deprecated tag:v6.7.0 - Will be removed. The labels are handled by the global form validation method.
        const zipcodeLabels = DomAccess.querySelectorAll(this.scopeElement, this.options.zipcodeLabel, false);

        const zipcodeInputs = DomAccess.querySelectorAll(this.scopeElement, this.options.zipcodeFieldInput, false);
        const zipcodeRequired = !!DomAccess.getDataAttribute(countrySelectCurrentOption, this.options.zipcodeRequired, false);

        countrySelect.addEventListener('change', this.onChangeCountry.bind(this));

        if (!initialCountryId) {
            return;
        }
        this.requestStateData(initialCountryId, initialCountryStateId, stateRequired);

        if (zipcodeRequired) {
            if (window.Feature.isActive('ACCESSIBILITY_TWEAKS')) {
                this._updateZipcodeFields(zipcodeInputs, zipcodeRequired);
            } else {
                this._updateZipcodeRequired(zipcodeLabels, zipcodeInputs, zipcodeRequired);
            }
        }

        if (!vatIdInput) {
            return;
        }
        if (window.Feature.isActive('ACCESSIBILITY_TWEAKS')) {
            this._updateVatIdField(vatIdInput, vatIdRequired);
        } else {
            this._updateRequiredVatId(vatIdInput, vatIdRequired);
        }
    }

    onChangeCountry(event) {
        const countryId = event.target.value;

        const countrySelect = event.target.options[event.target.selectedIndex];
        const stateRequired = !!DomAccess.getDataAttribute(countrySelect, this.options.stateRequired);
        this.requestStateData(countryId, null, stateRequired);
        const vatIdRequired = DomAccess.getDataAttribute(countrySelect, this.options.vatIdRequired);
        const vatIdInput = document.querySelector(this.options.vatIdFieldInput);

        // @deprecated tag:v6.7.0 - Will be removed. The labels are handled by the global form validation method.
        const zipcodeLabels = DomAccess.querySelectorAll(this.scopeElement, this.options.zipcodeLabel, false);

        const zipcodeInputs = DomAccess.querySelectorAll(this.scopeElement, this.options.zipcodeFieldInput, false);
        const zipcodeRequired = !!DomAccess.getDataAttribute(countrySelect, this.options.zipcodeRequired, false);

        if (window.Feature.isActive('ACCESSIBILITY_TWEAKS')) {
            this._updateZipcodeFields(zipcodeInputs, zipcodeRequired);
        } else {
            this._updateZipcodeRequired(zipcodeLabels, zipcodeInputs, zipcodeRequired);
        }

        if (vatIdInput) {
            if (window.Feature.isActive('ACCESSIBILITY_TWEAKS')) {
                this._updateVatIdField(vatIdInput, vatIdRequired);
            } else {
                this._updateRequiredVatId(vatIdInput, vatIdRequired);
            }
        }
    }

    requestStateData(countryId, countryStateId = null, stateRequired = false) {
        const payload = JSON.stringify({ countryId });

        this._client.post(
            window.router['frontend.country.country-data'],
            payload,
            (response) => {
                if (window.Feature.isActive('ACCESSIBILITY_TWEAKS')) {
                    const responseData = JSON.parse(response);
                    this._updateStateSelect(responseData.states, stateRequired, countryStateId);
                } else {
                    let responseData = JSON.parse(response);
                    responseData = {...responseData, ...{ stateRequired }};
                    updateStateSelect(responseData, countryStateId, this.el, CountryStateSelectPlugin.options);
                }
            }
        );
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
     * @deprecated tag:v6.7.0 - Use _updateVatIdField instead.
     */
    _updateRequiredVatId(vatIdFieldInput, vatIdRequired) {
        if (this._differentShippingCheckbox && this.options.prefix === 'billingAddress') {
            return;
        }

        const label = vatIdFieldInput.parentNode.querySelector('label');

        if (vatIdRequired) {
            vatIdFieldInput.setAttribute('required', 'required');

            if (label?.textContent && label.textContent.substr(-1, 1) !== '*') {
                label.textContent = `${label.textContent}*`;
            }

            return;
        }

        if (label?.textContent && label.textContent.substr(-1, 1) === '*') {
            label.textContent = label.textContent.substr(0, label.textContent.length -1);
        }

        vatIdFieldInput.removeAttribute('required');
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
     * @deprecated tag:v6.7.0 - Use _updateZipcodeFields instead.
     *
     * @param labels
     * @param inputs
     * @param required
     * @private
     */
    _updateZipcodeRequired(labels, inputs, required) {
        if (!labels || !inputs) {
            return;
        }

        labels.forEach(label => {
            label.className = required ? '' : 'd-none';
        });

        inputs.forEach(input => {
            if (required) {
                input.setAttribute('required', 'required');
            } else {
                input.removeAttribute('required');
            }
        });
    }

    _updateStateSelect(states, stateRequired, countryStateId) {
        const countryStateSelect = DomAccess.querySelector(this.scopeElement, this.options.countryStateSelectSelector);
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
            stateSelect = DomAccess.querySelector(this.scopeElement, this.options.countryStateSelectSelector);
        }

        stateSelect.querySelectorAll(optionSelector).forEach((option) => option.remove());
    }

    _addStateOptions(states, countryStateId, countryStateSelect) {
        let stateSelect = countryStateSelect;

        if (!countryStateSelect) {
            stateSelect = DomAccess.querySelector(this.scopeElement, this.options.countryStateSelectSelector);
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
        const toggleField = DomAccess.querySelector(document, '[data-form-field-toggle-target=".js-form-field-toggle-shipping-address"]', false);
        if (!toggleField) {
            return;
        }

        this._formFieldToggleInstance = window.PluginManager.getPluginInstanceFromElement(toggleField, 'FormFieldToggle');
    }

    _onFormFieldToggleChange(event) {
        this._differentShippingCheckbox = event.target.checked;

        const scopeElementSelector = this._differentShippingCheckbox ? '.register-shipping' : '.register-billing';
        const scopeElement = DomAccess.querySelector(document, scopeElementSelector);

        const countrySelect = DomAccess.querySelector(scopeElement, this.options.countrySelectSelector);
        const countrySelectCurrentOption = countrySelect.options[countrySelect.selectedIndex];

        const vatIdRequired = !!DomAccess.getDataAttribute(countrySelectCurrentOption, this.options.vatIdRequired, false);
        const vatIdInput = document.querySelector(this.options.vatIdFieldInput);

        if (!vatIdInput) {
            return;
        }

        if (window.Feature.isActive('ACCESSIBILITY_TWEAKS')) {
            this._updateVatIdField(vatIdInput, vatIdRequired);
        } else {
            this._updateRequiredVatId(vatIdInput, vatIdRequired);
        }
    }
}

/**
 * @deprecated tag:v6.7.0 - Use _updateStateSelect from the actual plugin class.
 */
function updateStateSelect({ stateRequired, states}, countryStateId, rootElement, options) {
    const { countryStateSelectSelector, countryStatePlaceholderSelector } = options;
    const countryStateSelect = DomAccess.querySelector(rootElement, countryStateSelectSelector);

    removeOldOptions(countryStateSelect, `option:not(${countryStatePlaceholderSelector})`);
    addNewStates(countryStateSelect, states, countryStateId);
    updateRequiredState(countryStateSelect, stateRequired, `option${countryStatePlaceholderSelector}`);
}

/**
 * @deprecated tag:v6.7.0 - Use _removeStateOptions from the actual plugin class.
 */
function removeOldOptions(el, optionQuery) {
    el.querySelectorAll(optionQuery).forEach((option) => option.remove());
}

/**
 * @deprecated tag:v6.7.0 - Use _addStateOptions from the actual plugin class.
 */
function addNewStates(selectEl, states, selectedStateId) {
    if (states.length === 0) {
        selectEl.parentNode.classList.add('d-none');
        selectEl.setAttribute('disabled', 'disabled');
        return;
    }

    states.map(option => createOptionFromState(option, selectedStateId))
        .forEach((option) => {
            selectEl.append(option);
        });
    selectEl.parentNode.classList.remove('d-none');
    selectEl.removeAttribute('disabled');
}

/**
 * @deprecated tag:v6.7.0 - Use _createStateOptionEl from the actual plugin class.
 */
function createOptionFromState(state, selectedStateId) {
    const option = document.createElement('option');

    option.setAttribute('value', state.id);
    option.innerText = state.translated.name;

    if (state.id === selectedStateId) {
        option.setAttribute('selected', 'selected');
    }

    return option;
}

/**
 * @deprecated tag:v6.7.0 - Removed with not replacement. Required state is handled in _updateStateSelect of the actual plugin class.
 */
function updateRequiredState(countryStateSelect, stateRequired, placeholderQuery) {
    const placeholder = countryStateSelect.querySelector(placeholderQuery);
    const label = countryStateSelect.parentNode.querySelector('label');

    if (stateRequired) {
        placeholder.setAttribute('disabled', 'disabled');
        countryStateSelect.setAttribute('required', 'required');

        if (label?.textContent && label.textContent.substr(-1, 1) !== '*') {
            label.textContent = `${label.textContent.trim()}*`;
        }

        return;
    }

    if (label?.textContent && label.textContent.substr(-1, 1) === '*') {
        label.textContent = label.textContent.substr(0, label.textContent.length -1);
    }

    placeholder.removeAttribute('disabled');
    countryStateSelect.removeAttribute('required');
}
