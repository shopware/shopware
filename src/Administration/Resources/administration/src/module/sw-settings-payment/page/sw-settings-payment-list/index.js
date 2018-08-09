import { Component, State } from 'src/core/shopware';
import template from './sw-settings-payment-list.html.twig';

Component.register('sw-settings-payment-list', {
    template,

    mixins: [
        'listing',
        'notification'
    ],

    data() {
        return {
            paymentMethods: [],
            isLoading: false,
            showDeleteModal: false
        };
    },

    computed: {
        paymentMethodStore() {
            return State.getStore('payment_method');
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

            return this.paymentMethodStore.getList(params).then((response) => {
                this.total = response.total;
                this.paymentMethods = response.items;
                this.isLoading = false;

                return this.paymentMethods;
            });
        },

        onDelete(id) {
            this.showDeleteModal = id;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete(id) {
            const paymentMethod = this.paymentMethodStore.store[id];
            const paymentMethodName = paymentMethod.name;
            const titleSaveSuccess = this.$tc('sw-settings-payment.list.titleDeleteSuccess');
            const messageSaveSuccess = this.$tc('sw-settings-payment.list.messageDeleteSuccess', 0, {
                name: paymentMethodName
            });

            return this.paymentMethodStore.store[id].delete(true).then(() => {
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
