import template from './sw-first-run-wizard-shopware-account.html.twig';
import './sw-first-run-wizard-shopware-account.scss';

const { Component } = Shopware;

Component.register('sw-first-run-wizard-shopware-account', {
    template,

    inject: ['firstRunWizardService'],

    data() {
        return {
            shopwareId: '',
            password: '',
            accountError: false,
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.setTitle();
            this.updateButtons();
        },

        setTitle() {
            this.$emit('frw-set-title', this.$tc('sw-first-run-wizard.shopwareAccount.modalTitle'));
        },

        updateButtons() {
            const buttonConfig = [
                {
                    key: 'back',
                    label: this.$tc('sw-first-run-wizard.general.buttonBack'),
                    position: 'left',
                    variant: null,
                    action: 'sw.first.run.wizard.index.plugins',
                    disabled: false,
                },
                {
                    key: 'skip',
                    label: this.$tc('sw-first-run-wizard.general.buttonSkip'),
                    position: 'right',
                    variant: null,
                    action: 'sw.first.run.wizard.index.store',
                    disabled: false,
                },
                {
                    key: 'next',
                    label: this.$tc('sw-first-run-wizard.general.buttonNext'),
                    position: 'right',
                    variant: 'primary',
                    action: this.testCredentials.bind(this),
                    disabled: false,
                },
            ];

            this.$emit('buttons-update', buttonConfig);
        },

        testCredentials() {
            const { shopwareId, password } = this;
            const language = Shopware.State.get('session').currentLocale;

            return this.firstRunWizardService.checkShopwareId({
                language,
                shopwareId,
                password,
            }).then(() => {
                this.accountError = false;

                this.$emit('frw-redirect', 'sw.first.run.wizard.index.shopware.domain');

                return false;
            }).catch(() => {
                this.accountError = true;

                return true;
            });
        },
    },
});
