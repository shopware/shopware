import { Component, State } from 'src/core/shopware';
import template from './sw-settings-tax-list.html.twig';

Component.register('sw-settings-tax-list', {
    template,

    mixins: [
        'listing',
        'notification'
    ],

    data() {
        return {
            taxes: [],
            isLoading: false,
            showDeleteModal: false
        };
    },

    computed: {
        taxStore() {
            return State.getStore('tax');
        }
    },

    created() {
        this.$root.$on('search', (term) => {
            this.onSearch(term);
        });
    },

    methods: {
        getList() {
            this.isLoading = true;
            const params = this.getListingParams();

            this.taxes = [];

            return this.taxStore.getList(params).then((response) => {
                this.total = response.total;
                this.taxes = response.items;
                this.isLoading = false;

                return this.taxes;
            });
        },

        onDelete(id) {
            this.showDeleteModal = id;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete(id) {
            const currency = this.taxStore.store[id];
            const currencyName = currency.name;
            const titleSaveSuccess = this.$tc('sw-settings-tax.list.titleDeleteSuccess');
            const messageSaveSuccess = this.$tc('sw-settings-tax.list.messageDeleteSuccess', 0, { name: currencyName });

            return this.taxStore.store[id].delete(true).then(() => {
                this.showDeleteModal = false;

                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });

                this.getList();
            });
        }
    }
});
