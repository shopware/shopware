const CONTROL_SELECTOR = '.sw-form-field__control';
const FEEDBACK_SELECTOR = '.sw-form-feedback';
const INVALID_CLASS = 'is-invalid';
const ELEMENT_LOADER_CLASS = 'element-loader-backdrop';
const BUTTON_LOADER_CLASS = 'is-loading-indicator-inner';

/**
 * Owns the form submission lifecycle and is the only `submit` listener on the form.
 *
 * `Form:PreSubmit` interceptors can push promises onto `payload.tasks` to finish work such as a
 * captcha token or another request before the form is serialized. `Form:BeforeRequest` alters the
 * request itself and therefore only fires on the AJAX path.
 */
export default class Form extends ShopwareComponent {

    static options = {
        ajax: false,
        replaceSelectors: [],
        submitOnChange: false,
        validate: true,
        debounceTime: 200,
    };

    init() {
        this.isSubmitting = false;
        this.isNativeSubmit = false;
        this.hasPendingSubmit = false;
        this.buttonLoaders = [];
        this.elementLoaders = [];

        if (this.el.tagName !== 'FORM') {
            console.error('[Sw:Form]: Element is not of type <form>', this.el);
            return;
        }

        // Copied because the static default is shared by every instance that does not override it.
        const selectors = this.options.replaceSelectors;
        this.options.replaceSelectors = typeof selectors === 'string'
            ? [selectors]
            : (Array.isArray(selectors) ? [...selectors] : []);

        this.onSubmit = this.onSubmit.bind(this);
        this.onFieldChange = this.onFieldChange.bind(this);
        this.onPageShow = this.onPageShow.bind(this);
        this.onFieldInput = this.debounce((event) => this.validateField(event.target), this.options.debounceTime);

        this.el.addEventListener('submit', this.onSubmit);
        this.el.addEventListener('change', this.onFieldChange);
        window.addEventListener('pageshow', this.onPageShow);

        if (this.options.validate) {
            this.initValidation();
        }
    }

    destroy() {
        this.el.removeEventListener('submit', this.onSubmit);
        this.el.removeEventListener('change', this.onFieldChange);
        this.el.removeEventListener('input', this.onFieldInput);
        window.removeEventListener('pageshow', this.onPageShow);

        if (this.nativeCheckValidity) {
            this.el.checkValidity = this.nativeCheckValidity;
        }

        this.hasPendingSubmit = false;
        this.stopLoading();
    }

    /**
     * A native submission hands over to the browser and never runs its own cleanup, so the
     * back/forward cache would restore the DOM with the form still locked.
     */
    onPageShow(event) {
        if (!event.persisted || !this.isNativeSubmit) {
            return;
        }

        this.isNativeSubmit = false;
        this.isSubmitting = false;
        this.stopLoading();
    }

    /**
     * An interceptor that forgets to return the payload makes `emitInterception()` hand back
     * undefined. Throwing here, before `preventDefault()`, would let the browser navigate away.
     */
    intercept(eventName, payload) {
        const result = Shopware.emitInterception(eventName, payload);

        return result && typeof result === 'object' ? result : payload;
    }

    /**
     * The native browser validation is replaced with the accessible one, so `checkValidity()`
     * keeps telling the truth for anything that asks the form itself.
     */
    initValidation() {
        if (!window.formValidation) {
            return;
        }

        window.formValidation.setNoValidate(this.el);

        this.nativeCheckValidity = this.el.checkValidity.bind(this.el);
        this.el.checkValidity = () => this.validateForm().length === 0;

        this.el.addEventListener('input', this.onFieldInput);
    }

    validateField(field) {
        if (!this.options.validate || !window.formValidation || !(field instanceof HTMLElement)) {
            return;
        }

        window.formValidation.validateField(field);
    }

    /**
     * @returns {HTMLElement[]} the invalid fields, empty when the form is valid
     */
    validateForm() {
        if (!this.options.validate || !window.formValidation) {
            return [];
        }

        // The fields are collected on every run because they can be added asynchronously.
        const invalidFields = window.formValidation.validateForm(this.el, Array.from(this.el.elements));

        return Array.isArray(invalidFields) ? invalidFields : [];
    }

    onFieldChange(event) {
        this.validateField(event.target);

        if (!this.options.submitOnChange) {
            return;
        }

        if (Array.isArray(this.options.submitOnChange)
            && !this.options.submitOnChange.some(selector => event.target.matches(selector))) {
            return;
        }

        this.requestSubmit();
    }

    /**
     * State arriving mid-request is newer, not a duplicate, so it is submitted once the request
     * finishes rather than dropped. Repeated changes collapse into that single follow-up.
     */
    requestSubmit() {
        if (this.isSubmitting) {
            this.hasPendingSubmit = true;
            return;
        }

        if (typeof this.el.requestSubmit === 'function') {
            this.el.requestSubmit();
            return;
        }

        this.el.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    }

