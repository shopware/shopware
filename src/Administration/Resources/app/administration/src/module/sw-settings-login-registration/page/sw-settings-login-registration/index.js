import notificationMixin from 'shopware:mixins/notification';
import template from './sw-settings-login-registration.html.twig';

/**
 * @sw-package fundamentals@framework
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    mixins: [
        notificationMixin,
    ],

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
            coreLoginRegistrationLoading: false,
            coreSystemWideLoginRegistrationLoading: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        systemConfigLoading() {
            return this.coreLoginRegistrationLoading || this.coreSystemWideLoginRegistrationLoading;
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
                this.$refs.systemConfigSystemWide.saveAll(),
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

        onLoginRegistrationLoadingChanged(loading) {
            this.coreLoginRegistrationLoading = loading;
        },

        onSystemWideLoadingChanged(loading) {
            this.coreSystemWideLoginRegistrationLoading = loading;
        },
    },
};
