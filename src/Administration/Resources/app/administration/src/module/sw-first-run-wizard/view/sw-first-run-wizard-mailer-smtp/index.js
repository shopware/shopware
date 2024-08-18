import './sw-first-run-wizard-mailer-smtp.scss';
import template from './sw-first-run-wizard-mailer-smtp.html.twig';

/**
 * @package checkout
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['systemConfigApiService'],

    emits: ['buttons-update', 'frw-set-title', 'frw-redirect'],

    data() {
        return {
            isLoading: false,
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
                'core.mailerSettings.disableDelivery': false,
            },
        };
    },

    computed: {
        nextAction() {
            if (Shopware.State.get('context').app.config.settings.disableExtensionManagement) {
                return 'sw.first.run.wizard.index.shopware.account';
            }

            return 'sw.first.run.wizard.index.paypal.info';
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
                    action: this.nextAction,
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
                !!this.mailerSettings['core.mailerSettings.host'] &&
                !!this.mailerSettings['core.mailerSettings.port']
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
            this.$emit('frw-set-title', this.$tc('sw-first-run-wizard.mailerSelection.modalTitle'));
        },

        async loadMailerSettings() {
            this.isLoading = true;

            this.mailerSettings = await this.systemConfigApiService.getValues('core.mailerSettings');
            this.mailerSettings['core.mailerSettings.emailAgent'] = 'smtp';

            this.isLoading = false;
        },

        saveMailerSettings() {
            this.isLoading = true;

            return this.systemConfigApiService.saveValues(this.mailerSettings).then(() => {
                this.$emit('frw-redirect', this.nextAction);
                this.isLoading = false;
            }).catch(() => {
                this.isLoading = false;
            });
        },
    },
};