    onSubmit(event) {
        if (this.isSubmitting) {
            event.preventDefault();
            return;
        }

        const invalidFields = this.validateForm();

        if (invalidFields.length > 0) {
            event.preventDefault();

            // In Safari, focus alone may not scroll, so manual scrolling is needed.
            invalidFields[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            invalidFields[0].focus();

            Shopware.emit('Form:ValidationFailed', { form: this.el, invalidFields });

            return;
        }

        const { tasks } = this.intercept('Form:PreSubmit', { form: this.el, tasks: [] });
        const preSubmitTasks = Array.isArray(tasks) ? tasks : [];

        this.isSubmitting = true;
        this.startLoading();

        // No AJAX and nothing to await: let the browser submit.
        if (!this.options.ajax && preSubmitTasks.length === 0) {
            this.isNativeSubmit = true;
            return;
        }

        event.preventDefault();

        void this.submitForm(preSubmitTasks, event.submitter ?? null);
    }

    async submitForm(tasks, submitter) {
        try {
            if (tasks.length > 0) {
                await Promise.all(tasks);
            }

            if (!this.options.ajax) {
                // Bypasses the submit event, so the pipeline cannot re-enter itself.
                this.el.submit();
                return;
            }

            // Serialized after the pre-submit work so fields it wrote, such as a captcha token, are included.
            const { action, method, formData } = this.intercept('Form:BeforeRequest', {
                form: this.el,
                action: this.resolveAction(submitter),
                method: this.resolveMethod(submitter),
                formData: Shopware.serializeForm(this.el),
            });

            Shopware.emit('Form:Submit', { form: this.el, action, method, formData });

            const response = await this.sendRequest(action, method, formData);

            await this.handleResponse(response);
        } catch (error) {
            console.error('[Sw:Form]: Submission failed.', error);

            this.stopLoading();

            Shopware.emit('Form:Error', { form: this.el, error });
        } finally {
            this.isSubmitting = false;

            const hasPendingSubmit = this.hasPendingSubmit;
            this.hasPendingSubmit = false;

            // A replaced form is gone, and its replacement already reflects the newer state.
            if (hasPendingSubmit && this.el.isConnected) {
                this.requestSubmit();
            }
        }
    }

    sendRequest(action, method, formData) {
        const url = action || window.location.href;

        if (method === 'get') {
            const query = new URLSearchParams(formData).toString();

            return fetch(query ? `${url}${url.includes('?') ? '&' : '?'}${query}` : url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
        }

        return fetch(url, {
            method,
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
    }

    async handleResponse(response) {
        const isJson = (response.headers?.get('content-type') ?? '').includes('application/json');
        const payload = isJson ? await response.json() : await response.text();

        this.stopLoading();

        // Violations are a handled outcome rather than a failure: the fields carry the result.
        if (isJson && this.applyServerViolations(payload)) {
            return;
        }

        // Anything else that did not succeed is a failure, whatever shape its body has.
        if (!response.ok) {
            Shopware.emit('Form:Error', { form: this.el, response, payload });

            return;
        }

        if (isJson) {
            Shopware.emit('Form:Response', { form: this.el, payload });

            return;
        }

        if (this.options.replaceSelectors.length > 0) {
            this.replaceContent(payload);
        }

        Shopware.emit('Form:Response', { form: this.el, html: payload });
    }

    replaceContent(html) {
        const source = new DOMParser().parseFromString(html, 'text/html');

        this.options.replaceSelectors.forEach((selector) => {
            const sourceElements = source.querySelectorAll(selector);
            const targetElements = document.querySelectorAll(selector);

            targetElements.forEach((target, index) => {
                const replacement = sourceElements[index] ?? sourceElements[0];

                // As `ElementReplaceHelper` does: a response without the fragment must not wipe it.
                if (replacement?.innerHTML) {
                    target.innerHTML = replacement.innerHTML;
                }
            });
        });
    }

    /**
     * Maps violations onto their fields by matching `source.pointer` against `data-violation-path`.
     *
     * @returns {boolean} whether any violation was mapped
     */
    applyServerViolations(payload) {
        const errors = Array.isArray(payload?.errors) ? payload.errors : [];

        this.clearServerViolations();

        const invalidFields = [];

        errors.forEach((error) => {
            const pointer = error?.source?.pointer;

            if (!pointer || pointer.includes('"')) {
                return;
            }

            const field = this.el.querySelector(`[data-violation-path="${pointer}"]`);

            if (!field) {
                return;
            }

            const controls = this.resolveControls(field);

            controls.forEach((control) => {
                control.classList.add(INVALID_CLASS);
                control.setAttribute('aria-invalid', 'true');
                invalidFields.push(control);
            });

            const feedback = this.resolveFeedback(field, controls[0]);

            if (feedback && error.detail) {
                const message = document.createElement('div');
                message.className = 'sw-form-feedback__message invalid-feedback';
                message.textContent = error.detail;
                feedback.appendChild(message);
            }
        });

        if (invalidFields.length === 0) {
            return false;
        }

        invalidFields[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        invalidFields[0].focus();

        Shopware.emit('Form:ValidationFailed', { form: this.el, invalidFields });

        return true;
    }

    /**
     * Falls back to plain markup, so a field contributed from outside the `Sw:Form:*` set is
     * served too.
     *
     * @returns {HTMLElement[]}
     */
    resolveControls(field) {
        const controls = field.querySelectorAll(CONTROL_SELECTOR);

        return Array.from(controls.length > 0 ? controls : field.querySelectorAll('input, select, textarea'));
    }

    /** Falls back to the lookup `FormValidation` uses, so both follow the same contract. */
    resolveFeedback(field, control) {
        const feedback = field.querySelector(FEEDBACK_SELECTOR);

        if (feedback) {
            return feedback;
        }

        const describedBy = control?.getAttribute('aria-describedby') ?? '';
        const feedbackId = describedBy.split(' ').find(id => id.includes('feedback'));

        return feedbackId ? document.getElementById(feedbackId) : null;
    }

    /** Drops the previous round-trip's violations, server-rendered or mapped alike. */
    clearServerViolations() {
        this.el.querySelectorAll('[data-violation-path]').forEach((field) => {
            const controls = this.resolveControls(field);

            controls.forEach((control) => {
                control.classList.remove(INVALID_CLASS);
                control.removeAttribute('aria-invalid');
            });

            const feedback = this.resolveFeedback(field, controls[0]);

            if (feedback) {
                feedback.innerHTML = '';
            }
        });
    }

    /** Mirrors the markup of `LoadingIndicatorUtil` so the existing loader styling applies. */
    createLoader() {
        const loader = document.createElement('div');
        loader.className = 'loader';
        loader.setAttribute('role', 'status');

        const label = document.createElement('span');
        label.className = 'visually-hidden';
        label.textContent = 'Loading...';
        loader.appendChild(label);

        return loader;
    }

    startLoading() {
        this.el.setAttribute('aria-busy', 'true');

        this.getSubmitButtons().forEach((button) => {
            // The nodes are kept rather than the markup, so a component inside the button survives.
            this.buttonLoaders.push({ button, content: [...button.childNodes], width: button.style.width });

            // Keep the button from jumping in width while the loader replaces its content.
            button.style.width = `${button.getBoundingClientRect().width}px`;
            button.replaceChildren(this.createLoader());
            button.classList.add(BUTTON_LOADER_CLASS);
            button.disabled = true;
        });

        this.getReplaceTargets().forEach((target) => {
            target.classList.add('has-element-loader');
            this.elementLoaders.push(target);

            if (target.querySelector(`.${ELEMENT_LOADER_CLASS}`)) {
                return;
            }

            const backdrop = document.createElement('div');
            backdrop.className = ELEMENT_LOADER_CLASS;
            backdrop.appendChild(this.createLoader());
            target.appendChild(backdrop);

            window.setTimeout(() => {
                target.querySelector(`.${ELEMENT_LOADER_CLASS}`)?.classList.add('element-loader-backdrop-open');
            }, 1);
        });
    }

    stopLoading() {
        this.el.removeAttribute('aria-busy');

        this.buttonLoaders.forEach(({ button, content, width }) => {
            button.style.width = width;
            button.replaceChildren(...content);
            button.classList.remove(BUTTON_LOADER_CLASS);
            button.disabled = false;
        });

        this.elementLoaders.forEach((target) => {
            target.classList.remove('has-element-loader');
            target.querySelector(`.${ELEMENT_LOADER_CLASS}`)?.remove();
        });

        this.buttonLoaders = [];
        this.elementLoaders = [];
    }

    getReplaceTargets() {
        return this.options.replaceSelectors.flatMap(selector => Array.from(document.querySelectorAll(selector)));
    }

    /** Submit buttons can sit outside the form and reference it via their `form` attribute. */
    getSubmitButtons() {
        const buttons = Array.from(this.el.querySelectorAll('button[type="submit"]'));

        if (this.el.id) {
            buttons.push(...document.querySelectorAll(`button[type="submit"][form="${this.el.id}"]`));
        }

        return buttons;
    }

    resolveAction(submitter) {
        return submitter?.getAttribute('formaction') ?? this.el.getAttribute('action') ?? '';
    }

    resolveMethod(submitter) {
        const method = submitter?.getAttribute('formmethod') ?? this.el.getAttribute('method') ?? 'post';

        return method.toLowerCase() === 'get' ? 'get' : 'post';
    }
}