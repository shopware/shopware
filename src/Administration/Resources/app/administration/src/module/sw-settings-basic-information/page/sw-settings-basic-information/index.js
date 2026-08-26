/**
 * @sw-package fundamentals@framework
 */
import template from './sw-settings-basic-information.html.twig';
import './sw-settings-basic-information.scss';

const { Mixin } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
            basicInformationLoading: false,
            cookieConsentLoading: false,
            cookieConsentRetentionLoading: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        systemConfigLoading() {
            return this.basicInformationLoading || this.cookieConsentLoading || this.cookieConsentRetentionLoading;
        },
    },

    methods: {
        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            Promise.all([
                this.$refs.systemConfig.saveAll(),
                this.$refs.systemConfigCookieConsent.saveAll(),
                this.$refs.systemConfigCookieConsentRetention.saveAll(),
            ])
                .then(() => {
                    this.isLoading = false;
                    this.isSaveSuccessful = true;
                })
                .catch((err) => {
                    this.isLoading = false;
                    this.createNotificationError({
                        message: err,
                    });
                });
        },

        onBasicInformationLoadingChanged(loading) {
            this.basicInformationLoading = loading;
        },

        onCookieConsentLoadingChanged(loading) {
            this.cookieConsentLoading = loading;
        },

        onCookieConsentRetentionLoadingChanged(loading) {
            this.cookieConsentRetentionLoading = loading;
        },
    },
};
