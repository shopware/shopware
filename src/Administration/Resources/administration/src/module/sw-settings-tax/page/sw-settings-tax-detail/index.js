import { Component, State, Mixin } from 'src/core/shopware';
import template from './sw-settings-tax-detail.html.twig';

Component.register('sw-settings-tax-detail', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('discard-detail-page-changes')('tax')
    ],

    data() {
        return {
            tax: {},
            isLoading: false,
            isSaveSuccessful: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            return this.tax.name || '';
        },

        taxStore() {
            return State.getStore('tax');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.taxId = this.$route.params.id;
                this.tax = this.taxStore.getById(this.taxId);
            }
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            return this.tax.save().then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;
            }).catch(() => {
                this.isLoading = false;
            });
        }
    }
});
