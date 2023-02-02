import './sw-first-run-wizard-mailer-local.scss';
import template from './sw-first-run-wizard-mailer-local.html.twig';

/**
 * @package merchant-services
 * @deprecated tag:v6.6.0 - Will be private
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['systemConfigApiService'],

    data() {
        return {
            isLoading: false,
            mailerSettings: {
                'core.mailerSettings.emailAgent': null,
                'core.mailerSettings.sendMailOptions': null,
            },
        };
    },

    computed: {
        emailSendmailOptions() {
            /**
             * -bs: Starts a smtp session over the sendmail binary
             * -t: Sends a mail in background
             */
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

        buttonConfig() {
            return [
                {
                    key: 'back',
                    label: this.$tc('sw-first-run-wizard.general.buttonBack'),
                    position: 'left',
                    variant: null,
                    action: 'sw.first.run.wizard.index.mailer.selection',
                    disabled: false,
                },
                {
                    key: 'configure-later',
                    label: this.$tc('sw-first-run-wizard.general.buttonConfigureLater'),
                    position: 'right',
                    variant: null,
                    action: 'sw.first.run.wizard.index.paypal.info',
                    disabled: false,
                },
                {
                    key: 'next',
                    label: this.$tc('sw-first-run-wizard.general.buttonNext'),
                    position: 'right',
                    variant: 'primary',
                    action: this.saveMailerSettings.bind(this),
                    disabled: !this.requiredFieldsFilled,
                },
            ];
        },

        requiredFieldsFilled() {
            return (
                !!this.mailerSettings['core.mailerSettings.emailAgent'] &&
                !!this.mailerSettings['core.mailerSettings.sendMailOptions']
            );
        },
    },

    watch: {
        mailerSettings: {
            deep: true,
            handler() {
                this.updateButtons();
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.updateButtons();
            this.setTitle();
            await this.loadMailerSettings();
        },

        updateButtons() {
            this.$emit('buttons-update', this.buttonConfig);
        },

        setTitle() {
            this.$emit('frw-set-title', this.$tc('sw-first-run-wizard.mailerLocal.headline'));
        },

        async loadMailerSettings() {
            this.isLoading = true;

            this.mailerSettings = await this.systemConfigApiService.getValues('core.mailerSettings');
            this.mailerSettings['core.mailerSettings.emailAgent'] = 'local';
            const option = this.mailerSettings['core.mailerSettings.sendMailOptions'];

            if (option === undefined || option === '') {
                this.mailerSettings['core.mailerSettings.sendMailOptions'] = '-t';
            }

            this.isLoading = false;
        },

        saveMailerSettings() {
            this.isLoading = true;

            return this.systemConfigApiService.saveValues(this.mailerSettings).then(() => {
                this.$emit('frw-redirect', 'sw.first.run.wizard.index.paypal.info');
                this.isLoading = false;
            }).catch(() => {
                this.isLoading = false;
            });
        },
    },
};
