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
            tax: {}
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

        onSave() {
            const taxName = this.tax.name;
            const titleSaveSuccess = this.$tc('sw-settings-tax.detail.titleSaveSuccess');
            const messageSaveSuccess = this.$tc('sw-settings-tax.detail.messageSaveSuccess', 0, { name: taxName });

            return this.tax.save().then(() => {
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });
            });
        }
    }
});
