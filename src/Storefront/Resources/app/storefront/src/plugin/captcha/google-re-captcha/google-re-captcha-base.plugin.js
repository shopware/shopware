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

        this.grecaptcha = window.grecaptcha;
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

        let ajaxSubmitFound = false;

        this.formPluginInstances.forEach(plugin => {
            if (typeof plugin.sendAjaxFormSubmit === 'function' && plugin.options.useAjax !== false) {
                ajaxSubmitFound = true;
                plugin.sendAjaxFormSubmit();
            }
        });

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
        this.formPluginInstances.forEach(plugin => {
            if (typeof plugin.sendAjaxFormSubmit === 'function' && plugin.options.useAjax !== false) {
                plugin.formSubmittedByCaptcha = true;
            }
        });
    }
}
