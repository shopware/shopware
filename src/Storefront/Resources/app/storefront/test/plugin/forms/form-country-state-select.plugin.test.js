import FormCountryStateSelectPlugin from 'src/plugin/forms/form-country-state-select.plugin';
import FormFieldTogglePlugin from 'src/plugin/forms/form-field-toggle.plugin';
import FormValidation from 'src/helper/form-validation.helper';

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
                <option data-placeholder-option="true">Select state..</option>
            </select>
        </form>
    `;

    function createPlugin(pluginOptions = {}) {
        const mockElement = document.querySelector('#registerForm');
        return new FormCountryStateSelectPlugin(mockElement, pluginOptions);
    }

    beforeEach(() => {
        document.body.innerHTML = template;

        window.validationMessages = {
            required: 'Input should not be empty.',
            email: 'Invalid email address.',
            confirmation: 'Confirmation field does not match.',
            minLength: 'Input is too short.',
        };

        global.fetch = jest.fn(() =>
            Promise.resolve({
                json: () => Promise.resolve({
                    countryId: '31e1ac8809c744c38c4d99bfe9a50aa8',
                    states: [{ id: '0490081418be4255b87731afc953e901', translated: { name: 'Hamburg' }}],
                }),
            }),
        );

        window.formValidation = new FormValidation();
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('should instantiate plugin', async () => {
        const formCountryStateSelectPlugin = createPlugin();
        await new Promise(process.nextTick);

        expect(formCountryStateSelectPlugin instanceof FormCountryStateSelectPlugin).toBe(true);
    });

    it('should set vatIds field to required directly when an initial country is available which also has vatId required setting', () => {
        createPlugin();

        const vatIdField = document.querySelector('#vatIds');
        const vatIdFieldLabel = document.querySelector('label[for="vatIds"]');

        // Ensure vatIds is has required attr and label includes required symbol "*"
        expect(vatIdField.hasAttribute('aria-required')).toBe(true);
        expect(vatIdFieldLabel.innerHTML.includes('form-required-label')).toBe(true);
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

        const vatIdField = document.querySelector('#vatIds');
        const vatIdFieldLabel = document.querySelector('label[for="vatIds"]');

        // Ensure vatIds is not required
        expect(vatIdField.hasAttribute('aria-required')).toBe(false);
        expect(vatIdFieldLabel.innerHTML.includes('form-required-label')).toBe(false);
    });

    it('should set vatIds field to required when a country with vatId required setting is selected', () => {
        template = `
            <form id="registerForm" action="/register" method="post">

                <div class="form-group col-md-6">
                    <label class="form-label" for="vatIds">VAT Registration Number</label>
                    <input type="text" name="vatIds[]" id="vatIds" class="form-name">
                </div>

                <select class="country-select" data-initial-country-id="">
                    <option disabled="disabled" value="">Select country...</option>
                    <option value="1" selected="selected" data-zipcode-required="0" data-vat-id-required="1" data-state-required="0">Netherlands</option>
                    <option value="2" data-vat-id-required="0" data-zipcode-required="0" data-state-required="0">Germany</option>
                </select>
                <select class="country-state-select" data-initial-country-state-id="">
                    <option data-placeholder-option="true">Select state..</option>
                </select>
            </form>
        `;

        document.body.innerHTML = template;

        createPlugin();

        const vatIdField = document.querySelector('#vatIds');
        const vatIdFieldLabel = document.querySelector('label[for="vatIds"]');

        // Ensure vatIds is not required
        expect(vatIdField.hasAttribute('aria-required')).toBe(false);
        expect(vatIdFieldLabel.innerHTML.includes('form-required-label')).toBe(false);

        // Perform selection
        document.querySelector('.country-select').dispatchEvent(new Event('change'));

        // Ensure vatIds is required after selecting a country with vatId required setting.
        expect(vatIdField.hasAttribute('aria-required')).toBe(true);
        expect(vatIdFieldLabel.innerHTML.includes('form-required-label')).toBe(true);
    });

    it('should set zipcode field to required when a country with required one setting is selected', () => {
        template = `
            <form id="registerForm" class="register-shipping" action="/register" method="post">
                <label class="form-label" for="addressZipCode">
                    Postal code
                </label>

                <input type="text" class="form-control" id="addressZipCode" value="" data-input-name="zipcodeInput">

                <label class="form-label" for="alternativeZipCode">
                     Postal code
                </label>

                <input type="text" class="form-control" id="alternativeZipCode" value="" data-input-name="zipcodeInput">

                <select class="country-select" data-initial-country-id="">
                    <option disabled="disabled" value="">Select country...</option>
                    <option value="1" data-vat-id-required="0" data-zipcode-required="1" data-state-required="1" selected="selected" data-placeholder-option="true">Germany</option>
                </select>

                <select class="country-state-select" data-initial-country-state-id="">
                    <option data-placeholder-option="true">Select state..</option>
                </select>
            </form>
        `;

        document.body.innerHTML = template;

        const plugin = createPlugin({
            scopeElementSelector: '.register-shipping',
        });

        const updateZipCodeSpy = jest.spyOn(plugin, '_updateZipcodeFields');

        const labels = document.querySelectorAll('.form-label');
        const inputs = document.querySelectorAll('[data-input-name="zipcodeInput"]');

        labels.forEach(label => expect(label.innerHTML.includes('form-required-label')).toBe(false));
        inputs.forEach(input => expect(input.hasAttribute('aria-required')).toBe(false));

        // Perform selection
        document.querySelector('.country-select').dispatchEvent(new Event('change'));

        expect(updateZipCodeSpy).toHaveBeenCalled();

        labels.forEach(label => expect(label.innerHTML.includes('form-required-label')).toBe(true));
        inputs.forEach(input => expect(input.hasAttribute('aria-required')).toBe(true));
    });

    it('should initialize form field toggle instance and subscribe to onChange event', async () => {
        template = `
            <form id="registerForm" action="/register" method="post" data-country-state-select="true">
                <input type="checkbox"
                     data-form-field-toggle="true"
                     data-form-field-toggle-target=".js-form-field-toggle-shipping-address"
                     data-form-field-toggle-value="true">

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
                            <select class="country-state-select form-select" data-initial-country-state-id="" id="shippingAddressAddressCountryState">
                                <option value="" selected="selected" data-placeholder-option="true">Bundesland auswählen ...</option>
                                <option value="0490081418be4255b87731afc953e901">Hamburg</option>
                            </select>
                        </div>
                    </div>
                </div>
            </form>
        `;

        const mockToggleInstance = {
            $emitter: {
                subscribe: jest.fn(),
            },
        };

        window.PluginManager.getPluginInstanceFromElement = jest.fn().mockReturnValue(mockToggleInstance);

        document.body.innerHTML = template;

        const plugin = createPlugin();
        await new Promise(process.nextTick);

        plugin._getFormFieldToggleInstance();

        expect(plugin._formFieldToggleInstance).toBe(mockToggleInstance);
        expect(mockToggleInstance.$emitter.subscribe).toHaveBeenCalledWith('onChange', expect.any(Function));
    });

    it('should not subscribe to onChange event if form field toggle instance is not found', async () => {
        template = `
            <form id="registerForm" action="/register" method="post" data-country-state-select="true">
                <input type="checkbox"
                     data-form-field-toggle="true"
                     data-form-field-toggle-target=".js-form-field-toggle-shipping-address"
                     data-form-field-toggle-value="true">

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
                            <select class="country-state-select form-select" data-initial-country-state-id="" id="shippingAddressAddressCountryState">
                                <option value="" selected="selected" data-placeholder-option="true">Bundesland auswählen ...</option>
                                <option value="0490081418be4255b87731afc953e901">Hamburg</option>
                            </select>
                        </div>
                    </div>
                </div>
            </form>
        `;

        window.PluginManager.getPluginInstanceFromElement = jest.fn().mockReturnValue(null);

        document.body.innerHTML = template;
        const plugin = createPlugin();
        await new Promise(process.nextTick);

        plugin._getFormFieldToggleInstance();

        expect(plugin._formFieldToggleInstance).toBeNull();
    });

    it('should update country state label when state required', async () => {
        const mockElement = `
             <input type="checkbox"
                     data-form-field-toggle="true"
                     data-form-field-toggle-target=".js-form-field-toggle-shipping-address"
                     data-form-field-toggle-value="true">
        `;

        template = `
            <form id="registerForm" action="/register" method="post" data-country-state-select="true">
                <div class="register-shipping">
                    <div class="row g-2">
                        <div class="form-group">
                            <label class="form-label">Land*</label>
                            <select class="country-select form-select" required="required" data-initial-country-id="31e1ac8809c744c38c4d99bfe9a50aa8">
                                <option selected="selected" value="31e1ac8809c744c38c4d99bfe9a50aa8" data-zipcode-required="" data-vat-id-required="" data-state-required="1">Deutschland</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="shippingAddressAddressCountryState">Bundesland</label>
                            <select class="country-state-select form-select" id="shippingAddressAddressCountryState" data-initial-country-state-id="">
                                <option value="" selected="selected" data-placeholder-option="true">Bundesland auswählen ...</option>
                                <option value="0490081418be4255b87731afc953e901">Hamburg</option>
                            </select>
                        </div>
                    </div>
                </div>
            </form>
        `;

        document.body.innerHTML = template;

        window.PluginManager.getPluginInstanceFromElement = () => {
            return new FormFieldTogglePlugin(mockElement);
        };

        global.fetch = jest.fn(() =>
            Promise.resolve({
                json: () => Promise.resolve({
                    countryId: '31e1ac8809c744c38c4d99bfe9a50aa8',
                    states: [{ id: '0490081418be4255b87731afc953e901', translated: { name: 'Hamburg' }}],
                }),
            }),
        );

        const plugin = createPlugin();
        await new Promise(process.nextTick);

        const stateLabel = document.querySelector('[for="shippingAddressAddressCountryState"]');

        expect(stateLabel.textContent).toBe('Bundesland *');

        plugin.requestStateData('31e1ac8809c744c38c4d99bfe9a50aa8', '0490081418be4255b87731afc953e901', true);

        expect(stateLabel.innerHTML.includes('form-required-label')).toBe(true);
    });

    it('should hide country state select when country disables state display', async () => {
        template = `
            <form id="registerForm" action="/register" method="post" data-country-state-select="true">
                <select class="country-select" data-initial-country-id="DE">
                    <option selected="selected" value="DE" data-display-state-in-registration="">Germany</option>
                </select>

                <div class="form-group">
                    <label class="form-label" for="addressCountryState">State</label>
                    <select class="country-state-select" id="addressCountryState" data-initial-country-state-id="">
                        <option data-placeholder-option="true">Select state..</option>
                    </select>
                </div>
            </form>
        `;

        document.body.innerHTML = template;

        createPlugin();
        await new Promise(process.nextTick);

        const stateSelect = document.querySelector('.country-state-select');

        expect(stateSelect.parentNode.classList.contains('d-none')).toBe(true);
        expect(stateSelect.hasAttribute('disabled')).toBe(true);
        expect(stateSelect.querySelectorAll('option:not([data-placeholder-option])')).toHaveLength(0);
        expect(stateSelect.hasAttribute('aria-required')).toBe(false);
    });

    it('should show country state select when state is required even if country disables state display', async () => {
        template = `
            <form id="registerForm" action="/register" method="post" data-country-state-select="true">
                <select class="country-select" data-initial-country-id="DE">
                    <option selected="selected" value="DE" data-state-required="1" data-display-state-in-registration="">Germany</option>
                </select>

                <div class="form-group d-none">
                    <label class="form-label" for="addressCountryState">State</label>
                    <select class="country-state-select" id="addressCountryState" data-initial-country-state-id="">
                        <option data-placeholder-option="true">Select state..</option>
                    </select>
                </div>
            </form>
        `;

        document.body.innerHTML = template;

        createPlugin();
        await new Promise(process.nextTick);

        const stateSelect = document.querySelector('.country-state-select');
        const placeholder = stateSelect.querySelector('[data-placeholder-option]');

        expect(stateSelect.parentNode.classList.contains('d-none')).toBe(false);
        expect(stateSelect.hasAttribute('disabled')).toBe(false);
        expect(stateSelect.querySelectorAll('option:not([data-placeholder-option])')).toHaveLength(1);
        expect(stateSelect.hasAttribute('aria-required')).toBe(true);
        expect(placeholder.hasAttribute('disabled')).toBe(true);
    });

    it('should update VAT ID field to required when different shipping address is selected', async () => {
        template = `
            <form id="registerForm" class="register-shipping" action="/register" method="post">

                <div class="form-group col-md-6">
                    <label class="form-label" for="vatIds">VAT Reg.No.</label>
                    <input type="text" name="vatIds[]" id="vatIds" class="form-name">
                </div>

                <select class="country-select" data-initial-country-id="555nase">
                    <option data-vat-id-required="1" data-state-required="0">Netherlands</option>
                </select>
                <select class="country-state-select" data-initial-country-state-id="">
                    <option data-placeholder-option="true">Select state..</option>
                </select>
            </form>
        `;

        document.body.innerHTML = template;

        const plugin = createPlugin();
        await new Promise(process.nextTick);

        const event = { target: { checked: true } };

        plugin._onFormFieldToggleChange(event);

        const vatIdInput = document.querySelector(plugin.options.vatIdFieldInput);
        const vatIdFieldLabel = document.querySelector('label[for="vatIds"]');

        expect(vatIdInput.hasAttribute('aria-required')).toBe(true);
        expect(vatIdFieldLabel.innerHTML.includes('form-required-label')).toBe(true);
    });

    it('should update VAT ID field to not required when different shipping address is not selected', async() => {
        template = `
            <form id="registerForm" class="register-billing" action="/register" method="post">

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

        const plugin = createPlugin();
        await new Promise(process.nextTick);

        const event = { target: { checked: false } };

        plugin._onFormFieldToggleChange(event);

        const vatIdInput = document.querySelector(plugin.options.vatIdFieldInput);
        const vatIdFieldLabel = document.querySelector('label[for="vatIds"]');

        expect(vatIdInput.hasAttribute('aria-required')).toBe(false);
        expect(vatIdFieldLabel.innerHTML.includes('form-required-label')).toBe(false);
    });

    it('should not update VAT ID field when different shipping address is selected and prefix is billingAddress', async () => {
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

        const plugin = createPlugin({ prefix: 'billingAddress' });
        await new Promise(process.nextTick);

        const event = { target: { checked: true } };

        plugin._differentShippingCheckbox = true;
        plugin._onFormFieldToggleChange(event);

        const vatIdInput = document.querySelector(plugin.options.vatIdFieldInput);
        const vatIdFieldLabel = document.querySelector('label[for="vatIds"]');

        expect(vatIdInput.hasAttribute('aria-required')).toBe(false);
        expect(vatIdFieldLabel.innerHTML.includes('form-required-label')).toBe(false);
    });

    it('should call update field/select methods with booleans for required parameters', async () => {
        template = `
            <form id="registerForm" class="register-shipping" action="/register" method="post">

                <div class="form-group col-md-6">
                    <label class="form-label" for="vatIds">VAT Reg.No.</label>
                    <input type="text" name="vatIds[]" id="vatIds" class="form-name">
                </div>

                <div class="form-group col-md-6">
                    <label class="form-label" for="addressCountry">Country</label>
                    <select class="country-select" id="addressCountry" data-initial-country-id="NE">
                        <option data-placeholder-option="true" disabled="disabled" value="">Select country...</option>
                        <option selected="selected" value="NE" data-vat-id-required="1" data-state-required="1" data-zipcode-required="1">Netherlands</option>
                        <option value="DE" data-vat-id-required data-state-required data-zipcode-required>Germany</option>
                    </select>
                </div>

                <div class="form-group col-md-6">
                    <label class="form-label" for="addressCountryState">State</label>
                    <select class="country-state-select" id="addressCountryState" data-initial-country-state-id="">
                        <option data-placeholder-option="true">Select state..</option>
                    </select>
                </div>
            </form>
        `;

        document.body.innerHTML = template;

        const plugin = createPlugin();

        const updateStateSelectSpy = jest.spyOn(plugin, '_updateStateSelect');
        const updateZipcodeFieldsSpy = jest.spyOn(plugin, '_updateZipcodeFields');
        const updateVatIdFieldSpy = jest.spyOn(plugin, '_updateVatIdField');

        plugin.initSelects();

        await new Promise(process.nextTick);

        expect(updateStateSelectSpy).toHaveBeenCalledWith(expect.anything(), true, expect.anything(), true);
        expect(updateZipcodeFieldsSpy).toHaveBeenCalledWith(expect.anything(), true);
        expect(updateVatIdFieldSpy).toHaveBeenCalledWith(expect.anything(), true, null, false);

        updateStateSelectSpy.mockClear();
        updateZipcodeFieldsSpy.mockClear();
        updateVatIdFieldSpy.mockClear();

        const countrySelect = document.querySelector('.country-select');

        countrySelect.value = 'DE';
        countrySelect.dispatchEvent(new Event('change'));

        await new Promise(process.nextTick);

        expect(updateStateSelectSpy).toHaveBeenCalledWith(expect.anything(), false, null, true);
        expect(updateZipcodeFieldsSpy).toHaveBeenCalledWith(expect.anything(), false);
        expect(updateVatIdFieldSpy).toHaveBeenCalledWith(expect.anything(), false, null, false);

        updateVatIdFieldSpy.mockClear();

        plugin._onFormFieldToggleChange({ target: { checked: true } });

        expect(updateVatIdFieldSpy).toHaveBeenCalledWith(expect.anything(), false, null, false);
    });

    it('should set pattern attribute on vatIds field when initial country has checkVatIdPattern enabled', () => {
        template = `
            <form id="registerForm" action="/register" method="post">

                <div class="form-group col-md-6">
                    <label class="form-label" for="vatIds">VAT Reg.No.</label>
                    <input type="text" name="vatIds[]" id="vatIds" class="form-name">
                </div>

                <select class="country-select" data-initial-country-id="DE">
                    <option selected="selected" value="DE" data-vat-id-required="0" data-state-required="0"
                            data-vat-id-pattern="DE[0-9]{9}" data-check-vat-id-pattern="1">Germany</option>
                </select>
                <select class="country-state-select" data-initial-country-state-id="">
                    <option data-placeholder-option="true">Select state..</option>
                </select>
            </form>
        `;

        document.body.innerHTML = template;
        createPlugin();

        const vatIdField = document.querySelector('#vatIds');
        expect(vatIdField.getAttribute('pattern')).toBe('DE[0-9]{9}');
    });

    it('should not set pattern attribute on vatIds field when initial country has checkVatIdPattern disabled', () => {
        template = `
            <form id="registerForm" action="/register" method="post">

                <div class="form-group col-md-6">
                    <label class="form-label" for="vatIds">VAT Reg.No.</label>
                    <input type="text" name="vatIds[]" id="vatIds" class="form-name" pattern="OLD[0-9]">
                </div>

                <select class="country-select" data-initial-country-id="US">
                    <option selected="selected" value="US" data-vat-id-required="0" data-state-required="0"
                            data-vat-id-pattern="US[0-9]{9}" data-check-vat-id-pattern="0">USA</option>
                </select>
                <select class="country-state-select" data-initial-country-state-id="">
                    <option data-placeholder-option="true">Select state..</option>
                </select>
            </form>
        `;

        document.body.innerHTML = template;
        createPlugin();

        const vatIdField = document.querySelector('#vatIds');
        expect(vatIdField.hasAttribute('pattern')).toBe(false);
    });

    it('should set pattern attribute when switching to a country with checkVatIdPattern enabled', () => {
        template = `
            <form id="registerForm" action="/register" method="post">

                <div class="form-group col-md-6">
                    <label class="form-label" for="vatIds">VAT Reg.No.</label>
                    <input type="text" name="vatIds[]" id="vatIds" class="form-name">
                </div>

                <select class="country-select" data-initial-country-id="">
                    <option disabled="disabled" value="" selected="selected">Select country...</option>
                    <option value="DE" data-vat-id-required="0" data-state-required="0" data-zipcode-required="0"
                            data-vat-id-pattern="DE[0-9]{9}" data-check-vat-id-pattern="1">Germany</option>
                    <option value="US" data-vat-id-required="0" data-state-required="0" data-zipcode-required="0"
                            data-vat-id-pattern="US[0-9]{9}" data-check-vat-id-pattern="0">USA</option>
                </select>
                <select class="country-state-select" data-initial-country-state-id="">
                    <option data-placeholder-option="true">Select state..</option>
                </select>
            </form>
        `;

        document.body.innerHTML = template;
        createPlugin();

        const vatIdField = document.querySelector('#vatIds');
        const countrySelect = document.querySelector('.country-select');

        expect(vatIdField.hasAttribute('pattern')).toBe(false);

        countrySelect.value = 'DE';
        countrySelect.dispatchEvent(new Event('change'));

        expect(vatIdField.getAttribute('pattern')).toBe('DE[0-9]{9}');

        countrySelect.value = 'US';
        countrySelect.dispatchEvent(new Event('change'));

        expect(vatIdField.hasAttribute('pattern')).toBe(false);
    });

    it('should re-validate existing vatIds value when pattern changes on country switch', () => {
        template = `
            <form id="registerForm" action="/register" method="post">

                <div class="form-group col-md-6">
                    <label class="form-label" for="vatIds">VAT Reg.No.</label>
                    <input type="text" name="vatIds[]" id="vatIds" class="form-name">
                </div>

                <select class="country-select" data-initial-country-id="">
                    <option disabled="disabled" value="" selected="selected">Select country...</option>
                    <option value="DE" data-vat-id-required="0" data-state-required="0" data-zipcode-required="0"
                            data-vat-id-pattern="DE[0-9]{9}" data-check-vat-id-pattern="1">Germany</option>
                    <option value="NL" data-vat-id-required="0" data-state-required="0" data-zipcode-required="0"
                            data-vat-id-pattern="NL[0-9]{9}B[0-9]{2}" data-check-vat-id-pattern="1">Netherlands</option>
                </select>
                <select class="country-state-select" data-initial-country-state-id="">
                    <option data-placeholder-option="true">Select state..</option>
                </select>
            </form>
        `;

        document.body.innerHTML = template;
        createPlugin();

        const vatIdField = document.querySelector('#vatIds');
        const countrySelect = document.querySelector('.country-select');
        const validateFieldSpy = jest.spyOn(window.formValidation, 'validateField');

        // Select Germany and enter a valid German VAT ID
        countrySelect.value = 'DE';
        countrySelect.dispatchEvent(new Event('change'));
        vatIdField.value = 'DE123456789';

        validateFieldSpy.mockClear();

        // Switch to Netherlands — plugin should re-validate the existing value against the new pattern
        countrySelect.value = 'NL';
        countrySelect.dispatchEvent(new Event('change'));

        expect(validateFieldSpy).toHaveBeenCalledWith(vatIdField);
    });

    it('should not re-validate vatIds when field is empty on country switch', () => {
        template = `
            <form id="registerForm" action="/register" method="post">

                <div class="form-group col-md-6">
                    <label class="form-label" for="vatIds">VAT Reg.No.</label>
                    <input type="text" name="vatIds[]" id="vatIds" class="form-name">
                </div>

                <select class="country-select" data-initial-country-id="">
                    <option disabled="disabled" value="" selected="selected">Select country...</option>
                    <option value="DE" data-vat-id-required="0" data-state-required="0" data-zipcode-required="0"
                            data-vat-id-pattern="DE[0-9]{9}" data-check-vat-id-pattern="1">Germany</option>
                </select>
                <select class="country-state-select" data-initial-country-state-id="">
                    <option data-placeholder-option="true">Select state..</option>
                </select>
            </form>
        `;

        document.body.innerHTML = template;
        createPlugin();

        const validateFieldSpy = jest.spyOn(window.formValidation, 'validateField');

        const countrySelect = document.querySelector('.country-select');
        countrySelect.value = 'DE';
        countrySelect.dispatchEvent(new Event('change'));

        expect(validateFieldSpy).not.toHaveBeenCalled();
    });

    it('should set pattern attribute via form field toggle change when country has checkVatIdPattern enabled', async () => {
        template = `
            <form id="registerForm" class="register-shipping" action="/register" method="post">

                <div class="form-group col-md-6">
                    <label class="form-label" for="vatIds">VAT Reg.No.</label>
                    <input type="text" name="vatIds[]" id="vatIds" class="form-name">
                </div>

                <select class="country-select" data-initial-country-id="DE">
                    <option selected="selected" value="DE" data-vat-id-required="0" data-state-required="0"
                            data-vat-id-pattern="DE[0-9]{9}" data-check-vat-id-pattern="1">Germany</option>
                </select>
                <select class="country-state-select" data-initial-country-state-id="">
                    <option data-placeholder-option="true">Select state..</option>
                </select>
            </form>
        `;

        document.body.innerHTML = template;

        const plugin = createPlugin();
        await new Promise(process.nextTick);

        plugin._onFormFieldToggleChange({ target: { checked: true } });

        const vatIdField = document.querySelector('#vatIds');
        expect(vatIdField.getAttribute('pattern')).toBe('DE[0-9]{9}');
    });
    it('should set the zipcode pattern on init when the country has postal code validation enabled', async () => {
        template = `
            <form id="registerForm" action="/register" method="post">

                <div class="form-group col-md-3">
                    <label class="form-label" for="addressZipcode">Zip code</label>
                    <input type="text" name="address[zipcode]" id="addressZipcode" data-input-name="zipcodeInput">
                </div>

                <select class="country-select" data-initial-country-id="DE">
                    <option selected="selected" value="DE" data-vat-id-required="0" data-state-required="0"
                            data-zipcode-required="1" data-zipcode-pattern="\\d{5}" data-check-zipcode-pattern="1">Germany</option>
                </select>
                <select class="country-state-select" data-initial-country-state-id="">
                    <option data-placeholder-option="true">Select state..</option>
                </select>
            </form>
        `;

        document.body.innerHTML = template;
        createPlugin();

        await new Promise(process.nextTick);

        expect(document.querySelector('#addressZipcode').getAttribute('pattern')).toBe('\\d{5}');
    });

    it('should not set the zipcode pattern when postal code validation is disabled', async () => {
        template = `
            <form id="registerForm" action="/register" method="post">

                <div class="form-group col-md-3">
                    <label class="form-label" for="addressZipcode">Zip code</label>
                    <input type="text" name="address[zipcode]" id="addressZipcode" data-input-name="zipcodeInput">
                </div>

                <select class="country-select" data-initial-country-id="DE">
                    <option selected="selected" value="DE" data-vat-id-required="0" data-state-required="0"
                            data-zipcode-required="1" data-zipcode-pattern="\\d{5}" data-check-zipcode-pattern="">Germany</option>
                </select>
                <select class="country-state-select" data-initial-country-state-id="">
                    <option data-placeholder-option="true">Select state..</option>
                </select>
            </form>
        `;

        document.body.innerHTML = template;
        createPlugin();

        await new Promise(process.nextTick);

        expect(document.querySelector('#addressZipcode').hasAttribute('pattern')).toBe(false);
    });

    it('should swap the zipcode pattern when switching country and drop it again when the next country has none', () => {
        template = `
            <form id="registerForm" action="/register" method="post">

                <div class="form-group col-md-3">
                    <label class="form-label" for="addressZipcode">Zip code</label>
                    <input type="text" name="address[zipcode]" id="addressZipcode" data-input-name="zipcodeInput">
                </div>

                <select class="country-select" data-initial-country-id="">
                    <option disabled="disabled" value="" selected="selected">Select country...</option>
                    <option value="DE" data-vat-id-required="0" data-state-required="0"
                            data-zipcode-required="1" data-zipcode-pattern="\\d{5}" data-check-zipcode-pattern="1">Germany</option>
                    <option value="NL" data-vat-id-required="0" data-state-required="0"
                            data-zipcode-required="1" data-zipcode-pattern="\\d{4}\\s?[a-zA-Z]{2}" data-check-zipcode-pattern="1">Netherlands</option>
                    <option value="IE" data-vat-id-required="0" data-state-required="0"
                            data-zipcode-required="0" data-zipcode-pattern="" data-check-zipcode-pattern="">Ireland</option>
                </select>
                <select class="country-state-select" data-initial-country-state-id="">
                    <option data-placeholder-option="true">Select state..</option>
                </select>
            </form>
        `;

        document.body.innerHTML = template;
        createPlugin();

        const zipcodeField = document.querySelector('#addressZipcode');
        const countrySelect = document.querySelector('.country-select');

        countrySelect.value = 'DE';
        countrySelect.dispatchEvent(new Event('change'));
        expect(zipcodeField.getAttribute('pattern')).toBe('\\d{5}');

        countrySelect.value = 'NL';
        countrySelect.dispatchEvent(new Event('change'));
        expect(zipcodeField.getAttribute('pattern')).toBe('\\d{4}\\s?[a-zA-Z]{2}');

        countrySelect.value = 'IE';
        countrySelect.dispatchEvent(new Event('change'));
        expect(zipcodeField.hasAttribute('pattern')).toBe(false);
    });

    it('should re-validate an already filled zipcode when the country changes', () => {
        template = `
            <form id="registerForm" action="/register" method="post">

                <div class="form-group col-md-3">
                    <label class="form-label" for="addressZipcode">Zip code</label>
                    <input type="text" name="address[zipcode]" id="addressZipcode" data-input-name="zipcodeInput">
                </div>

                <select class="country-select" data-initial-country-id="">
                    <option disabled="disabled" value="" selected="selected">Select country...</option>
                    <option value="DE" data-vat-id-required="0" data-state-required="0"
                            data-zipcode-required="1" data-zipcode-pattern="\\d{5}" data-check-zipcode-pattern="1">Germany</option>
                </select>
                <select class="country-state-select" data-initial-country-state-id="">
                    <option data-placeholder-option="true">Select state..</option>
                </select>
            </form>
        `;

        document.body.innerHTML = template;
        createPlugin();

        const zipcodeField = document.querySelector('#addressZipcode');
        const countrySelect = document.querySelector('.country-select');
        const validateFieldSpy = jest.spyOn(window.formValidation, 'validateField');

        countrySelect.value = 'DE';
        countrySelect.dispatchEvent(new Event('change'));
        expect(validateFieldSpy).not.toHaveBeenCalled();

        zipcodeField.value = 'asdasd';
        countrySelect.dispatchEvent(new Event('change'));

        expect(validateFieldSpy).toHaveBeenCalledWith(zipcodeField);
    });

    it('should not validate a prefilled zipcode on page load, so server-rendered feedback survives', async () => {
        template = `
            <form id="registerForm" action="/register" method="post">

                <div class="form-group col-md-3">
                    <label class="form-label" for="addressZipcode">Zip code</label>
                    <input type="text" name="address[zipcode]" id="addressZipcode" data-input-name="zipcodeInput"
                           class="form-control is-invalid" aria-describedby="addressZipcodeFeedback" value="12345">
                    <div id="addressZipcodeFeedback" class="invalid-feedback">We do not ship to this postal code.</div>
                </div>

                <select class="country-select" data-initial-country-id="DE">
                    <option selected="selected" value="DE" data-vat-id-required="0" data-state-required="0"
                            data-zipcode-required="1" data-zipcode-pattern="\\d{5}" data-check-zipcode-pattern="1">Germany</option>
                </select>
                <select class="country-state-select" data-initial-country-state-id="">
                    <option data-placeholder-option="true">Select state..</option>
                </select>
            </form>
        `;

        document.body.innerHTML = template;

        const validateFieldSpy = jest.spyOn(window.formValidation, 'validateField');

        createPlugin();
        await new Promise(process.nextTick);

        const zipcodeField = document.querySelector('#addressZipcode');

        expect(zipcodeField.getAttribute('pattern')).toBe('\\d{5}');
        expect(validateFieldSpy).not.toHaveBeenCalled();
        expect(zipcodeField.classList.contains('is-invalid')).toBe(true);
        expect(document.querySelector('#addressZipcodeFeedback').innerHTML).toBe('We do not ship to this postal code.');
    });

    it('should mark an invalid zipcode as invalid even while another required field is still empty', () => {
        template = `
            <form id="registerForm" action="/register" method="post">

                <div class="form-group col-md-3">
                    <label class="form-label" for="addressCity">City</label>
                    <input type="text" name="address[city]" id="addressCity" required="required">
                </div>

                <div class="form-group col-md-3">
                    <label class="form-label" for="addressZipcode">Zip code</label>
                    <input type="text" name="address[zipcode]" id="addressZipcode" data-input-name="zipcodeInput">
                </div>

                <select class="country-select" data-initial-country-id="DE">
                    <option selected="selected" value="DE" data-vat-id-required="0" data-state-required="0"
                            data-zipcode-required="1" data-zipcode-pattern="\\d{5}" data-check-zipcode-pattern="1">Germany</option>
                </select>
                <select class="country-state-select" data-initial-country-state-id="">
                    <option data-placeholder-option="true">Select state..</option>
                </select>
            </form>
        `;

        document.body.innerHTML = template;
        createPlugin();

        const form = document.querySelector('#registerForm');
        const zipcodeField = document.querySelector('#addressZipcode');
        zipcodeField.value = 'asdasd';

        // Mocking `checkVisibility` method, because Jest does not support it.
        form.querySelectorAll('input').forEach((field) => {
            field.checkVisibility = jest.fn().mockReturnValue(true);
        });

        const invalidFields = window.formValidation.validateForm(form);

        expect(invalidFields).toContain(zipcodeField);
        expect(invalidFields).toContain(document.querySelector('#addressCity'));
    });
});
