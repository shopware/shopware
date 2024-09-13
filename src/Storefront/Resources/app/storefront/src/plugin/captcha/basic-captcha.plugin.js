import Plugin from 'src/plugin-system/plugin.class';
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
        formId: '',
        preCheck: false,
        preCheckId: '#precheck',
        preCheckRoute: {},
    };

    init() {
        this._getForm();

        if (!this._form) {
            return;
        }

        this._httpClient = new HttpClient();
        this._formSubmitting = false;
        this.formPluginInstances = window.PluginManager.getPluginInstancesFromElement(this._form);
        this._onLoadBasicCaptcha();
        this._registerEvents();
        this.formValidating = false;
    }

    /**
     * register all needed events
     *
     * @private
     */
    _registerEvents() {
        const refreshCaptchaButton = this.el.querySelector(this.options.captchaRefreshIconId);
        refreshCaptchaButton.addEventListener('click', this._onLoadBasicCaptcha.bind(this));

        this.formPluginInstances.forEach(plugin => {
            plugin.$emitter.subscribe('onFormResponse', res => this.onHandleResponse(res.detail));

            if (this.options.preCheck) {
                plugin.$emitter.subscribe('beforeSubmit', this._onValidate.bind(this));
            }
        });
    }

    /**
     * @private
     */
    _onLoadBasicCaptcha() {
        const captchaImageId = this.el.querySelector(this.options.captchaImageId);
        ElementLoadingIndicatorUtil.create(captchaImageId);

        const url = `${this.options.router}?formId=${this.options.formId}`;
        this._httpClient.get(url, (response) => {
            this.formValidating = false;
            const srcEl = new DOMParser().parseFromString(response, 'text/html');
            ElementReplaceHelper.replaceElement(srcEl.querySelector(this.options.captchaImageId), captchaImageId, true);
            ElementLoadingIndicatorUtil.remove(captchaImageId);
        });
    }

    /**
     * @private
     */
    _onValidate() {
        if (this.formValidating) {
            return;
        }

        this.formValidating = true;
        const data = JSON.stringify({
            formId: this.options.formId,
            shopware_basic_captcha_confirm: this.el.querySelector(this.options.basicCaptchaInputId).value,
        });
        this._httpClient.post(this.options.preCheckRoute.path, data, (res) => {
            this.formValidating = false;
            const response = JSON.parse(res);
            if (response.session) {
                this.onFormSubmit(response.session);
                return;
            }
            this.onHandleResponse(res);
        });
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

    onFormSubmit(fakeSession) {
        const preCheckId = `#${this.options.formId}-precheck`;
        this.el.querySelector(preCheckId).value = 'allowed';
        this.el.querySelector(this.options.basicCaptchaInputId).value = fakeSession;

        if (!this._form.checkValidity()) {
            this.el.querySelector(preCheckId).value = '';
            return;
        }

        this._form.submit();
    }

    onHandleResponse(res) {
        if (this.formValidating) {
            return;
        }
        this.formValidating = true;
        const response = JSON.parse(res)[0];
        if (response.error !== 'invalid_captcha') {
            return;
        }
        const basicCaptchaFieldId = this.el.querySelector(this.options.basicCaptchaFieldId);
        ElementLoadingIndicatorUtil.create(basicCaptchaFieldId);

        const srcEl = new DOMParser().parseFromString(response.input, 'text/html');
        ElementReplaceHelper.replaceElement(srcEl.querySelector(this.options.basicCaptchaFieldId), basicCaptchaFieldId);
        ElementLoadingIndicatorUtil.remove(basicCaptchaFieldId);
        this._onLoadBasicCaptcha();
    }
}
