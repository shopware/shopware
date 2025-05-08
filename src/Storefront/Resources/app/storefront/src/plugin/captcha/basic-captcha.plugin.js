import Plugin from 'src/plugin-system/plugin.class';
/** @deprecated tag:v6.8.0 - HttpClient is deprecated. Use native fetch API instead. */
import HttpClient from 'src/service/http-client.service';
import ElementReplaceHelper from 'src/helper/element-replace.helper';
import ElementLoadingIndicatorUtil from 'src/utility/loading-indicator/element-loading-indicator.util';

export default class BasicCaptchaPlugin extends Plugin {
    static options = {
        router: '',
        captchaRefreshIconId: '#basic-captcha-content-refresh-icon',
        captchaImageId: '#basic-captcha-content-image',
        basicCaptchaInputId: '#basic-captcha-input',
        basicCaptchaFieldId: '#basic-captcha-field',
        invalidFeedbackMessage: 'Incorrect input. Please try again.',
        formId: '',
        preCheckRoute: {},
    };

    init() {
        this._getForm();

        if (!this._form) {
            return;
        }

        window.formValidation.addErrorMessage('basicCaptcha', this.options.invalidFeedbackMessage);

        this.formPluginInstances = window.PluginManager.getPluginInstancesFromElement(this._form);

        this.createFakeInput();

        /** @deprecated tag:v6.8.0 - HttpClient is deprecated. Use native fetch API instead. */
        this._httpClient = new HttpClient();
        this._onLoadBasicCaptcha();
        this._registerEvents();
    }

    /**
     * Creates a fake input which is required.
     * This ensures that the form stays invalid until the captcha is solved correctly.
     * It helps to create compatibility with other plugins that rely on the native `checkValidity()` method.
     */
    createFakeInput() {
        this.fakeInput = document.createElement('input');
        this.fakeInput.type = 'text';
        this.fakeInput.id = 'shopware_basic_captcha_check';
        this.fakeInput.name = 'shopware_basic_captcha_check';
        this.fakeInput.required = true;
        this.fakeInput.style.display = 'none';
        this.fakeInput.tabIndex = -1;
        this.fakeInput.ariaHidden = 'true';
        this.fakeInput.value = null;

        // Compatibility with the form validation helper and the form handler plugin.
        this.fakeInput.setAttribute('data-validate-hidden', 'true');

        this.el.appendChild(this.fakeInput);
    }

    /**
     * Registers the necessary event listeners.
     *
     * @private
     */
    _registerEvents() {
        const refreshCaptchaButton = this.el.querySelector(this.options.captchaRefreshIconId);
        refreshCaptchaButton.addEventListener('click', this._onLoadBasicCaptcha.bind(this));

        this._form.addEventListener('submit', this.validateCaptcha.bind(this));
    }

    /**
     * Fetches a new captcha image and replaces it within the form markup.
     * Is called by the refresh action of the user or if validation of the current captcha failed.
     *
     * @private
     */
    _onLoadBasicCaptcha() {
        const captchaImageId = this.el.querySelector(this.options.captchaImageId);
        ElementLoadingIndicatorUtil.create(captchaImageId);

        const url = `${this.options.router}?formId=${this.options.formId}`;

        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(response => response.text())
            .then(content => {
                this.formValidating = false;
                const srcEl = new DOMParser().parseFromString(content, 'text/html');
                ElementReplaceHelper.replaceElement(srcEl.querySelector(this.options.captchaImageId), captchaImageId);
                ElementLoadingIndicatorUtil.remove(captchaImageId);
            });
    }

    /**
     * Validates the captcha via server request.
     * It checks if the captcha value is correct in association to the form id.
     * Called on form submit.
     *
     * @return {Promise<boolean>}
     */
    async validateCaptcha(event) {
        event.preventDefault();

        const captchaInput = this.el.querySelector(this.options.basicCaptchaInputId);
        const captchaValue = captchaInput.value;
        const data = JSON.stringify({
            formId: this.options.formId,
            shopware_basic_captcha_confirm: captchaValue,
        });

        const response = await fetch(this.options.preCheckRoute.path, {
            method: 'POST',
            body: data,
            headers: { 'Content-Type': 'application/json' },
        });

        const content = await response.json();
        const validCaptcha = !!content.session;

        if (!validCaptcha) {
            // Captcha input will be marked as invalid.
            window.formValidation.setFieldInvalid(captchaInput, ['basicCaptcha']);

            // Reset the fake input value so the form stays invalid.
            this.fakeInput.value = null;

            // Captcha code is always updated with new image if the validation failed.
            this._onLoadBasicCaptcha();

            // Remove loading indicators in the case the form uses them.
            // This event is triggering the corresponding logic in the form handler plugin.
            this._form.dispatchEvent(new CustomEvent('removeLoader'));

            return;
        }

        // If the captcha is valid, the fake input is also filled so the native form validation succeeds.
        this.fakeInput.value = captchaValue;

        const validForm = this._form.checkValidity();

        if (validCaptcha && validForm) {
            if (this._isCmsForm()) {
                // Compatibility with the CMS form handler plugin which does an async form submit.
                const formCmsHandlerPlugin = this.formPluginInstances.get('FormCmsHandler');
                formCmsHandlerPlugin._submitForm();
            } else {
                // Normal form submit.
                this._form.submit();
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

    /**
     * tries to get the closest form
     *
     * @returns {HTMLElement|boolean}
     * @private
     */
    _getForm() {
        if (this.el && this.el.nodeName === 'FORM') {
            this._form = this.el;
        } else {
            this._form = this.el.closest('form');
        }
    }
}
