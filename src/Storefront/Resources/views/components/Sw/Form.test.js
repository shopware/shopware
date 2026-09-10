import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
// Import order is load-bearing: this module's side effect installs the `globalThis.ShopwareComponent`
// that the component below extends as a bare global while its own module is evaluated.
import { Shopware } from 'shopware';
import Form from './Form';

function htmlResponse(body, { ok = true, status = 200 } = {}) {
    return {
        ok,
        status,
        headers: { get: () => 'text/html; charset=UTF-8' },
        text: () => Promise.resolve(body),
    };
}

function jsonResponse(payload, { status = 400 } = {}) {
    return {
        ok: status < 400,
        status,
        headers: { get: () => 'application/json' },
        json: () => Promise.resolve(payload),
    };
}

function createForm(innerHtml, options = {}, attributes = {}) {
    const el = document.createElement('form');
    el.setAttribute('action', '/some-path');
    el.setAttribute('method', 'post');

    Object.entries(attributes).forEach(([name, value]) => el.setAttribute(name, value));

    el.innerHTML = innerHtml;
    document.body.appendChild(el);

    // The `ShopwareComponent` test double does not call `init()` from its constructor.
    const component = new Form(el, { ...Form.options, ...options });
    component.init();

    return { component, el };
}

function submit(el) {
    return el.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
}

/** A `pageshow` carrying `persisted`, which is what a back/forward cache restore looks like. */
function restoreEvent() {
    const event = new Event('pageshow');
    Object.defineProperty(event, 'persisted', { value: true });

    return event;
}

function appendHidden(el, name, value) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    el.appendChild(input);
}

function field(name, { violationPath = null, validation = null } = {}) {
    return `
        <div class="sw-form-input sw-form-field form-group"${violationPath ? ` data-violation-path="${violationPath}"` : ''}>
            <input class="sw-form-input__control sw-form-field__control form-control"
                   id="${name}" name="${name}" aria-describedby="${name}-feedback"
                   ${validation ? `data-validation="${validation}"` : ''}>
            <div class="sw-form-feedback form-field-feedback" id="${name}-feedback"></div>
        </div>`;
}

