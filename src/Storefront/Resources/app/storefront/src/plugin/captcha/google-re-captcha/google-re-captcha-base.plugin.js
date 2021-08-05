import Plugin from 'src/plugin-system/plugin.class';

export default class GoogleReCaptchaBasePlugin extends Plugin {
    init() {
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
        if (!this.formPluginInstances) {
            this._form.addEventListener('submit', this._onFormSubmitCallback.bind(this));
        } else {
            this.formPluginInstances.forEach(plugin => {
                plugin.$emitter.subscribe('beforeSubmit', this._onFormSubmitCallback.bind(this));
            });
        }
    }

    _submitInvisibleForm() {
        if (!this._form.checkValidity()) {
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

    _onFormSubmitCallback() {
        if (this._formSubmitting) {
            return;
        }

        this._formSubmitting = true;

        this.onFormSubmit();
    }
}
