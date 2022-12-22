/* eslint-disable */
import Storage from 'src/helper/storage/storage.helper';
import FormCsrfHandler from "../../../src/plugin/forms/form-csrf-handler.plugin";
import template from "./form-csrf-handler.plugin.template.html";

/**
 * @package content
 */
function setUpFormLoader(formSelector) {
    window.csrf = {
        enabled: true,
        mode: 'ajax',
    };

    window.router = {
        'frontend.csrf.generateToken': '/csrf'
    };

    Storage.clear();

    const form = document.querySelector(formSelector);
    const plugin = new FormCsrfHandler(form, null, 'FormCsrfHandler');

    return { form, plugin };
}

describe('Form csrf handler tests', () => {
    let plugin;
    let form;

    beforeEach(() => {
        document.body.innerHTML = template;

        const setUp = setUpFormLoader('#test');

        form = setUp.form;
        plugin = setUp.plugin;

        plugin.client = {
            fetchCsrfToken: function (callback) {
                callback('csrf-token')
            }
        };
    });

    afterEach(() => {
        plugin = undefined;
        form = undefined;
    });

    test('form csrf plugin exists', () => {
        expect(typeof plugin).toBe('object');
    });

    test('form is the same as passed form', () => {
        expect(plugin._form).toBe(form);
    });

    test('test submit fires an event', () => {
        form.submit = jest.fn();

        const csrfEvent = jest.fn();
        plugin.$emitter.subscribe('beforeFetchCsrfToken', csrfEvent)

        const beforeSubmitEvent = jest.fn();
        plugin.$emitter.subscribe('beforeSubmit', beforeSubmitEvent)

        plugin.onSubmit(new CustomEvent('submit'));

        expect(csrfEvent).toBeCalled();
        expect(beforeSubmitEvent).toBeCalled();
        expect(form.submit).toBeCalled();
        expect(form.querySelector(['[name="_csrf_token"]']).name).toBe('_csrf_token')
    })

    test('test beforeSubmit event got canceled and triggers no submit', () => {
        form.submit = jest.fn();

        const csrfEvent = jest.fn();
        plugin.$emitter.subscribe('beforeFetchCsrfToken', csrfEvent)

        plugin.$emitter.subscribe('beforeSubmit', function (event) {
            event.preventDefault()
        })

        plugin.onSubmit(new CustomEvent('submit'));

        expect(csrfEvent).toBeCalled();
        expect(form.submit).not.toBeCalled();
        expect(form.querySelector(['[name="_csrf_token"]']).name).toBe('_csrf_token')
    })
})
