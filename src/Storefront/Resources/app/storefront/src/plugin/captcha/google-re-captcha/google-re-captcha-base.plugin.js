import Plugin from 'src/plugin-system/plugin.class';

export default class GoogleReCaptchaBasePlugin extends Plugin {
    init() {
        const recaptchaScript = document.getElementById('recaptcha-script');
        if (!recaptchaScript) {
            return;
        }

        if (!recaptchaScript.hasAttribute('src')) {
            const dataSrc = recaptchaScript.getAttribute('data-src');
            if (dataSrc && this._isValidUrl(dataSrc)) {
                recaptchaScript.setAttribute('src', encodeURI(dataSrc));
            }
        }

        // The shim script in main.js ensures window.grecaptcha and window.grecaptcha.ready exist.
        // The callback .bind(this) ensures 'this' context is correct in _executeGoogleReCaptchaInitialization.
        if (window.grecaptcha && typeof window.grecaptcha.ready === 'function') {
            window.grecaptcha.ready(this._executeGoogleReCaptchaInitialization.bind(this));
        }
    }

    _executeGoogleReCaptchaInitialization() {
        this._getForm();

        if (!this._form) {
            return;
        }

        this.grecaptchaInput = this.el.querySelector(this.options.grecaptchaInputSelector);

        if (!this.grecaptchaInput) {
            throw new Error('Input field for Google reCAPTCHA is missing!');
        }

        // this.grecaptcha should be set by the time grecaptcha.ready's callback executes.
        this.grecaptcha = window.grecaptcha;
        if (!this.grecaptcha || (typeof this.grecaptcha.render !== 'function' && typeof this.grecaptcha.execute !== 'function')) {
            throw new Error('Google reCAPTCHA object (window.grecaptcha) methods (render/execute) not available.');
        }

        this._formSubmitting = false;
        this.formPluginInstances = window.PluginManager.getPluginInstancesFromElement(this._form);

        this._setGoogleReCaptchaHandleSubmit();
        this._registerEvents();
    }

    getGreCaptchaInfo() {
        // handle by child plugin
    }

    /**
     * Handle form submit event manually by preventing the usual form submission first.
     * Show loading indicator after submitting the order
     */
    onFormSubmit() {
        // handle by child plugin
    }

    /**
     * Resets the captcha after the form was submitted via AJAX. Concrete captcha versions
     * override this to clear their widget state. Versions that already request a fresh token on
     * every submit (e.g. reCAPTCHA v3) do not need to reset.
     */
    resetGreCaptcha() {
        // handle by child plugin
    }

    /**
     * tries to get the closest form
     *
     * @returns {HTMLElement|boolean}
     * @private
     */
    _getForm() {
        if (this.el && this.el.nodeName === 'FORM') {
            this._form = this.el;
            return true;
        }

        this._form = this.el.closest('form');

        return this._form;
    }

    _registerEvents() {
        this._form.addEventListener('submit', this._onFormSubmitCallback.bind(this), { capture: true });

        // Once the form's AJAX submission returns, the round-trip is complete and any captcha
        // token that was sent has been consumed. Re-enable submitting and reset the captcha so a
        // single-use token is never sent twice. This matters when the form stays on screen after
        // a server-side validation error: the `_captcha` route flag validates (and consumes) the
        // token before the remaining fields are validated, so a failed field validation would
        // otherwise leave a stale token behind that fails on the next submission.
        this._onFormAjaxResponseCallback = this._onFormAjaxResponse.bind(this);
        this._form.addEventListener('onFormResponse', this._onFormAjaxResponseCallback);
        this._form.addEventListener('onAfterAjaxSubmit', this._onFormAjaxResponseCallback);
    }

    /**
     * Handles the completion of an AJAX form submission emitted by the form handler plugins.
     *
     * @private
     */
    _onFormAjaxResponse() {
        this._formSubmitting = false;
        this.resetGreCaptcha();
    }

    destroy() {
        if (this._form && this._onFormAjaxResponseCallback) {
            this._form.removeEventListener('onFormResponse', this._onFormAjaxResponseCallback);
            this._form.removeEventListener('onAfterAjaxSubmit', this._onFormAjaxResponseCallback);
        }
    }

    _submitInvisibleForm() {
        if (!this._form.checkValidity()) {
            this._formSubmitting = false;
            return;
        }

        this.$emitter.publish('beforeGreCaptchaFormSubmit', {
            info: this.getGreCaptchaInfo(),
            token: this.grecaptchaInput.value,
        });

        let ajaxSubmitFound = false;

        for (const plugin of this.formPluginInstances.values()) {
            if (typeof plugin.sendAjaxFormSubmit === 'function' && plugin.options.useAjax !== false) {
                ajaxSubmitFound = true;
                plugin.sendAjaxFormSubmit();
            }
        }

        if (ajaxSubmitFound) {
            return;
        }

        this._form.submit();
    }

    _onFormSubmitCallback(event) {
        if (this._formSubmitting) {
            event.preventDefault();
            event.stopImmediatePropagation();
            return;
        }

        event.preventDefault();

        this._formSubmitting = true;

        this.onFormSubmit();
    }

    _setGoogleReCaptchaHandleSubmit() {
        for (const plugin of this.formPluginInstances.values()) {
            if (typeof plugin.sendAjaxFormSubmit === 'function' && plugin.options.useAjax !== false) {
                plugin.formSubmittedByCaptcha = true;
            }
        }
    }

    _isValidUrl(url) {
        try {
            const parsedUrl = new URL(url);
            return ['http:', 'https:'].includes(parsedUrl.protocol);
        } catch (e) {
            return false;
        }
    }
}
