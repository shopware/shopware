import template from './sw-first-run-wizard-finish.html.twig';
import './sw-first-run-wizard-finish.scss';

const { Component } = Shopware;

Component.register('sw-first-run-wizard-finish', {
    template,

    inject: ['firstRunWizardService'],

    data() {
        return {
            licenceDomains: [],
            licensed: false,
            restarting: false
        };
    },

    computed: {
        edition() {
            const activeDomain = this.licenceDomains.find((domain) => domain.active);

            if (!activeDomain) {
                return '';
            }

            return activeDomain.edition;
        },

        successMessage() {
            if (!this.licensed) {
                return this.$tc('sw-first-run-wizard.finish.messageNotLicensed');
            }

            const { edition } = this;

            return this.$tc('sw-first-run-wizard.finish.message', {}, { edition });
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.updateButtons();
            const language = Shopware.State.get('session').currentLocale;

            this.firstRunWizardService.getLicenseDomains({
                language
            }).then((response) => {
                const { items } = response;

                if (!items || items.length < 1) {
                    return;
                }

                this.licenceDomains = items;
                this.licensed = true;
            }).catch(() => {
                this.licensed = false;
            });
        },

        updateButtons() {
            const buttonConfig = [
                {
                    key: 'back',
                    label: this.$tc('sw-first-run-wizard.general.buttonBack'),
                    position: 'left',
                    variant: null,
                    action: 'sw.first.run.wizard.index.shopware.account',
                    disabled: false
                },
                {
                    key: 'finish',
                    label: this.$tc('sw-first-run-wizard.general.buttonFinish'),
                    position: 'right',
                    variant: 'primary',
                    action: this.onFinish.bind(this),
                    disabled: false
                }
            ];

            this.$emit('buttons-update', buttonConfig);
        },

        onFinish() {
            this.restarting = true;
            this.$emit('frw-finish', true);
        }
    }
});
