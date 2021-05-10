import Plugin from 'src/plugin-system/plugin.class';
import HttpClient from 'src/service/http-client.service';
import ElementReplaceHelper from 'src/helper/element-replace.helper';
import ElementLoadingIndicatorUtil from 'src/utility/loading-indicator/element-loading-indicator.util';

export default class BasicCaptchaPlugin extends Plugin {
    static options = {
        router: '',
        captchaRefreshIconId: '#basic-captcha-content-refresh-icon',
        captchaImageId: '#basic-captcha-content-image',
        formId: ''
    }

    init() {
        this._httpClient = new HttpClient();
        this._onLoadBasicCaptcha();
        this._registerEvents();
    }

    /**
     * register all needed events
     *
     * @private
     */
    _registerEvents() {
        const refreshCaptchaButton = this.el.querySelector(this.options.captchaRefreshIconId);
        refreshCaptchaButton.addEventListener('click', this._onLoadBasicCaptcha.bind(this));
    }

    /**
     * @private
     */
    _onLoadBasicCaptcha() {
        const captchaImageId= this.el.querySelector(this.options.captchaImageId);
        ElementLoadingIndicatorUtil.create(captchaImageId);

        const url = `${this.options.router}?formId=${this.options.formId}`;
        this._httpClient.get(url, (response) => {
            ElementReplaceHelper.replaceFromMarkup(response, this.options.captchaImageId);
            ElementLoadingIndicatorUtil.remove(captchaImageId);
        });
    }
}
