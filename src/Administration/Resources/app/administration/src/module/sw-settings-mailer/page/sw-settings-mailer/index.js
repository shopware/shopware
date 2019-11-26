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
                'core.mailerSettings.authenticationMethod': 'null',
                'core.mailerSettings.senderAddress': null,
                'core.mailerSettings.deliveryAddress': null,
                'core.mailerSettings.disableDelivery': false
            }
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        emailAgentOptions() {
            return [
                {
                    value: 'local',
                    name: this.$tc('sw-settings-mailer.mailer-configuration.local-agent'),
                    helpText: 'This option uses your system‘s default mailer (Sendmail). ' +
                        'Make sure Sendmail works and is available in your own hosting environment.'
                },
                {
                    value: 'smtp',
                    name: this.$tc('sw-settings-mailer.mailer-configuration.smtp-server')
                }
            ];
        },

        encryptionOptions() {
            return [
                { value: 'null', label: this.$tc('sw-settings-mailer.encryption.no-encryption') },
                { value: 'ssl', label: this.$tc('sw-settings-mailer.encryption.ssl') },
                { value: 'tls', label: this.$tc('sw-settings-mailer.encryption.tls') }
            ];
        },

        authenticationOptions() {
            return [
                { value: 'null', label: this.$tc('sw-settings-mailer.authentication.no-authentication') },
                { value: 'plain', label: this.$tc('sw-settings-mailer.authentication.plain') },
                { value: 'login', label: this.$tc('sw-settings-mailer.authentication.login') },
                { value: 'cram-md5', label: this.$tc('sw-settings-mailer.authentication.cram-md5') }
            ];
        },

        isSmtpMode() {
            return this.mailerSettings['core.mailerSettings.emailAgent'] === 'smtp';
        }
    },

    created() {
        this.onCreated();
    },

    methods: {
        async onCreated() {
            await this.loadPageContent();
        },

        async loadPageContent() {
            await this.loadMailerSettings();
            this.checkFirstConfiguration();
        },

        async loadMailerSettings() {
            this.isLoading = true;
            this.mailerSettings = await this.systemConfigApiService.getValues('core.mailerSettings');
            this.isLoading = false;
        },

        async saveMailerSettings() {
            this.isLoading = true;
            await this.systemConfigApiService.saveValues(this.mailerSettings);
            this.isLoading = false;
        },

        async onSaveFinish() {
            await this.loadPageContent();
        },

        checkFirstConfiguration() {
            this.isFirstConfiguration = !this.mailerSettings['core.mailerSettings.emailAgent'];
        }
    }
});
