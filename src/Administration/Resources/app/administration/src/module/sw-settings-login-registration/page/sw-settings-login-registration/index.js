import template from './sw-settings-login-registration.html.twig';

const { Mixin } = Shopware;

/**
 * @sw-package fundamentals@framework
 */
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
            coreLoginRegistrationLoading: false,
            coreSystemWideLoginRegistrationLoading: false,
            loginRegistrationConfig: {},
            inheritedLoginRegistrationConfig: {},
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

        disabledLoginRegistrationElements() {
            const key = 'core.loginRegistration.showAccountTypeSelection';
            // A sales channel that does not override the setting runs on the inherited one.
            const accountTypeSelectable = this.loginRegistrationConfig[key] ?? this.inheritedLoginRegistrationConfig[key];

            if (accountTypeSelectable) {
                return [];
            }

            return [
                'core.loginRegistration.showNameFieldsForCompanyAccounts',
                'core.loginRegistration.nameFieldsRequiredForCompanyAccounts',
            ];
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

        onLoginRegistrationConfigChanged(config, inheritedConfig) {
            this.loginRegistrationConfig = config ?? {};
            this.inheritedLoginRegistrationConfig = inheritedConfig ?? {};
        },

        onLoginRegistrationLoadingChanged(loading) {
            this.coreLoginRegistrationLoading = loading;
        },

        onSystemWideLoadingChanged(loading) {
            this.coreSystemWideLoginRegistrationLoading = loading;
        },
    },
};
