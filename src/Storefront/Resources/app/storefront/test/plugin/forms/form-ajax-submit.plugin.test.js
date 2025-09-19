import FormAjaxSubmitPlugin from 'src/plugin/forms/form-ajax-submit.plugin';

/**
 * @package content
 */
describe('FormAjaxSubmitPlugin tests', () => {
    let formAjaxSubmit;
    let formElement;

    beforeEach(() => {
        document.body.innerHTML = `
            <div class="replace-me"></div>

            <form method="post" action="/account/newsletter/subscribe">
                <input type="email" name="email" value="test@example.com">
                <button>Subscribe to newsletter</button>
            </form>
        `;

        formElement = document.querySelector('form');

        formAjaxSubmit = new FormAjaxSubmitPlugin(formElement, {
            replaceSelectors: ['.replace-me'],
            submitOnChange: true,
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
                method: 'post',
                body: expect.any(FormData),
                headers: expect.any(Object),
            },
        );
    });

    test('shows HTML from response with replace selectors option', async () => {
        const submitButton = document.querySelector('button');
        submitButton.click();
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

        formAjaxSubmit.addCallback(cb);
        formElement.dispatchEvent(new Event('submit', { cancelable: true }));
        await new Promise(process.nextTick);

        expect(cb).toHaveBeenCalledTimes(1);
    });

    test('not executes callback when submitting invalid form', async () => {
        formElement.checkValidity = jest.fn(() => false);

        const submitButton = document.querySelector('button');
        const cb = jest.fn();

        formAjaxSubmit.addCallback(cb);
        submitButton.click();
        await new Promise(process.nextTick);

        expect(cb).toHaveBeenCalledTimes(0);
    });

    test('executes callback when submitting form via input change event', async () => {
        const cb = jest.fn();
        const inputElement = formElement.querySelector('input');

        formAjaxSubmit.addCallback(cb);
        // native browser change event not cancelable
        inputElement.dispatchEvent(new Event('change', { bubbles: true, cancelable: false }));
        await new Promise(process.nextTick);

        expect(cb).toHaveBeenCalledTimes(1);
    });

    test('calls _fireRequest when input matches submitOnChange selector', async () => {
        const cb = jest.fn();
        const inputElement = formElement.querySelector('input');
        inputElement.classList.add('trigger-me');

        // Create plugin with submitOnChange as array
        formAjaxSubmit = new FormAjaxSubmitPlugin(formElement, {
            replaceSelectors: ['.replace-me'],
            submitOnChange: ['.trigger-me'],
        });

        formAjaxSubmit.addCallback(cb);
        // native browser change event not cancelable
        inputElement.dispatchEvent(new Event('change', { bubbles: true }));
        await new Promise(process.nextTick);

        expect(cb).toHaveBeenCalledTimes(1);
    });

    test('stops checking further selectors once a match is found in submitOnChange', async () => {
        const cb = jest.fn();
        const inputElement = formElement.querySelector('input');
        inputElement.classList.add('match-me');

        // Create plugin with submitOnChange as array
        formAjaxSubmit = new FormAjaxSubmitPlugin(formElement, {
            replaceSelectors: ['.replace-me'],
            submitOnChange: ['.match-me', '.should-not-be-checked'],
        });
        const fireSpy = jest.spyOn(formAjaxSubmit, '_fireRequest');

        formAjaxSubmit.addCallback(cb);
        // native browser change event not cancelable
        inputElement.dispatchEvent(new Event('change', { bubbles: true }));
        await new Promise(process.nextTick);

        expect(cb).toHaveBeenCalledTimes(1);
        expect(fireSpy).toHaveBeenCalledTimes(1);
    });

    test('will log an error when submitting form via non-cancelable form submit event', () => {
        const event = new Event('submit', { cancelable: false });
        const eventSpy = jest.spyOn(event, 'preventDefault');
        formElement.dispatchEvent(event);

        expect(eventSpy).not.toHaveBeenCalled();
    });

    test('executes callback when submitting invalid form with noValidate option', async () => {
        // Add required attribute to input & Remove value from input
        document.body.innerHTML = `
            <div class="replace-me"></div>

            <form method="post" action="/account/newsletter/subscribe">
                <input type="email" name="email" required>
                <button>Subscribe to newsletter</button>
            </form>
        `;

        formElement = document.querySelector('form');

        formAjaxSubmit = new FormAjaxSubmitPlugin(formElement, {
            replaceSelectors: ['.replace-me'],
            submitOnChange: true,
            noValidate: true,
        });

        global.fetch = jest.fn(() =>
            Promise.resolve({
                text: () => Promise.resolve('<div class="replace-me"><div class="alert">Success</div></div>'),
            })
        );

        formAjaxSubmit.$emitter.publish = jest.fn();

        window.PluginManager.initializePlugins = jest.fn();

        // execute the tests on the invalid form
        const cb = jest.fn();
        const inputElement = formElement.querySelector('input');

        formAjaxSubmit.addCallback(cb);
        // native browser change event not cancelable
        inputElement.dispatchEvent(new Event('change', { bubbles: true, cancelable: false }));
        await new Promise(process.nextTick);

        expect(cb).toHaveBeenCalledTimes(1);
    });

    test('form uses correct formAction & formMethod from submitter', async () => {
        // Add formAction & formMethod to submitter
        document.body.innerHTML = `
            <div class="replace-me"></div>

            <form method="post" action="/account/newsletter/subscribe">
                <input type="email" name="email" value="test@example.com">
                <button type="submit" formaction="/account/newsletter/unsubscribe" formmethod="get">Subscribe to newsletter</button>
            </form>
        `;

        formElement = document.querySelector('form');

        formAjaxSubmit = new FormAjaxSubmitPlugin(formElement, {
            replaceSelectors: ['.replace-me'],
            submitOnChange: true,
        });

        global.fetch = jest.fn((url, options) => {
            expect(url).toBe('/account/newsletter/unsubscribe');
            expect(options).toStrictEqual({ headers: { 'X-Requested-With': 'XMLHttpRequest' }});

            return Promise.resolve({
                text: () => Promise.resolve('<div class="replace-me"><div class="alert">Success</div></div>'),
            });
        });

        formAjaxSubmit.$emitter.publish = jest.fn();

        window.PluginManager.initializePlugins = jest.fn();

        const event = new Event('submit');
        event.submitter = formElement.querySelector('button');

        formElement.dispatchEvent(event);
        await new Promise(process.nextTick);

        expect(global.fetch).toHaveBeenCalledTimes(1);
    });
});