describe('Sw:Form', () => {
    let invalidFields;

    beforeEach(() => {
        invalidFields = [];

        window.formValidation = {
            setNoValidate: vi.fn(el => el.setAttribute('novalidate', 'true')),
            validateField: vi.fn(),
            validateForm: vi.fn(() => invalidFields),
        };

        window.fetch = vi.fn(() => Promise.resolve(htmlResponse('<html></html>')));
        Element.prototype.scrollIntoView = vi.fn();

        Shopware.emit.mockClear();
        Shopware.emitInterception.mockClear();
        Shopware.emitInterception.mockImplementation((_event, payload) => payload);
        Shopware.serializeForm.mockImplementation(form => new FormData(form));
    });

    afterEach(() => {
        document.body.innerHTML = '';
        delete window.formValidation;
        delete window.PluginManager;
        delete window.focusHandler;
        vi.restoreAllMocks();
    });

    it('replaces the native browser validation with the accessible one', () => {
        const { el } = createForm(field('title'));

        expect(el.getAttribute('novalidate')).toBe('true');
        expect(el.checkValidity()).toBe(true);

        invalidFields = [el.querySelector('input')];

        expect(el.checkValidity()).toBe(false);
    });

    it('leaves a valid submission of a non-ajax form to the browser', () => {
        const { el } = createForm(`${field('title')}<button type="submit">Save</button>`);

        const notPrevented = submit(el);

        expect(notPrevented).toBe(true);
        expect(window.fetch).not.toHaveBeenCalled();
        expect(el.querySelector('button').disabled).toBe(true);
    });

    it('blocks an invalid submission and moves the focus to the first invalid field', () => {
        const { el } = createForm(field('title', { validation: 'required' }));
        const control = el.querySelector('input');
        invalidFields = [control];

        const notPrevented = submit(el);

        expect(notPrevented).toBe(false);
        expect(window.fetch).not.toHaveBeenCalled();
        expect(document.activeElement).toBe(control);
        expect(Shopware.emit).toHaveBeenCalledWith('Form:ValidationFailed', { form: el, invalidFields: [control] });
    });

    it('sends the form fields when submitting via ajax', async () => {
        const { el } = createForm(field('title'), { ajax: true });
        el.querySelector('input').value = 'Great product';

        const notPrevented = submit(el);

        expect(notPrevented).toBe(false);
        await vi.waitFor(() => expect(window.fetch).toHaveBeenCalled());

        const [url, request] = window.fetch.mock.calls[0];
        expect(url).toBe('/some-path');
        expect(request.method).toBe('post');
        expect(request.headers['X-Requested-With']).toBe('XMLHttpRequest');
        expect(request.body.get('title')).toBe('Great product');
    });

    it('carries the fields as query parameters on a get submission', async () => {
        const { el } = createForm(field('title'), { ajax: true }, { method: 'get' });
        el.querySelector('input').value = 'sw';

        submit(el);

        await vi.waitFor(() => expect(window.fetch).toHaveBeenCalled());
        expect(window.fetch.mock.calls[0][0]).toBe('/some-path?title=sw');
        expect(window.fetch.mock.calls[0][1].body).toBeUndefined();
    });

    it('takes the action and method of the submitter over the ones of the form', async () => {
        const { el } = createForm(`${field('title')}<button type="submit" formaction="/other-path" formmethod="GET">Save</button>`, { ajax: true });

        const event = new Event('submit', { cancelable: true, bubbles: true });
        Object.defineProperty(event, 'submitter', { value: el.querySelector('button') });
        el.dispatchEvent(event);

        await vi.waitFor(() => expect(window.fetch).toHaveBeenCalled());
        expect(window.fetch.mock.calls[0][0]).toContain('/other-path');
    });

    // How captcha and comparable async work join in without intercepting the submit event.
    it('waits for every pre-submit task and sends what they wrote in one request', async () => {
        const { el } = createForm(field('title'), { ajax: true });

        let releaseToken;
        Shopware.emitInterception.mockImplementation((event, payload) => {
            if (event === 'Form:PreSubmit') {
                payload.tasks.push(new Promise((resolve) => {
                    releaseToken = () => {
                        appendHidden(el, '_grecaptcha_v3', 'token-from-task');
                        resolve();
                    };
                }));
                payload.tasks.push(Promise.resolve().then(() => appendHidden(el, 'second', 'b')));
            }

            return payload;
        });

        submit(el);

        await Promise.resolve();
        expect(window.fetch).not.toHaveBeenCalled();

        releaseToken();

        await vi.waitFor(() => expect(window.fetch).toHaveBeenCalledOnce());
        expect(window.fetch.mock.calls[0][1].body.get('_grecaptcha_v3')).toBe('token-from-task');
        expect(window.fetch.mock.calls[0][1].body.get('second')).toBe('b');
    });

    it('lets an interceptor change the request right before it is sent', async () => {
        const { el } = createForm(field('title'), { ajax: true });

        Shopware.emitInterception.mockImplementation((event, payload) => {
            if (event === 'Form:BeforeRequest') {
                payload.action = '/intercepted';
                payload.formData.append('extra', 'value');
            }

            return payload;
        });

        submit(el);

        await vi.waitFor(() => expect(window.fetch).toHaveBeenCalled());
        expect(window.fetch.mock.calls[0][0]).toBe('/intercepted');
        expect(window.fetch.mock.calls[0][1].body.get('extra')).toBe('value');
    });

    it('keeps a single submission in flight and restores the submit button afterwards', async () => {
        const { el } = createForm(`${field('title')}<button type="submit">Save</button>`, { ajax: true });
        const button = el.querySelector('button');

        submit(el);
        submit(el);

        expect(button.disabled).toBe(true);
        expect(button.innerHTML).toContain('loader');

        await vi.waitFor(() => expect(window.fetch).toHaveBeenCalledOnce());
        await vi.waitFor(() => expect(button.disabled).toBe(false));
        expect(button.innerHTML).toBe('Save');
    });

    // Round-tripping the button through innerHTML would rebuild its children, tearing down any
    // component instance inside it.
    it('keeps the nodes inside a submit button alive across the loading state', async () => {
        const { el } = createForm(
            `${field('title')}<button type="submit"><span class="sw-icon">icon</span>Save</button>`,
            { ajax: true },
        );
        const icon = el.querySelector('.sw-icon');

        submit(el);
        expect(el.querySelector('.sw-icon')).toBeNull();

        await vi.waitFor(() => expect(el.querySelector('button').disabled).toBe(false));

        expect(el.querySelector('.sw-icon')).toBe(icon);
    });

    it('restores the submit button and reports the failure when the request fails', async () => {
        window.fetch = vi.fn(() => Promise.reject(new Error('offline')));
        vi.spyOn(console, 'error').mockImplementation(() => {});

        const { el } = createForm(`${field('title')}<button type="submit">Save</button>`, { ajax: true });

        submit(el);

        await vi.waitFor(() => expect(el.querySelector('button').disabled).toBe(false));
        expect(Shopware.emit).toHaveBeenCalledWith('Form:Error', expect.objectContaining({ form: el }));
    });

    it('replaces the configured fragments of the response and hands the legacy plugins their entry point back', async () => {
        const container = document.createElement('div');
        container.className = 'js-review-container';
        container.innerHTML = '<p>old</p>';
        document.body.appendChild(container);

        window.PluginManager = { initializePlugins: vi.fn() };
        window.fetch = vi.fn(() => Promise.resolve(htmlResponse(
            '<html><body><div class="js-review-container"><p>new</p></div></body></html>',
        )));

        const { el } = createForm(field('title'), { ajax: true, replaceSelectors: ['.js-review-container'] });

        submit(el);

        await vi.waitFor(() => expect(container.innerHTML).toBe('<p>new</p>'));
        expect(window.PluginManager.initializePlugins).toHaveBeenCalled();
        expect(container.classList.contains('has-element-loader')).toBe(false);
        expect(Shopware.emit).toHaveBeenCalledWith('Form:Response', expect.objectContaining({ form: el }));
    });

    it('covers the replaced fragments with a loading indicator while the request runs', () => {
        const container = document.createElement('div');
        container.className = 'js-review-container';
        document.body.appendChild(container);

        const { el } = createForm(field('title'), { ajax: true, replaceSelectors: ['.js-review-container'] });

        submit(el);

        expect(container.classList.contains('has-element-loader')).toBe(true);
        expect(container.querySelector('.element-loader-backdrop')).not.toBeNull();
    });

    it('submits when a field changes', () => {
        const { el } = createForm(field('sort'), { ajax: true, submitOnChange: true });

        el.querySelector('input').dispatchEvent(new Event('change', { bubbles: true }));

        expect(window.fetch).toHaveBeenCalled();
    });

    it('submits on change only for the configured fields', () => {
        const { el } = createForm(`${field('sort')}${field('language')}`, {
            ajax: true,
            submitOnChange: ['[name="language"]'],
        });

        el.querySelector('[name="sort"]').dispatchEvent(new Event('change', { bubbles: true }));
        expect(window.fetch).not.toHaveBeenCalled();

        el.querySelector('[name="language"]').dispatchEvent(new Event('change', { bubbles: true }));
        expect(window.fetch).toHaveBeenCalled();
    });

    it('turns a pagination click into a submission of the requested page', async () => {
        window.focusHandler = { saveFocusState: vi.fn(), resumeFocusState: vi.fn() };

        const { el } = createForm(
            '<input type="hidden" name="p" value="1">'
            + '<nav class="pagination"><a class="page-link" data-page="3" data-focus-id="3" href="#">3</a></nav>',
            { ajax: true, pagination: true, replaceSelectors: ['.js-review-container'] },
        );

        el.querySelector('.page-link').dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));

        expect(el.querySelector('[name="p"]').value).toBe('3');
        expect(window.focusHandler.saveFocusState).toHaveBeenCalledWith('sw-form-pagination', '[data-focus-id="3"]');

        await vi.waitFor(() => expect(window.fetch).toHaveBeenCalled());
        expect(window.fetch.mock.calls[0][1].body.get('p')).toBe('3');
        await vi.waitFor(() => expect(window.focusHandler.resumeFocusState).toHaveBeenCalledWith('sw-form-pagination'));
    });

    it('maps the violations of an error response onto the fields that answer for them', async () => {
        window.fetch = vi.fn(() => Promise.resolve(jsonResponse({
            errors: [
                { detail: 'Input should not be empty.', source: { pointer: '/title' } },
                { detail: 'Nobody answers for this.', source: { pointer: '/unknown' } },
            ],
        })));

        const { el } = createForm(
            `${field('title', { violationPath: '/title' })}${field('content', { violationPath: '/content' })}`,
            { ajax: true },
        );

        submit(el);

        await vi.waitFor(() => expect(el.querySelector('#title-feedback').textContent).toContain('Input should not be empty.'));

        const title = el.querySelector('[name="title"]');
        expect(title.classList.contains('is-invalid')).toBe(true);
        expect(title.getAttribute('aria-invalid')).toBe('true');

        const content = el.querySelector('[name="content"]');
        expect(content.classList.contains('is-invalid')).toBe(false);
        expect(el.querySelector('#content-feedback').textContent).toBe('');

        expect(Shopware.emit).toHaveBeenCalledWith('Form:ValidationFailed', expect.objectContaining({ form: el }));
    });

    it('drops the violations of the previous round-trip before applying the new ones', async () => {
        window.fetch = vi.fn(() => Promise.resolve(jsonResponse({ errors: [] }, { status: 200 })));

        const { el } = createForm(field('title', { violationPath: '/title' }), { ajax: true });

        const control = el.querySelector('[name="title"]');
        control.classList.add('is-invalid');
        control.setAttribute('aria-invalid', 'true');
        el.querySelector('#title-feedback').innerHTML = '<div class="invalid-feedback">Stale</div>';

        submit(el);

        await vi.waitFor(() => expect(el.querySelector('#title-feedback').innerHTML).toBe(''));
        expect(control.classList.contains('is-invalid')).toBe(false);
        expect(control.hasAttribute('aria-invalid')).toBe(false);
        expect(Shopware.emit).toHaveBeenCalledWith('Form:Response', expect.objectContaining({ form: el }));
    });

    it('stops listening and clears its loading state when it is destroyed', () => {
        const { component, el } = createForm(`${field('title')}<button type="submit">Save</button>`, { ajax: true });

        submit(el);
        component.destroy();

        expect(el.querySelector('button').disabled).toBe(false);
        expect(el.hasAttribute('aria-busy')).toBe(false);

        window.fetch.mockClear();
        submit(el);
        expect(window.fetch).not.toHaveBeenCalled();
    });

    // An interceptor that mutates the payload but forgets `return payload` makes
    // `emitInterception()` hand back undefined. Before the fallback that threw inside the submit
    // handler ahead of `preventDefault()`, so the browser navigated away instead of submitting.
    it('keeps submitting when an interceptor forgets to return the payload', async () => {
        const { el } = createForm(field('title'), { ajax: true });

        Shopware.emitInterception.mockImplementation((event, payload) => {
            if (event === 'Form:PreSubmit') {
                payload.tasks.push(Promise.resolve());

                return undefined;
            }

            return payload;
        });

        const notPrevented = submit(el);

        expect(notPrevented).toBe(false);
        await vi.waitFor(() => expect(window.fetch).toHaveBeenCalledOnce());
        expect(window.fetch.mock.calls[0][0]).toBe('/some-path');
    });

    // The captcha shape is what `ErrorController::onCaptchaFailure()` answers an XHR with. It
    // carries no `errors` key, so reading the body alone made a rejected captcha look like success.
    it.each([
        ['an error object', { message: 'nope' }, 500],
        ['the captcha shape', [{ type: 'danger', error: 'invalid_captcha', alert: '<div>Invalid</div>' }], 403],
    ])('reports a failed response carrying %s as an error, and stays usable', async (_name, payload, status) => {
        window.fetch = vi.fn(() => Promise.resolve(jsonResponse(payload, { status })));

        const { el } = createForm(`${field('title')}<button type="submit">Save</button>`, { ajax: true });

        submit(el);

        await vi.waitFor(() => expect(Shopware.emit).toHaveBeenCalledWith(
            'Form:Error',
            expect.objectContaining({ form: el, payload }),
        ));

        expect(Shopware.emit).not.toHaveBeenCalledWith('Form:Response', expect.anything());
        expect(el.querySelector('button').disabled).toBe(false);
        expect(el.hasAttribute('aria-busy')).toBe(false);

        window.fetch.mockClear();
        submit(el);
        expect(window.fetch).toHaveBeenCalledOnce();
    });

    it('collapses changes made during a request into one follow-up with the latest values', async () => {
        const pending = [];
        window.fetch = vi.fn(() => new Promise(resolve => pending.push(resolve)));

        const { el } = createForm(field('sort'), { ajax: true, submitOnChange: true });
        const control = el.querySelector('[name="sort"]');

        control.value = 'first';
        control.dispatchEvent(new Event('change', { bubbles: true }));
        expect(window.fetch).toHaveBeenCalledOnce();
        expect(window.fetch.mock.calls[0][1].body.get('sort')).toBe('first');

        ['a', 'b', 'latest'].forEach((value) => {
            control.value = value;
            control.dispatchEvent(new Event('change', { bubbles: true }));
        });
        expect(window.fetch).toHaveBeenCalledOnce();

        pending[0](htmlResponse('<html></html>'));
        await vi.waitFor(() => expect(window.fetch).toHaveBeenCalledTimes(2));
        expect(window.fetch.mock.calls[1][1].body.get('sort')).toBe('latest');

        pending[1](htmlResponse('<html></html>'));
        await new Promise(resolve => setTimeout(resolve, 10));
        expect(window.fetch).toHaveBeenCalledTimes(2);
    });

    // A repeated click is a duplicate, not newer state, so it must not queue a follow-up.
    it('does not repeat a duplicate submit after the request finishes', async () => {
        const pending = [];
        window.fetch = vi.fn(() => new Promise(resolve => pending.push(resolve)));

        const { el } = createForm(`${field('title')}<button type="submit">Save</button>`, { ajax: true });

        submit(el);
        submit(el);
        submit(el);

        pending[0](htmlResponse('<html></html>'));
        await new Promise(resolve => setTimeout(resolve, 10));

        expect(window.fetch).toHaveBeenCalledOnce();
    });

    it('unlocks a natively submitted form when the page is restored from the back/forward cache', () => {
        const { el } = createForm(`${field('title')}<button type="submit">Save</button>`);
        const button = el.querySelector('button');

        expect(submit(el)).toBe(true);
        expect(button.disabled).toBe(true);
        expect(el.getAttribute('aria-busy')).toBe('true');

        window.dispatchEvent(restoreEvent());

        expect(button.disabled).toBe(false);
        expect(button.innerHTML).toBe('Save');
        expect(el.hasAttribute('aria-busy')).toBe(false);

        expect(submit(el)).toBe(true);
        expect(button.disabled).toBe(true);
    });

    it('leaves a running ajax submission alone when the page is restored', () => {
        window.fetch = vi.fn(() => new Promise(() => {}));

        const { el } = createForm(`${field('title')}<button type="submit">Save</button>`, { ajax: true });

        submit(el);
        window.dispatchEvent(restoreEvent());

        expect(el.querySelector('button').disabled).toBe(true);
    });

    it('aborts the submission and cleans up when a pre-submit task rejects', async () => {
        vi.spyOn(console, 'error').mockImplementation(() => {});

        const { el } = createForm(`${field('title')}<button type="submit">Save</button>`, { ajax: true });

        Shopware.emitInterception.mockImplementationOnce((_event, payload) => {
            payload.tasks.push(Promise.reject(new Error('captcha refused')));

            return payload;
        });

        submit(el);

        await vi.waitFor(() => expect(Shopware.emit).toHaveBeenCalledWith(
            'Form:Error',
            expect.objectContaining({ form: el }),
        ));

        expect(window.fetch).not.toHaveBeenCalled();
        expect(el.querySelector('button').disabled).toBe(false);
        expect(el.hasAttribute('aria-busy')).toBe(false);

        submit(el);
        await vi.waitFor(() => expect(window.fetch).toHaveBeenCalledOnce());
    });

    // `PreSubmit` used to carry `action`/`method`, which a native submission silently ignored
    // because the browser submits from the form's own attributes. Only the request stage has them.
    it('keeps the request target out of the pre-submit payload', () => {
        const { el } = createForm(field('title'), { ajax: true });
        const payloads = {};

        Shopware.emitInterception.mockImplementation((event, payload) => {
            payloads[event] = payload;

            return payload;
        });

        submit(el);

        expect(payloads['Form:PreSubmit']).toEqual({ form: el, tasks: [] });
        expect(payloads['Form:BeforeRequest']).toEqual(expect.objectContaining({
            action: '/some-path',
            method: 'post',
        }));
    });

    it('writes a violation into a feedback element that is only referenced by aria-describedby', async () => {
        window.fetch = vi.fn(() => Promise.resolve(jsonResponse({
            errors: [{ detail: 'Captcha is invalid.', source: { pointer: '/_captcha' } }],
        })));

        // Shaped like the reCAPTCHA field: no `sw-` classes, but the same accessibility contract.
        const { el } = createForm(`
            <div data-violation-path="/_captcha">
                <input name="_captcha" class="grecaptcha_v3-input" aria-describedby="captcha-feedback">
                <div id="captcha-feedback" class="form-field-feedback"></div>
            </div>`, { ajax: true });

        submit(el);

        await vi.waitFor(() => expect(el.querySelector('#captcha-feedback').textContent).toContain('Captcha is invalid.'));
        expect(el.querySelector('[name="_captcha"]').getAttribute('aria-invalid')).toBe('true');
    });

    // `ElementReplaceHelper` skips empty source fragments so a response that does not carry one
    // cannot wipe what is on the page.
    it('leaves a fragment alone when the response carries an empty one', async () => {
        const container = document.createElement('div');
        container.className = 'js-review-container';
        container.innerHTML = '<p>keep me</p>';
        document.body.appendChild(container);

        window.fetch = vi.fn(() => Promise.resolve(htmlResponse(
            '<html><body><div class="js-review-container"></div></body></html>',
        )));

        const { el } = createForm(field('title'), { ajax: true, replaceSelectors: ['.js-review-container'] });

        submit(el);

        await vi.waitFor(() => expect(Shopware.emit).toHaveBeenCalledWith('Form:Response', expect.anything()));
        expect(container.innerHTML).toBe('<p>keep me</p>');
    });

    it('keeps an ajax form and a native form on the same page independent', async () => {
        const pending = [];
        window.fetch = vi.fn(() => new Promise(resolve => pending.push(resolve)));

        const container = document.createElement('div');
        container.className = 'js-review-container';
        document.body.appendChild(container);

        const review = createForm(`${field('title')}<button type="submit">Save review</button>`, {
            ajax: true,
            replaceSelectors: ['.js-review-container'],
        });
        const login = createForm(`${field('email')}<button type="submit">Log in</button>`);

        submit(review.el);

        expect(review.el.querySelector('button').disabled).toBe(true);
        expect(container.classList.contains('has-element-loader')).toBe(true);

        expect(login.el.querySelector('button').disabled).toBe(false);
        expect(login.el.querySelector('button').innerHTML).toBe('Log in');
        expect(login.el.hasAttribute('aria-busy')).toBe(false);

        // The native form still submits while the other one waits for its response.
        expect(submit(login.el)).toBe(true);
        expect(window.fetch).toHaveBeenCalledOnce();

        pending[0](htmlResponse('<html></html>'));

        await vi.waitFor(() => expect(review.el.querySelector('button').disabled).toBe(false));
        expect(login.el.querySelector('button').disabled).toBe(true);
    });

    it('keeps a submit-on-change form and a plain form independent', async () => {
        const pending = [];
        window.fetch = vi.fn(() => new Promise(resolve => pending.push(resolve)));

        const filter = createForm(field('points'), { ajax: true, submitOnChange: true });
        const plain = createForm(`${field('title')}<button type="submit">Save</button>`, { ajax: true });

        const points = filter.el.querySelector('[name="points"]');
        points.dispatchEvent(new Event('change', { bubbles: true }));
        points.dispatchEvent(new Event('change', { bubbles: true }));

        // A change in one form submits only that form, and only once while it is in flight.
        expect(window.fetch).toHaveBeenCalledOnce();
        expect(window.fetch.mock.calls[0][1].body.has('points')).toBe(true);
        expect(plain.el.querySelector('button').disabled).toBe(false);

        pending[0](htmlResponse('<html></html>'));
        await vi.waitFor(() => expect(window.fetch).toHaveBeenCalledTimes(2));

        // The follow-up belongs to the filter form; the plain one never submitted.
        expect(window.fetch.mock.calls[1][1].body.has('points')).toBe(true);
        expect(plain.el.querySelector('button').disabled).toBe(false);
    });
});