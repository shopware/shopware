/**
 * @jest-environment jsdom
 */

/* eslint-disable */
import Storage from 'src/helper/storage/storage.helper';
import template from './form-submit-loader.plugin.template.html';
import FormSubmitLoader from "../../../src/plugin/forms/form-submit-loader.plugin";

describe('Form submit loader tests', () => {
    let formSubmitLoaderPlugin = undefined;
    let form = undefined;

    beforeEach(() => {
        document.body.innerHTML = template;

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
        form = document.querySelector('#test');
        formSubmitLoaderPlugin = new FormSubmitLoader(form, null, 'FormSubmitLoader');
    });

    afterEach(() => {
        formSubmitLoaderPlugin = undefined;
    });

    test('form submit loader plugin exists', () => {
        expect(typeof formSubmitLoaderPlugin).toBe('object');
    });

    test('form is the sames as passed from', () => {
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
