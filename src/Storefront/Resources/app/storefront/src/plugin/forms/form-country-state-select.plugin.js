import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';
import HttpClient from 'src/service/http-client.service';
import Feature from 'src/helper/feature.helper';

export default class CountryStateSelectPlugin extends Plugin {

    static options = {
        countrySelectSelector: '.country-select',
        initialCountryAttribute: 'initial-country-id',
        countryStateSelectSelector: '.country-state-select',
        initialCountryStateAttribute: 'initial-country-state-id',
        countryStatePlaceholderSelector: '[data-placeholder-option="true"]',
        vatIdFieldInput: '#vatIds',
        vatIdRequired: 'vat-id-required',
    };

    init() {
        this.initClient();
        this.initSelects();
    }

    initClient() {
        this._client = new HttpClient();
    }

    initSelects() {
        const { countrySelectSelector, countryStateSelectSelector, initialCountryAttribute, initialCountryStateAttribute } = CountryStateSelectPlugin.options;
        const countrySelect = DomAccess.querySelector(this.el, countrySelectSelector);
        const countryStateSelect = DomAccess.querySelector(this.el, countryStateSelectSelector);
        const initialCountryId = DomAccess.getDataAttribute(countrySelect, initialCountryAttribute);
        const initialCountryStateId = DomAccess.getDataAttribute(countryStateSelect, initialCountryStateAttribute);

        countrySelect.addEventListener('change', this.onChangeCountry.bind(this));

        if (initialCountryId) {
            this.requestStateData(initialCountryId, initialCountryStateId);
        }
    }

    onChangeCountry(event) {
        const countryId = event.target.value;

        this.requestStateData(countryId);
        if (Feature.isActive('FEATURE_NEXT_14114')) {
            const countrySelect = event.target.options[event.target.selectedIndex];
            const vatIdRequired = DomAccess.getDataAttribute(countrySelect, this.options.vatIdRequired);
            const vatIdInput = document.querySelector(this.options.vatIdFieldInput);

            if (vatIdInput) {
                this._updateRequiredVatId(vatIdInput, vatIdRequired);
            }
        }
    }

    requestStateData(countryId, countryStateId = null) {
        const payload = JSON.stringify({ countryId });

        this._client.post(
            window.router['frontend.country.country-data'],
            payload,
            (response) => {
                const responseData = JSON.parse(response);

                updateStateSelect(responseData, countryStateId, this.el, CountryStateSelectPlugin.options)
            }
        );
    }

    _updateRequiredVatId(vatIdFieldInput, vatIdRequired) {
        const label = vatIdFieldInput.parentNode.querySelector('label');

        if (vatIdRequired) {
            vatIdFieldInput.setAttribute('required', 'required');

            if (label.innerText.substr(-1, 1) !== '*') {
                label.innerText = `${label.innerText}*`;
            }

            return;
        }

        if (label.innerText.substr(-1, 1) === '*') {
            label.innerText = label.innerText.substr(0, label.innerText.length -1);
        }

        vatIdFieldInput.removeAttribute('required');
    }
}

function updateStateSelect({ stateRequired, states}, countryStateId, rootElement, options) {
    const { countryStateSelectSelector, countryStatePlaceholderSelector } = options;
    const countryStateSelect = DomAccess.querySelector(rootElement, countryStateSelectSelector);

    removeOldOptions(countryStateSelect, `option:not(${countryStatePlaceholderSelector})`);
    addNewStates(countryStateSelect, states, countryStateId);
    updateRequiredState(countryStateSelect, stateRequired, `option${countryStatePlaceholderSelector}`);
}

function removeOldOptions(el, optionQuery) {
    el.querySelectorAll(optionQuery).forEach((option) => option.remove());
}

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

function createOptionFromState(state, selectedStateId) {
    const option = document.createElement('option');

    option.setAttribute('value', state.id);
    option.innerText = state.translated.name;

    if (state.id === selectedStateId) {
        option.setAttribute('selected', 'selected');
    }

    return option;
}

function updateRequiredState(countryStateSelect, stateRequired, placeholderQuery) {
    const placeholder = countryStateSelect.querySelector(placeholderQuery);
    const label = countryStateSelect.parentNode.querySelector('label');

    if (stateRequired) {
        placeholder.setAttribute('disabled', 'disabled');
        countryStateSelect.setAttribute('required', 'required');

        if (label.innerText.substr(-1, 1) !== '*') {
            label.innerText = `${label.innerText}*`;
        }

        return;
    }

    if (label.innerText.substr(-1, 1) === '*') {
        label.innerText = label.innerText.substr(0, label.innerText.length -1);
    }

    placeholder.removeAttribute('disabled');
    countryStateSelect.removeAttribute('required');
}
