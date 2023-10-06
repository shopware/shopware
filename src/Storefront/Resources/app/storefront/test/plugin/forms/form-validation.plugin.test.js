import FormValidationPlugin from 'src/plugin/forms/form-validation.plugin';

/**
 * @package content
 */
describe('FormValidationPlugin tests', () => {
    let formValidationPlugin = null;
    let spySetFieldToInvalid = jest.fn();

    const template = `
        <form id="Form" method="post">
            <input type="email" name="email" class="form-email" value="test@example.com">
            <input type="email" name="emailConfirm" class="form-email" value="test@example.com">
            <input type="password" name="password" class="form-password" value="password">
        </form>
    `;

    function createPlugin(pluginOptions = {}) {
        return new FormValidationPlugin(document.querySelector('#Form'), pluginOptions);
    }

    beforeEach(() => {
        document.body.innerHTML = template;

        formValidationPlugin = createPlugin();

        spySetFieldToInvalid = jest.spyOn(FormValidationPlugin.prototype, '_setFieldToInvalid');
    });

    afterEach(() => {
        document.body.innerHTML = '';
        formValidationPlugin = undefined;
        spySetFieldToInvalid.mockClear();
    });

    test('should instantiate plugin', () => {
        expect(formValidationPlugin instanceof FormValidationPlugin).toBe(true);
    });

    test('validate empty fields', () => {
        const spyOnValidRequired = jest.spyOn(FormValidationPlugin.prototype, '_onValidateRequired');

        const inputField = document.querySelector(`[name=email]`);
        inputField.setAttribute('data-form-validation-required', '');
        inputField.setAttribute('data-form-validation-required-message', 'Email should not be empty.')

        createPlugin();

        expect(spyOnValidRequired).not.toHaveBeenCalled();
        expect(spySetFieldToInvalid).not.toHaveBeenCalled();

        inputField.dispatchEvent(new Event('change'));

        expect(spyOnValidRequired).toHaveBeenCalledTimes(1);
        expect(spySetFieldToInvalid).toHaveBeenCalledTimes(0);
        expect(document.querySelector('.invalid-feedback')).toBeNull();

        inputField.value = ' ';
        inputField.dispatchEvent(new Event('change'));

        expect(spyOnValidRequired).toHaveBeenCalledTimes(2);
        expect(spySetFieldToInvalid).toHaveBeenCalledTimes(1);
        expect(document.querySelector('.invalid-feedback').textContent).toBe('Email should not be empty.');
    });

    test('validate length fields', () => {
        const spyOnValidateLength = jest.spyOn(FormValidationPlugin.prototype, '_onValidateLength');

        const inputField = document.querySelector(`[type=password]`);

        inputField.setAttribute('data-form-validation-length', 5);
        inputField.setAttribute('data-form-validation-length-message', 'Passwords must have a minimum length of 5 characters.')

        createPlugin();

        expect(spyOnValidateLength).not.toHaveBeenCalled();
        expect(spySetFieldToInvalid).not.toHaveBeenCalled();

        inputField.dispatchEvent(new Event('change'));

        expect(spyOnValidateLength).toHaveBeenCalledTimes(1);
        expect(spySetFieldToInvalid).toHaveBeenCalledTimes(0);
        expect(document.querySelector('.invalid-feedback')).toBeNull();

        inputField.value = '1234';
        inputField.dispatchEvent(new Event('change'));

        expect(spyOnValidateLength).toHaveBeenCalledTimes(2);
        expect(spySetFieldToInvalid).toHaveBeenCalledTimes(1);
        expect(document.querySelector('.invalid-feedback').textContent).toBe('Passwords must have a minimum length of 5 characters.');

        spyOnValidateLength.mockClear();
    });
})

