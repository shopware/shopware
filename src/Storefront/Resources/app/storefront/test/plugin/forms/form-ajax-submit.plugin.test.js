import FormAjaxSubmitPlugin from 'src/plugin/forms/form-ajax-submit.plugin';

/**
 * @package content
 */
describe('FormAjaxSubmitPlugin tests', () => {
    let formAjaxSubmit;

    beforeEach(() => {
        document.body.innerHTML = `
            <div class="replace-me"></div>

            <form method="post" action="/account/newsletter/subscribe">
                <input type="email" name="email" value="test@example.com">
                <button>Subscribe to newsletter</button>
            </form>
        `;

        const formElement = document.querySelector('form');

        formAjaxSubmit = new FormAjaxSubmitPlugin(formElement, {
            replaceSelectors: ['.replace-me'],
        });

        global.fetch = jest.fn(() =>
            Promise.resolve({
                text: () => Promise.resolve('<div class="replace-me"><div class="alert">Success</div></div>'),
            })
        );

        formAjaxSubmit.$emitter.publish = jest.fn();

        window.PluginManager.initializePlugins = jest.fn();
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    test('plugin initializes', () => {
        expect(typeof formAjaxSubmit).toBe('object');
        expect(formAjaxSubmit instanceof FormAjaxSubmitPlugin).toBe(true);
    });

    test('submits form with ajax request', () => {
        const submitButton = document.querySelector('button');
        submitButton.click();

        expect(formAjaxSubmit._getFormData().get('email')).toBe('test@example.com');
        expect(global.fetch).toHaveBeenCalledWith(
            '/account/newsletter/subscribe',
            {
                method: 'POST',
                body: expect.any(FormData),
                headers: expect.any(Object),
            },
        );
    });

    test('shows HTML from response with replace selectors option', async () => {
        const submitButton = document.querySelector('button');
        await submitButton.click();
        await new Promise(process.nextTick);

        expect(document.querySelector('.alert').innerHTML).toBe('Success');
        expect(window.PluginManager.initializePlugins).toHaveBeenCalledTimes(1);
    });

    test('executes callback when submitting form', async () => {
        const submitButton = document.querySelector('button');
        const cb = jest.fn();

        formAjaxSubmit.addCallback(cb);
        submitButton.click();
        await new Promise(process.nextTick);

        expect(cb).toHaveBeenCalledTimes(1);
    });

    test('executes callback when submitting form via form submit event', async () => {
        const cb = jest.fn();
        const formElement = document.querySelector('form');

        formAjaxSubmit.addCallback(cb);
        formElement.dispatchEvent(new Event('submit', { cancelable: true }));
        await new Promise(process.nextTick);

        expect(cb).toHaveBeenCalledTimes(1);
    });

    test('will log an error when submitting form via non-cancelable form submit event', () => {
        const formElement = document.querySelector('form');
        const consoleSpy = jest.spyOn(console, 'error').mockImplementation();

        formElement.dispatchEvent(new Event('submit', { cancelable: false }));

        expect(consoleSpy).toHaveBeenCalledWith('[Ajax Form Submit]: The submit event cannot be prevented as it is not cancelable and would be handled by the navigator.');
    });
});
