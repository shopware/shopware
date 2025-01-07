import FormCountryStateSelectPlugin from 'src/plugin/forms/form-country-state-select.plugin';
import FormFieldTogglePlugin from 'src/plugin/forms/form-field-toggle.plugin';
import FormValidation from 'src/helper/form-validation.helper';
import Feature from 'src/helper/feature.helper';

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

        window.Feature = Feature;
        window.Feature.init({ 'ACCESSIBILITY_TWEAKS': true });

        window.validationMessages = {
            required: 'Input should not be empty.',
            email: 'Invalid email address.',
            confirmation: 'Confirmation field does not match.',
            minLength: 'Input is too short.',
        };

        window.formValidation = new FormValidation();
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
                    <option>Select state..</option>
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
                    <option>Select state..</option>
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

    it('should initialize form field toggle instance and subscribe to onChange event', () => {
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
                            <select class="country-state-select form-select" data-initial-country-state-id="">
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

        plugin._getFormFieldToggleInstance();

        expect(plugin._formFieldToggleInstance).toBe(mockToggleInstance);
        expect(mockToggleInstance.$emitter.subscribe).toHaveBeenCalledWith('onChange', expect.any(Function));
    });

    it('should not subscribe to onChange event if form field toggle instance is not found', () => {
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
                            <select class="country-state-select form-select" data-initial-country-state-id="">
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

        plugin._getFormFieldToggleInstance();

        expect(plugin._formFieldToggleInstance).toBeNull();
    });

    it('should update country state label when state required', () => {
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
                                <option selected="selected" value="31e1ac8809c744c38c4d99bfe9a50aa8" data-zipcode-required="" data-vat-id-required="" data-state-required="">Deutschland</option>
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

        const plugin = createPlugin();

        plugin._client.post = jest.fn((url, _, callback) => {
            const response = {
                countryId: '31e1ac8809c744c38c4d99bfe9a50aa8',
                states: [{ id: '0490081418be4255b87731afc953e901', translated: { name: 'Hamburg' }}],
            };

            callback(JSON.stringify(response));
        });

        const stateLabel = document.querySelector('[for="shippingAddressAddressCountryState"]');

        expect(stateLabel.textContent).toBe('Bundesland');

        plugin.requestStateData('31e1ac8809c744c38c4d99bfe9a50aa8', '0490081418be4255b87731afc953e901', true);

        expect(stateLabel.innerHTML.includes('form-required-label')).toBe(true);
    });

    it('should update VAT ID field to required when different shipping address is selected', () => {
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
                    <option>Select state..</option>
                </select>
            </form>
        `;

        document.body.innerHTML = template;

        const plugin = createPlugin();
        const event = { target: { checked: true } };

        plugin._onFormFieldToggleChange(event);

        const vatIdInput = document.querySelector(plugin.options.vatIdFieldInput);
        const vatIdFieldLabel = document.querySelector('label[for="vatIds"]');

        expect(vatIdInput.hasAttribute('aria-required')).toBe(true);
        expect(vatIdFieldLabel.innerHTML.includes('form-required-label')).toBe(true);
    });

    it('should update VAT ID field to not required when different shipping address is not selected', () => {
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
        const event = { target: { checked: false } };

        plugin._onFormFieldToggleChange(event);

        const vatIdInput = document.querySelector(plugin.options.vatIdFieldInput);
        const vatIdFieldLabel = document.querySelector('label[for="vatIds"]');

        expect(vatIdInput.hasAttribute('aria-required')).toBe(false);
        expect(vatIdFieldLabel.innerHTML.includes('form-required-label')).toBe(false);
    });

    it('should not update VAT ID field when different shipping address is selected and prefix is billingAddress', () => {
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
        const event = { target: { checked: true } };

        plugin._differentShippingCheckbox = true;
        plugin._onFormFieldToggleChange(event);

        const vatIdInput = document.querySelector(plugin.options.vatIdFieldInput);
        const vatIdFieldLabel = document.querySelector('label[for="vatIds"]');

        expect(vatIdInput.hasAttribute('aria-required')).toBe(false);
        expect(vatIdFieldLabel.innerHTML.includes('form-required-label')).toBe(false);
    });
});
