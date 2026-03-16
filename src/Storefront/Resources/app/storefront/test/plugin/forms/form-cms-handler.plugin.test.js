import FormCmsHandlerPlugin from 'src/plugin/forms/form-cms-handler.plugin';

const template = `
    <div class="cms-block">
      <form id="test-form">
        <button type="submit">Submit</button>
      </form>
    </div>
`.trim();

describe('Form CMS Handler tests', () => {
    let formCmsHandlerPlugin = undefined;
    let formElement = undefined;
    let submitButtonElement = undefined;

    beforeEach(() => {
        document.body.innerHTML = template;

        formElement = document.getElementById('test-form');
        formElement.parentElement.scrollIntoView = jest.fn(); // Used by form-cms-handler plugin, but not implemented by jsdom.

        submitButtonElement = formElement.querySelector('button[type=submit]');

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

    test('form cms handler disables submit button while request is pending', () => {
        global.fetch = jest.fn(() => new Promise(() => {}));

        formElement.dispatchEvent(new Event('submit'));

        expect(submitButtonElement.disabled).toBe(true);
    });

    test('form cms handler re-enables submit button after error response', async () => {
        global.fetch = jest.fn(() =>
            Promise.resolve({
                text: () => Promise.resolve('[{"type":"danger","alert":""}]'),
            })
        );

        formElement.dispatchEvent(new Event('submit'));

        expect(submitButtonElement.disabled).toBe(true);

        await new Promise(process.nextTick);

        expect(submitButtonElement.disabled).toBe(false);
    });
});
