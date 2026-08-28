import GoogleReCaptchaBasePlugin from 'src/plugin/captcha/google-re-captcha/google-re-captcha-base.plugin';

export default class GoogleReCaptchaV3Plugin extends GoogleReCaptchaBasePlugin {
    static RECAPTCHA_PENDING_VALUE = 'recaptcha-pending';

    static options = {
        siteKey: null,
        grecaptchaInputSelector: '.grecaptcha_v3-input',
    };

    init() {
        super.init();
    }

    onFormSubmit() {
        this.grecaptchaInput.value = GoogleReCaptchaV3Plugin.RECAPTCHA_PENDING_VALUE;
        this.grecaptcha.ready(this._onGreCaptchaReady.bind(this));
    }

    getGreCaptchaInfo() {
        return {
            version: 'GoogleReCaptchaV3',
        };
    }

    /**
     * @private
     */
    _onGreCaptchaReady() {
        this.grecaptcha.execute(this.options.siteKey, { action: 'submit' }).then(token => {
            this.$emitter.publish('onGreCaptchaTokenResponse', {
                info: this.getGreCaptchaInfo(),
                token,
            });

            this.grecaptchaInput.value = token;
            this._formSubmitting = false;

            this._submitInvisibleForm();
        });
    }
}
