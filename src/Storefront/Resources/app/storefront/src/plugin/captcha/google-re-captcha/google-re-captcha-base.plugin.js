import Plugin from 'src/plugin-system/plugin.class';

export default class GoogleReCaptchaBasePlugin extends Plugin {
    init() {
        const recaptchaScript = document.getElementById('recaptcha-script');
        if (!recaptchaScript || recaptchaScript.hasAttribute('src')) {
            return;
        }

        const dataSrc = recaptchaScript.getAttribute('data-src');
        if (dataSrc && this._isValidUrl(dataSrc)) {
            recaptchaScript.setAttribute('src', encodeURI(dataSrc));
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
        this._form.addEventListener('submit', this._onFormSubmitCallback.bind(this));
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

        if (this._isCmsForm()) {
            const formCmsHandlerPlugin = this.formPluginInstances.get('FormCmsHandler');
            if (formCmsHandlerPlugin) {
                formCmsHandlerPlugin._submitForm();
                return;
            }
        }

        let ajaxSubmitFound = false;

        for (const plugin of this.formPluginInstances) {
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
            return;
        }

        event.preventDefault();

        this._formSubmitting = true;

        this.onFormSubmit();
    }

    _setGoogleReCaptchaHandleSubmit() {
        for (const plugin of this.formPluginInstances) {
            if (typeof plugin.sendAjaxFormSubmit === 'function' && plugin.options.useAjax !== false) {
                plugin.formSubmittedByCaptcha = true;
            }
        }
    }

    /**
     * Checks if the form is the CMS contact form.
     * This is used to work in association with the form CMS handler.
     *
     * @return {boolean}
     * @private
     */
    _isCmsForm() {
        return this.formPluginInstances.has('FormCmsHandler');
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
