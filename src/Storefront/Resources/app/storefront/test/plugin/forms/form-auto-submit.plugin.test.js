import FormAutoSubmitPlugin from 'src/plugin/forms/form-auto-submit.plugin';

/**
 * @package content
 */
describe('Form auto submit plugin', () => {
    let spyNativeFormSubmit = jest.fn();
    let spyOnSubmit = jest.fn();
    let spyOnChange = jest.fn();
    let spyUpdateParams = jest.fn();

    const template = `
        <form id="newsletterForm" action="/newsletter/configure" method="post">
            <input type="email" name="email" class="form-email" value="test@example.com">
            <input type="text" name="firstName" class="form-name" value="John">
            <input type="hidden" name="redirectParameters[important]" value="doNotOverwrite">
            <input type="checkbox" name="unsubscribe" class="form-unsubscribe" value="1">
        </form>
    `;

    function createPlugin(pluginOptions = {}) {
        const mockElement = document.querySelector('#newsletterForm');
        return new FormAutoSubmitPlugin(mockElement, pluginOptions);
    }

    beforeEach(() => {
        window.HTMLFormElement.prototype.submit = spyNativeFormSubmit;

        window.focusHandler = {
            saveFocusState: jest.fn(),
            resumeFocusState: jest.fn(),
            saveFocusStatePersistent: jest.fn(),
            resumeFocusStatePersistent: jest.fn(),
        }

        document.body.innerHTML = template;

        spyOnSubmit = jest.spyOn(FormAutoSubmitPlugin.prototype, '_onSubmit');
        spyOnChange = jest.spyOn(FormAutoSubmitPlugin.prototype, '_onChange');
        spyUpdateParams = jest.spyOn(FormAutoSubmitPlugin.prototype, '_updateRedirectParameters');
        spyNativeFormSubmit = jest.spyOn(window.HTMLFormElement.prototype, 'submit');
    });

    afterEach(() => {
        document.body.innerHTML = '';
        spyNativeFormSubmit.mockClear();
        spyOnSubmit.mockClear();
        spyOnChange.mockClear();
        spyUpdateParams.mockClear();
    });

    it('should instantiate plugin', () => {
        const formAutoSubmitPlugin = createPlugin();

        expect(formAutoSubmitPlugin instanceof FormAutoSubmitPlugin).toBe(true);
    });

    it('should auto submit native form on form change', () => {
        createPlugin();

        const emailField = document.querySelector('.form-email');

        // Fire change event on input field which bubbles up to the form
        emailField.dispatchEvent(new Event('change', { bubbles: true }));

        expect(spyNativeFormSubmit).toHaveBeenCalledTimes(1);
        expect(spyOnChange).toHaveBeenCalledTimes(1);
        expect(spyOnSubmit).toHaveBeenCalledTimes(0);
    });

    it('should auto submit form with ajax on form change', () => {
        createPlugin({ useAjax: true, ajaxContainerSelector: '#newsletterForm' });

        const emailField = document.querySelector('.form-email');

        // Fire change event on input field which bubbles up to the form
        emailField.dispatchEvent(new Event('change', { bubbles: true }));

        expect(spyOnSubmit).toHaveBeenCalledTimes(1);
        expect(spyNativeFormSubmit).toHaveBeenCalledTimes(0);
        expect(spyOnChange).toHaveBeenCalledTimes(0);
    });

    it('should perform auto submit for every changed input element in form by default', () => {
        createPlugin();

        const emailField = document.querySelector('.form-email');
        const nameField = document.querySelector('.form-name');
        const unsubscribeField = document.querySelector('.form-unsubscribe');
        const changeEvent = new Event('change', { bubbles: true });

        // Fire change events on input fields which bubble up to the form
        emailField.dispatchEvent(changeEvent);
        nameField.dispatchEvent(changeEvent);
        unsubscribeField.dispatchEvent(changeEvent);

        expect(spyOnChange).toHaveBeenCalledTimes(3);
        expect(spyNativeFormSubmit).toHaveBeenCalledTimes(3);
        expect(spyOnSubmit).toHaveBeenCalledTimes(0);
    });

    it('should only perform auto submit when one of the configured input elements fires change event', () => {
        createPlugin({ changeTriggerSelectors: ['.form-unsubscribe', '.form-name'] });

        const emailField = document.querySelector('.form-email');
        const nameField = document.querySelector('.form-name');
        const unsubscribeField = document.querySelector('.form-unsubscribe');
        const changeEvent = new Event('change', { bubbles: true });

        // Fire change events on input fields which bubble up to the form
        emailField.dispatchEvent(changeEvent);
        nameField.dispatchEvent(changeEvent);
        unsubscribeField.dispatchEvent(changeEvent);

        // General on change method should be executed for every change
        expect(spyOnChange).toHaveBeenCalledTimes(3);
        // Native form submit should only be performed when configured elements changed
        expect(spyNativeFormSubmit).toHaveBeenCalledTimes(2);
        expect(spyOnSubmit).toHaveBeenCalledTimes(0);
    });

    it('should throw error when change trigger selectors is not an array', () => {
        const expectedError = '[FormAutoSubmitPlugin] The option "changeTriggerSelectors" must be an array of selector strings.'

        expect(() => createPlugin({ changeTriggerSelectors: '.some-selector' })).toThrow(expectedError);
    });

    it('should throw error when ajax mode is missing a replace selector', () => {
        const expectedError = '[FormAutoSubmitPlugin] The option "ajaxContainerSelector" must be given when using ajax.'

        expect(() => createPlugin({ useAjax: true })).toThrow(expectedError);
    });

    it('should update redirect parameters on form change not existing in form', () => {
        createPlugin({ changeTriggerSelectors: ['.form-unsubscribe', '.form-name'] });

        Object.defineProperty(window, 'location', {
            value: {
                search: '?important=0&test=1',
            },
        });

        const emailField = document.querySelector('.form-email');

        emailField.dispatchEvent(new Event('change', { bubbles: true }));

        expect(spyOnChange).toHaveBeenCalled();
        expect(spyUpdateParams).toHaveBeenCalled();

        const hiddenImportantField = document.querySelectorAll('[name="redirectParameters[important]"]');
        expect(hiddenImportantField).toHaveLength(1);
        expect(hiddenImportantField[0].value).toBe('doNotOverwrite');

        const hiddenTestField = document.querySelectorAll('[name="redirectParameters[test]"]');
        expect(hiddenTestField).toHaveLength(1);
        expect(hiddenTestField[0].value).toBe('1');
    });

    test('should generate correct input for redirect parameter', () => {
        const formAutoSubmitPlugin = createPlugin();

        const input = document.createElement('div');
        input.innerHTML = '<input name="redirectParameters[name]" type="hidden" value="value" />';

        expect(formAutoSubmitPlugin._createInputForRedirectParameter('name', 'value')).toStrictEqual(input.firstChild);
    });
});
