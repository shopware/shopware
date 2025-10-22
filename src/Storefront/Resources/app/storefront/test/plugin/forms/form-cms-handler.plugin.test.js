/* eslint-disable */
import FormCmsHandlerPlugin from 'src/plugin/forms/form-cms-handler.plugin';

const template = `
    <div class="cms-block">
      <form id="test-form"></form>
    <div>
`.trim();

describe('Form CMS Handler tests', () => {

    let formCmsHandlerPlugin = undefined;
    let formElement = undefined;

    beforeEach(() => {
        document.body.innerHTML = template;
        formElement = document.getElementById('test-form');
        formElement.parentElement.scrollIntoView = jest.fn(); // Used by form-cms-handler plugin, but not implemented by jsdom.
        formCmsHandlerPlugin = new FormCmsHandlerPlugin(formElement);
    });

    test('form cms handler plugin exists', () => {
        expect(typeof formCmsHandlerPlugin).toBe('object');
    });

    test('form cms handler resets form after successful ajax submission', async () => {
        global.fetch = jest.fn(() =>
            Promise.resolve({
                text: () => Promise.resolve('[{"type":"success","alert":""}]'),
            })
        );

        const resetSpy = jest.spyOn(formElement, 'reset');

        formElement.dispatchEvent(new Event('submit'));
        await new Promise(process.nextTick);

        expect(global.fetch).toHaveBeenCalled();
        expect(resetSpy).toHaveBeenCalled();
    });

    test('form cms handler does not reset after unsuccessful ajax submission', async () => {
        global.fetch = jest.fn(() =>
            Promise.resolve({
                text: () => Promise.resolve('[{"type":"danger","alert":""}]'),
            })
        );

        const resetSpy = jest.spyOn(formElement, 'reset');

        formElement.dispatchEvent(new Event('submit'));
        await new Promise(process.nextTick);

        expect(global.fetch).toHaveBeenCalled();
        expect(resetSpy).not.toHaveBeenCalled();
    });
});
