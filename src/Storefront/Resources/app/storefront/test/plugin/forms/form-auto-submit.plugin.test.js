/**
 * @jest-environment jsdom
 */

import FormAutoSubmitPlugin from 'src/plugin/forms/form-auto-submit.plugin';

describe('Form auto submit plugin', () => {
    let spyNativeFormSubmit = jest.fn();
    let spyOnSubmit = jest.fn();
    let spyOnChange = jest.fn();

    const template = `
        <form id="newsletterForm" action="/newsletter/configure" method="post">
            <input type="email" name="email" class="form-email" value="test@example.com">
            <input type="text" name="firstName" class="form-name" value="John">
            <input type="checkbox" name="unsubscribe" class="form-unsubscribe" value="1">
        </form>
    `;

    function createPlugin(pluginOptions = {}) {
        const mockElement = document.querySelector('#newsletterForm');
        return new FormAutoSubmitPlugin(mockElement, pluginOptions);
    }

    beforeEach(() => {
        window.PluginManager = {
            getPluginInstancesFromElement: () => {
                return new Map();
            },
            getPlugin: () => {
                return {
                    get: () => []
                };
            }
        };

        window.router = [];

        window.csrf = {
            enabled: false
        };

        window.HTMLFormElement.prototype.submit = spyNativeFormSubmit;

        document.body.innerHTML = template;

        spyOnSubmit = jest.spyOn(FormAutoSubmitPlugin.prototype, '_onSubmit');
        spyOnChange = jest.spyOn(FormAutoSubmitPlugin.prototype, '_onChange');
        spyNativeFormSubmit = jest.spyOn(window.HTMLFormElement.prototype, 'submit');
    });

    afterEach(() => {
        document.body.innerHTML = '';
        spyNativeFormSubmit.mockClear();
        spyOnSubmit.mockClear();
        spyOnChange.mockClear();
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
});
