import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@framework
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-settings-captcha-select-v2',
    load: () => import('./src/sw-settings-captcha-select-v2'),
    public: {
        props: [
            'value',
        ],
        emits: [
            'update:value',
        ],
        blocks: [
            'sw_settings_captcha_select_v2',
            'sw_settings_captcha_select_v2_multi_select',
            'sw_settings_captcha_select_v2_google_recaptcha_v2',
            'sw_settings_captcha_select_v2_google_recaptcha_v2_description',
            'sw_settings_captcha_select_v2_google_recaptcha_v2_site_key',
            'sw_settings_captcha_select_v2_google_recaptcha_v2_secret_key',
            'sw_settings_captcha_select_v2_google_recaptcha_v2_invisible',
            'sw_settings_captcha_select_v2_google_recaptcha_v3',
            'sw_settings_captcha_select_v2_google_recaptcha_v3_description',
            'sw_settings_captcha_select_v2_google_recaptcha_v3_site_key',
            'sw_settings_captcha_select_v2_google_recaptcha_v3_secret_key',
            'sw_settings_captcha_select_v2_google_recaptcha_v3_threshold_score',
            'sw_settings_captcha_select_v2_basic_captcha_notice',
        ],
        computed: [
            'attributes',
            'currentValue',
            'activeCaptchaSelect',
        ],
        methods: [
            'createdComponent',
            'setCaptchaOptions',
            'renderCaptchaOption',
            'getTranslations',
        ],
    },
});
