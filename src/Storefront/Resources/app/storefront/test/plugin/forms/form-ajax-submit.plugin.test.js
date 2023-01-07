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

        formAjaxSubmit._client.post = jest.fn((url, data, callback) => {
            callback('<div class="replace-me"><div class="alert">Success</div></div>');
        });

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

        // Click submit button
        submitButton.dispatchEvent(new Event('click', { bubbles: true }));

        expect(formAjaxSubmit._getFormData().get('email')).toBe('test@example.com');
        expect(formAjaxSubmit._client.post).toHaveBeenCalledWith(
            '/account/newsletter/subscribe',
            expect.any(FormData),
            expect.any(Function),
        );
    });

    test('shows HTML from response with replace selectors option', () => {
        const submitButton = document.querySelector('button');

        // Click submit button
        submitButton.dispatchEvent(new Event('click', { bubbles: true }));

        expect(document.querySelector('.alert').innerHTML).toBe('Success');
        expect(window.PluginManager.initializePlugins).toHaveBeenCalledTimes(1);
    });

    test('executes callback when submitting form', () => {
        const submitButton = document.querySelector('button');
        const cb = jest.fn();

        formAjaxSubmit.addCallback(cb);

        // Click submit button
        submitButton.dispatchEvent(new Event('click', { bubbles: true }));

        expect(cb).toHaveBeenCalledTimes(1);
    });
});
