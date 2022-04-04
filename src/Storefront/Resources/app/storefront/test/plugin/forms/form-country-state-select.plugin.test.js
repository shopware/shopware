/**
 * @jest-environment jsdom
 */

import FormCountryStateSelectPlugin from 'src/plugin/forms/form-country-state-select.plugin';

describe('Form country state select plugin', () => {
    let template = `
        <form id="registerForm" action="/register" method="post">

            <div class="form-group col-md-6">
                <label class="form-label" for="vatIds">VAT Reg.No.</label>
                <input type="text" name="vatIds[]" id="vatIds" class="form-name">
            </div>

            <select class="country-select" data-initial-country-id="555nase">
                <option data-vat-id-required="1">Netherlands</option>
            </select>
            <select class="country-state-select" data-initial-country-state-id="">
                <option>Select state..</option>
            </select>
        </form>
    `;

    function createPlugin(pluginOptions = {}) {
        const mockElement = document.querySelector('#registerForm');
        return new FormCountryStateSelectPlugin(mockElement, pluginOptions);
    }

    beforeEach(() => {
        window.PluginManager = {
            getPluginInstancesFromElement: () => {
                return new Map();
            },
            getPlugin: () => {
                return {
                    get: () => [],
                };
            },
        };

        window.router = [];

        window.csrf = {
            enabled: false,
        };

        document.body.innerHTML = template;
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('should instantiate plugin', () => {
        const formCountryStateSelectPlugin = createPlugin();

        expect(formCountryStateSelectPlugin instanceof FormCountryStateSelectPlugin).toBe(true);
    });

    it('should set vatIds field to required directly when an initial country is available which also has vatId required setting', () => {
        createPlugin();

        // Ensure vatIds is has required attr and label includes required symbol "*"
        expect(document.querySelector('#vatIds').hasAttribute('required')).toBe(true);
        expect(document.querySelector('label[for="vatIds"]').textContent).toBe('VAT Reg.No.*');
    });

    it('should not set vatIds field to required directly when there is no initial country', () => {
        template = `
            <form id="registerForm" action="/register" method="post">

                <div class="form-group col-md-6">
                    <label class="form-label" for="vatIds">VAT Reg.No.</label>
                    <input type="text" name="vatIds[]" id="vatIds" class="form-name">
                </div>

                <select class="country-select" data-initial-country-id="">
                    <option disabled="disabled" value="" selected="selected">Select country...</option>
                    <option data-vat-id-required="1">Netherlands</option>
                    <option data-vat-id-required="0">Germany</option>
                </select>
                <select class="country-state-select" data-initial-country-state-id="">
                    <option>Select state..</option>
                </select>
            </form>
        `;

        document.body.innerHTML = template;

        createPlugin();

        // Ensure vatIds is not required and label includes no required symbol "*"
        expect(document.querySelector('#vatIds').hasAttribute('required')).toBe(false);
        expect(document.querySelector('label[for="vatIds"]').textContent).toBe('VAT Reg.No.');
    });

    it('should set vatIds field to required when a country with vatId required setting is selected', () => {
        template = `
            <form id="registerForm" action="/register" method="post">

                <div class="form-group col-md-6">
                    <label class="form-label" for="vatIds">VAT Reg.No.</label>
                    <input type="text" name="vatIds[]" id="vatIds" class="form-name">
                </div>

                <select class="country-select" data-initial-country-id="">
                    <option disabled="disabled" value="">Select country...</option>
                    <option value="1" selected="selected" data-vat-id-required="1">Netherlands</option>
                    <option value="2" data-vat-id-required="0">Germany</option>
                </select>
                <select class="country-state-select" data-initial-country-state-id="">
                    <option>Select state..</option>
                </select>
            </form>
        `;

        document.body.innerHTML = template;

        createPlugin();

        // Ensure vatIds is not required and label includes no required symbol "*" at the beginning.
        expect(document.querySelector('#vatIds').hasAttribute('required')).toBe(false);
        expect(document.querySelector('label[for="vatIds"]').textContent).toBe('VAT Reg.No.');

        // Perform selection
        document.querySelector('.country-select').dispatchEvent(new Event('change'));

        // Ensure vatIds is required after selecting a country with vatId required setting.
        expect(document.querySelector('#vatIds').hasAttribute('required')).toBe(true);
        expect(document.querySelector('label[for="vatIds"]').textContent).toBe('VAT Reg.No.*');
    });
});
