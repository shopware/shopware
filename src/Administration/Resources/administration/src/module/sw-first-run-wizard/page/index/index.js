import { Component } from 'src/core/shopware';
import { mapState } from 'vuex';
import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-first-run-wizard.html.twig';
import swFirstRunWizardState from './state';

Component.register('sw-first-run-wizard', {
    template,

    inject: ['repositoryFactory', 'context'],

    metaInfo() {
        return {
            title: this.title
        };
    },

    data() {
        return {
            repository: null
        };
    },

    computed: {
        ...mapState('swFirstRunWizardState', [
            'currentLocale'
        ]),

        title() {
            return `${this.$tc('sw-first-run-wizard.welcome.modalTitle')}`;
        }
    },

    beforeCreate() {
        this.$store.registerModule('swFirstRunWizardState', swFirstRunWizardState);
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.repository = this.repositoryFactory.create('plugin');

            const criteria = new Criteria();
            criteria.addFilter(
                Criteria.equals('plugin.name', 'SwagPayPal')
            );

            this.repository
                .search(criteria, this.context)
                .then((result) => {
                    const plugin = result.first();

                    console.log(plugin);
                });
        }
    }
});
