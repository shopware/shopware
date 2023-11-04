/**
 * @package system-settings
 */
import template from './sw-settings-mailer.html.twig';
import './sw-settings-mailer.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['systemConfigApiService'],

    mixins: ['notification'],

    data() {
        return {
            isLoading: true,
            isSaveSuccessful: false,
            isFirstConfiguration: false,
            mailerSettings: {
                'core.mailerSettings.emailAgent': null,
                'core.mailerSettings.host': null,
                'core.mailerSettings.port': null,
                'core.mailerSettings.username': null,
                'core.mailerSettings.password': null,
                'core.mailerSettings.encryption': 'null',
                'core.mailerSettings.senderAddress': null,
                'core.mailerSettings.deliveryAddress': null,
                'core.mailerSettings.disableDelivery': false,
            },
            smtpHostError: null,
            smtpPortError: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        emailSendmailOptions() {
            return [
                {
                    value: '-bs',
                    name: this.$tc('sw-settings-mailer.sendmail.sync'),
                },
                {
                    value: '-t',
                    name: this.$tc('sw-settings-mailer.sendmail.async'),
                },
            ];
        },

        isSmtpMode() {
            return this.mailerSettings['core.mailerSettings.emailAgent'] === 'smtp';
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            await this.loadPageContent();
        },

        async loadPageContent() {
            await this.loadMailerSettings();
            this.checkFirstConfiguration();
        },

        async loadMailerSettings() {
            this.isLoading = true;
            this.mailerSettings = await this.systemConfigApiService.getValues('core.mailerSettings');

            // Default when config is empty
            if (Object.keys(this.mailerSettings).length === 0) {
                this.mailerSettings = {
                    'core.mailerSettings.emailAgent': '',
                    'core.mailerSettings.sendMailOptions': '-t',
                };
            }

            this.isLoading = false;
        },

        async saveMailerSettings() {
            this.isLoading = true;

            // Inputs cannot return null
            if (this.mailerSettings['core.mailerSettings.emailAgent'] === '') {
                this.mailerSettings['core.mailerSettings.emailAgent'] = null;
            }

            // Validate smtp configuration
            if (this.isSmtpMode) {
                this.validateSmtpConfiguration();
            }

            // SMTP configuration invalid stop save and propagate error notification
            if (this.smtpHostError !== null || this.smtpPortError !== null) {
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc('sw-settings-mailer.card-smtp.error.notificationMessage'),
                });

                this.isLoading = false;

                return;
            }

            await this.systemConfigApiService.saveValues(this.mailerSettings);
            this.isLoading = false;
        },

        async onSaveFinish() {
            await this.loadPageContent();
        },

        checkFirstConfiguration() {
            this.isFirstConfiguration = !this.mailerSettings['core.mailerSettings.emailAgent'];
        },

        validateSmtpConfiguration() {
            this.smtpHostError = !this.mailerSettings['core.mailerSettings.host'] ? {
                detail: this.$tc('global.error-codes.c1051bb4-d103-4f74-8988-acbcafc7fdc3'),
            } : null;

            this.smtpPortError = typeof this.mailerSettings['core.mailerSettings.port'] !== 'number' ? {
                detail: this.$tc('global.error-codes.c1051bb4-d103-4f74-8988-acbcafc7fdc3'),
            } : null;
        },

        resetSmtpHostError() {
            this.smtpHostError = null;
        },

        resetSmtpPortError() {
            this.smtpPortError = null;
        },
    },
};
