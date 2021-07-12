import GoogleReCaptchaBasePlugin from 'src/plugin/captcha/google-re-captcha/google-re-captcha-base.plugin';

export default class GoogleReCaptchaV3Plugin extends GoogleReCaptchaBasePlugin {
    static options = {
        siteKey: null,
        grecaptchaInputSelector: '.grecaptcha_v3-input',
    };

    init() {
        super.init();
    }

    onFormSubmit() {
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
            this.formSubmitting = false;

            this._submitInvisibleForm();
        });
    }
}
