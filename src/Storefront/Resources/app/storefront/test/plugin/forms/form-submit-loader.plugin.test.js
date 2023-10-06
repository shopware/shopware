/* eslint-disable */
import Storage from 'src/helper/storage/storage.helper';
import template from './form-submit-loader.plugin.template.html';
import outerFormTemplate from './form-submit-loader-outer-form-submit-button.plugin.template.html';
import multipleButtonFormTemplate from './form-submit-loader-multiple-submit-buttons.plugin.template.html';
import editedSelectorTemplate from './form-submit-loader-edited-form-selector.plugin.template.html';
import skipLoadingIndicatorTemplate from './form-submit-loader-without-loading-spinner.plugin.template.html';
import dontSkipLoadingIndicatorTemplate from './form-submit-loader-with-loading-spinner.plugin.template.html';
import FormSubmitLoader from "../../../src/plugin/forms/form-submit-loader.plugin";

function setUpFormLoader(formSelector) {
    Storage.clear();

    const form = document.querySelector(formSelector);
    const plugin = new FormSubmitLoader(form, null, 'FormSubmitLoader');

    return { form, plugin };
}

/**
 * @package content
 */
describe('Form submit loader tests', () => {
    let formSubmitLoaderPlugin;
    let form;

    beforeEach(() => {
        document.body.innerHTML = template;

        const setUp = setUpFormLoader('#test');

        form = setUp.form;
        formSubmitLoaderPlugin = setUp.plugin;
    });

    afterEach(() => {
        formSubmitLoaderPlugin = undefined;
    });

    test('form submit loader plugin exists', () => {
        expect(typeof formSubmitLoaderPlugin).toBe('object');
    });

    test('form is the same as passed form', () => {
        expect(formSubmitLoaderPlugin._form).toBe(form);
    });

    test('find correct submit button', () => {
        const submitButton = document.querySelector('#formBtn');
        expect(formSubmitLoaderPlugin._submitButtons.length).toBe(1);
        expect(formSubmitLoaderPlugin._submitButtons).toContain(submitButton);
    });

    test('submit button is disabled on submit', () => {
        let event = new Event('submit');
        formSubmitLoaderPlugin._onFormSubmit(event);

        const submitButton = document.querySelector('#formBtn');
        expect(submitButton.disabled).toBe(true);
    });
});

describe('Form submit loader tests when submit button is out of form', () => {
    let formSubmitLoaderPlugin;
    let form;

    beforeEach(() => {
        document.body.innerHTML = outerFormTemplate;

        const setUp = setUpFormLoader('#test');

        form = setUp.form;
        formSubmitLoaderPlugin = setUp.plugin;
    });

    afterEach(() => {
        formSubmitLoaderPlugin = undefined;
    });

    test('form submit loader plugin exists', () => {
        expect(typeof formSubmitLoaderPlugin).toBe('object');
    });

    test('form is the same as passed form', () => {
        expect(formSubmitLoaderPlugin._form).toBe(form);
    });

    test('find correct submit button', () => {
        const submitButton = document.querySelector('#formBtn');
        expect(formSubmitLoaderPlugin._submitButtons.length).toBe(1);
        expect(formSubmitLoaderPlugin._submitButtons).toContain(submitButton);
    });

    test('submit button is disabled on submit', () => {
        let event = new Event('submit');
        formSubmitLoaderPlugin._onFormSubmit(event);

        const submitButton = document.querySelector('#formBtn');
        expect(submitButton.disabled).toBe(true);
    });
});

describe('Form submit loader tests with multiple buttons inside and outside the form', () => {
    let formSubmitLoaderPlugin;
    let form;

    beforeEach(() => {
        document.body.innerHTML = multipleButtonFormTemplate;

        const setUp = setUpFormLoader('#test');

        form = setUp.form;
        formSubmitLoaderPlugin = setUp.plugin;
    });

    afterEach(() => {
        formSubmitLoaderPlugin = undefined;
    });

    test('form submit loader plugin exists', () => {
        expect(typeof formSubmitLoaderPlugin).toBe('object');
    });

    test('form is the same as passed form', () => {
        expect(formSubmitLoaderPlugin._form).toBe(form);
    });

    test('find correct submit buttons', () => {
        const submitButtons = Array.from(document.querySelectorAll('.valid-form-button'));
        expect(formSubmitLoaderPlugin._submitButtons.length).toBe(4);
        expect(formSubmitLoaderPlugin._submitButtons).toEqual(expect.arrayContaining(submitButtons));
    });

    test('submit button is disabled on submit', () => {
        let event = new Event('submit');
        formSubmitLoaderPlugin._onFormSubmit(event);

        const submitButtons = document.querySelectorAll('.valid-form-button');
        submitButtons.forEach((button) => {
            expect(button.disabled).toBe(true);
        })
    });
});

describe('form submit loader loads button if selector is edited', () => {
    beforeEach(() => {
        document.body.innerHTML = editedSelectorTemplate;
    });

    test('it loads button inside the container', () => {
        const { plugin } = setUpFormLoader('#test-with-button');

        expect(plugin.options.formWrapperSelector).toBe('.form-container-one');
        expect(plugin._submitButtons.length).toBe(1);
        expect(plugin._submitButtons[0].id).toBe('inside-form-one');
    });

    test('it loads button inside the container', () => {
        expect(() => {
            setUpFormLoader('test-without-button');
        }).toThrow('There is no valid element given.');
    });
});


describe('form submit loader', () => {
    let form, plugin;

    test('does not add a loading spinner but disables the button', () => {
        document.body.innerHTML = skipLoadingIndicatorTemplate;

        const setup = setUpFormLoader('#test');

        plugin = setup.plugin;
        form = setup.form;

        expect(plugin.options.skipLoadingIndicator).toBeTruthy();

        const event = new Event('submit');
        plugin._onFormSubmit(event);

        const submitButtons = document.querySelectorAll('#formBtn');

        submitButtons.forEach((button) => {
            expect(button.disabled).toBeTruthy();
            expect(button.innerHTML).toBe('Submit');
        });
    });

    test('add a loading spinner and disables the button', () => {
        document.body.innerHTML = dontSkipLoadingIndicatorTemplate;

        const setup = setUpFormLoader('#test');

        plugin = setup.plugin;
        form = setup.form;

        expect(plugin.options.skipLoadingIndicator).toBeFalsy();

        const event = new Event('submit');
        plugin._onFormSubmit(event);

        const submitButtons = document.querySelectorAll('#formBtn');

        submitButtons.forEach((button) => {
            expect(button.disabled).toBeTruthy();
            expect(button.querySelector('div').classList.contains('loader')).toBeTruthy();
            expect(button.innerHTML).toContain('Submit');
        });
    });

});


