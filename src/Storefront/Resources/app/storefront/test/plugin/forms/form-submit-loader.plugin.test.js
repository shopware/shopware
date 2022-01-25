/**
 * @jest-environment jsdom
 */

/* eslint-disable */
import Storage from 'src/helper/storage/storage.helper';
import template from './form-submit-loader.plugin.template.html';
import outerFormTemplate from './form-submit-loader-outer-form-submit-button.plugin.template.html';
import editedSelectorTemplate from './form-submit-loader-edited-form-selector.plugin.template.html';
import FormSubmitLoader from "../../../src/plugin/forms/form-submit-loader.plugin";

function setUpFormLoader(formSelector) {
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

    Storage.clear();

    const form = document.querySelector(formSelector);
    const plugin = new FormSubmitLoader(form, null, 'FormSubmitLoader');

    return { form, plugin };
}

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
        expect(formSubmitLoaderPlugin._submitButton).toBe(submitButton);
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
        expect(formSubmitLoaderPlugin._submitButton).toBe(submitButton);
    });

    test('submit button is disabled on submit', () => {
        let event = new Event('submit');
        formSubmitLoaderPlugin._onFormSubmit(event);

        const submitButton = document.querySelector('#formBtn');
        expect(submitButton.disabled).toBe(true);
    });
});

describe('form submit loader loads button if selector is edited', () => {
    beforeEach(() => {
        document.body.innerHTML = editedSelectorTemplate;
    });

    test('it loads button inside the container', () => {
        const { plugin } = setUpFormLoader('#test-with-button');

        expect(plugin.options.formWrapperSelector).toBe('.form-container-one');
        expect(plugin._submitButton.id).toBe('inside-form-one');
    });

    test('it loads button inside the container', () => {
        expect(() => {
            setUpFormLoader('test-without-button');
        }).toThrow('There is no valid element given.');
    });
});
