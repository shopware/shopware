import { Component } from 'src/core/shopware';
import template from './sw-first-run-wizard-finish.html.twig';
import './sw-first-run-wizard-finish.scss';

Component.register('sw-first-run-wizard-finish', {
    template,

    inject: ['addNextCallback', 'firstRunWizardService'],

    data() {
        return {
            licenceDomains: [],
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
        }
    },

    created() {
        this.createdComponet();
    },

    methods: {
        createdComponet() {
            const language = this.$store.state.adminLocale.currentLocale;

            this.firstRunWizardService.getLicenseDomains({
                language
            }).then((response) => {
                const { items } = response;

                if (!items || items.length < 1) {
                    return;
                }

                this.licenceDomains = items;
            });

            this.addNextCallback(this.onFinish);
        },

        onFinish() {
            this.restarting = true;

            return Promise.resolve(false);
        }
    }
});
