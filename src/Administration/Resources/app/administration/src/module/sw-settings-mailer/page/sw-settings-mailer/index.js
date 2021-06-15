import template from './sw-settings-mailer.html.twig';
import './sw-settings-mailer.scss';

Shopware.Component.register('sw-settings-mailer', {
    template,

    inject: ['systemConfigApiService'],

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

            await this.systemConfigApiService.saveValues(this.mailerSettings);
            this.isLoading = false;
        },

        async onSaveFinish() {
            await this.loadPageContent();
        },

        checkFirstConfiguration() {
            this.isFirstConfiguration = !this.mailerSettings['core.mailerSettings.emailAgent'];
        },
    },
});
