import FormCountryStateSelectPlugin from 'src/plugin/forms/form-country-state-select.plugin';

/**
 * @package content
 */
describe('Form country state select plugin', () => {
    let template = `
        <form id="registerForm" action="/register" method="post">

            <div class="form-group col-md-6">
                <label class="form-label" for="vatIds">VAT Reg.No.</label>
                <input type="text" name="vatIds[]" id="vatIds" class="form-name">
            </div>

            <select class="country-select" data-initial-country-id="555nase">
                <option data-vat-id-required="1" data-state-required="0">Netherlands</option>
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
            <form id="registerForm" class="register-shipping" action="/register" method="post">

                <div class="form-group col-md-6">
                    <label class="form-label" for="vatIds">VAT Reg.No.</label>
                    <input type="text" name="vatIds[]" id="vatIds" class="form-name">
                </div>

                <select class="country-select" data-initial-country-id="">
                    <option disabled="disabled" value="" selected="selected">Select country...</option>
                    <option data-vat-id-required="1" data-state-required="0">Netherlands</option>
                    <option data-vat-id-required="0" data-state-required="0">Germany</option>
                </select>
                <select class="country-state-select" data-initial-country-state-id="">
                    <option>Select state..</option>
                </select>
            </form>
        `;

        document.body.innerHTML = template;

        createPlugin({
            scopeElementSelector: '.register-shipping',
        });

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
                    <option value="1" selected="selected" data-zipcode-required="0" data-vat-id-required="1" data-state-required="0">Netherlands</option>
                    <option value="2" data-vat-id-required="0" data-zipcode-required="0" data-state-required="0">Germany</option>
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

    it('should set zipcode field to required when a country with required one setting is selected', () => {
        template = `
            <form id="registerForm" class="register-shipping" action="/register" method="post">
               <label class="form-label" for="addressZipcode">
                   Postal code<span id="zipcodeLabel" class="d-none">*</span>
               </label>

               <input type="text" class="form-control" id="addressZipcode" value="" data-input-name="zipcodeInput">

               <select class="country-select" data-initial-country-id="">
                  <option disabled="disabled" value="">Select country...</option>
                  <option value="1" data-vat-id-required="0" data-zipcode-required="1" data-state-required="1" selected="selected" data-placeholder-option="true">Germany</option>
               </select>

               <select class="country-state-select" data-initial-country-state-id="">
                 <option>Select state..</option>
               </select>
            </form>
        `;

        document.body.innerHTML = template;

        createPlugin({
            scopeElementSelector: '.register-shipping',
        });

        expect(document.querySelector('[data-input-name="zipcodeInput"]').hasAttribute('required')).toBe(false);
        expect(document.querySelector('#zipcodeLabel').classList.contains('d-none')).toBe(true);

        // Perform selection
        document.querySelector('.country-select').dispatchEvent(new Event('change'));

        expect(document.querySelector('[data-input-name="zipcodeInput"]').hasAttribute('required')).toBe(true);
        expect(document.querySelector('#zipcodeLabel').classList.contains('d-none')).toBe(false);
    });

    it('should remove space around at country state label name when the state required', () => {
        template = `
            <form id="registerForm" action="/register" method="post" data-country-state-select="true">
                <div class="register-shipping">
                    <div class="row g-2">
                        <div class="form-group">
                            <label class="form-label">Land*</label>
                            <select class="country-select form-select" required="required" data-initial-country-id="31e1ac8809c744c38c4d99bfe9a50aa8">
                                <option selected="selected" value="31e1ac8809c744c38c4d99bfe9a50aa8" data-zipcode-required="" data-vat-id-required="" data-state-required="">Deutschland</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="shippingAddressAddressCountryState"> Bundesland </label>
                            <select class="country-state-select form-select" data-initial-country-state-id="">
                                <option value="" selected="selected" data-placeholder-option="true">Bundesland auswählen ...</option>
                                <option value="0490081418be4255b87731afc953e901">Hamburg</option>
                            </select>
                        </div>
                    </div>
                </div>
            </form>
        `;

        document.body.innerHTML = template;

        const plugin = createPlugin();

        plugin._client.post = jest.fn((url, _, callback) => {
            const response = {
                countryId: '31e1ac8809c744c38c4d99bfe9a50aa8',
                states: [{ id: '0490081418be4255b87731afc953e901', translated: { name: 'Hamburg' }}],
            };

            callback(JSON.stringify(response));
        });

        expect(document.querySelector('[for="shippingAddressAddressCountryState"]').textContent).toBe(' Bundesland ');

        plugin.requestStateData('31e1ac8809c744c38c4d99bfe9a50aa8', '0490081418be4255b87731afc953e901', true);

        expect(document.querySelector('[for="shippingAddressAddressCountryState"]').textContent).toBe('Bundesland*');
    });
});
