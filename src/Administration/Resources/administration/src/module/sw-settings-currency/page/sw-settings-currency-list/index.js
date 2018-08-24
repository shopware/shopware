import { Component, State } from 'src/core/shopware';
import template from './sw-settings-currency-list.html.twig';

Component.register('sw-settings-currency-list', {
    template,

    mixins: [
        'listing',
        'notification'
    ],

    data() {
        return {
            currencies: [],
            isLoading: false,
            showDeleteModal: false
        };
    },

    computed: {
        currencyStore() {
            return State.getStore('currency');
        }
    },

    created() {
        this.$root.$on('search', this.onSearch);
    },

    destroyed() {
        this.$root.$off('search', this.onSearch);
    },

    methods: {
        getList() {
            this.isLoading = true;
            const params = this.getListingParams();

            this.currencies = [];

            return this.currencyStore.getList(params).then((response) => {
                this.total = response.total;
                this.currencies = response.items;
                this.isLoading = false;

                return this.currencies;
            });
        },

        onDelete(id) {
            this.showDeleteModal = id;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete(id) {
            const currency = this.currencyStore.store[id];
            const currencyName = currency.name;
            const titleSaveSuccess = this.$tc('sw-settings-currency.list.titleDeleteSuccess');
            const messageSaveSuccess = this.$tc('sw-settings-currency.list.messageDeleteSuccess', 0, { name: currencyName });

            return this.currencyStore.store[id].delete(true).then(() => {
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
