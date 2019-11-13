import template from './sw-first-run-wizard-finish.html.twig';
import './sw-first-run-wizard-finish.scss';

const { Component } = Shopware;

Component.register('sw-first-run-wizard-finish', {
    template,

    inject: ['addNextCallback', 'firstRunWizardService'],

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
            const language = Shopware.State.get('adminLocale').currentLocale;

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

            this.addNextCallback(this.onFinish);
        },

        onFinish() {
            this.restarting = true;

            return Promise.resolve(false);
        }
    }
});
