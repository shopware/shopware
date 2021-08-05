import DomAccess from 'src/helper/dom-access.helper';
import GoogleReCaptchaBasePlugin from 'src/plugin/captcha/google-re-captcha/google-re-captcha-base.plugin';

export default class GoogleReCaptchaV2Plugin extends GoogleReCaptchaBasePlugin
{
    static options = {
        grecaptchaInputSelector: '.grecaptcha-v2-input',
        checkboxContainer: '.grecaptcha-v2-container',
        grecaptchaIframeHasErrorClassSelector: 'has-error',
        siteKey: null,
        invisible: false,
    };

    init() {
        super.init();

        this.grecaptchaContainer = this.el.querySelector(this.options.checkboxContainer);
        this.grecaptchaContainerIframe = null;
        this.grecaptchaWidgetId = null;

        this._renderV2Captcha();
    }

    getGreCaptchaInfo() {
        return {
            version: 'GoogleReCaptchaV2',
            invisible: this.options.invisible,
        };
    }

    onFormSubmit() {
        if (this.options.invisible) {
            if (this.grecaptchaWidgetId === null) {
                return;
            }

            this.grecaptcha.execute(this.grecaptchaWidgetId).then(() => {
                this._formSubmitting = false;
            });
        } else {
            if (!this.grecaptchaInput.value) {
                this.grecaptchaContainerIframe = DomAccess.querySelector(this.el, 'iframe');
                this.grecaptchaContainerIframe.classList.add(this.options.grecaptchaIframeHasErrorClassSelector);
            }

            this._formSubmitting = false;

            this.$emitter.publish('beforeGreCaptchaFormSubmit', {
                info: this.getGreCaptchaInfo(),
                token: this.grecaptchaInput.value,
            });
        }
    }

    /**
     * @private
     */
    _renderV2Captcha() {
        this.grecaptcha.ready(this._onGreCaptchaReady.bind(this));
    }

    /**
     * @private
     */
    _onCaptchaTokenResponse(token) {
        this.$emitter.publish('onGreCaptchaTokenResponse', {
            info: this.getGreCaptchaInfo(),
            token,
        });

        this._formSubmitting = false;
        this.grecaptchaInput.value = token;

        if (!this.options.invisible) {
            this.grecaptchaContainerIframe.classList.remove(this.options.grecaptchaIframeHasErrorClassSelector);
            return;
        }

        this._submitInvisibleForm();
    }

    /**
     * @private
     */
    _onGreCaptchaReady() {
        this.grecaptchaWidgetId = this.grecaptcha.render(this.grecaptchaContainer, {
            sitekey: this.options.siteKey,
            size: this.options.invisible ? 'invisible' : 'normal',
            callback: this._onCaptchaTokenResponse.bind(this),
            'expired-callback': this._onGreCaptchaExpire.bind(this),
            'error-callback': this._onGreCaptchaError.bind(this),
        });

        this.grecaptchaContainerIframe = DomAccess.querySelector(this.el, 'iframe');
    }

    /**
     * @private
     */
    _onGreCaptchaExpire() {
        this.$emitter.publish('onGreCaptchaExpire', {
            info: this.getGreCaptchaInfo(),
        });

        this.grecaptcha.reset(this.grecaptchaWidgetId);
        this.grecaptchaInput.value = '';
    }

    /**
     * @private
     */
    _onGreCaptchaError() {
        this.$emitter.publish('onGreCaptchaError', {
            info: this.getGreCaptchaInfo(),
        });
    }
}
