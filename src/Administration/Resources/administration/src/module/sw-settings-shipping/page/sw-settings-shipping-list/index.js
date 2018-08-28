import { Component, State } from 'src/core/shopware';
import template from './sw-settings-shipping-list.html.twig';

Component.register('sw-settings-shipping-list', {
    template,

    mixins: [
        'listing',
        'notification'
    ],

    data() {
        return {
            shippingMethods: [],
            isLoading: false,
            showDeleteModal: false
        };
    },

    computed: {
        shippingMethodStore() {
            return State.getStore('shipping_method');
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

            this.shippingMethods = [];

            return this.shippingMethodStore.getList(params).then((response) => {
                this.total = response.total;
                this.shippingMethods = response.items;
                this.isLoading = false;

                return this.shippingMethods;
            });
        },

        onDelete(id) {
            this.showDeleteModal = id;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete(id) {
            const shippingMethod = this.shippingMethodStore.store[id];
            const shippingMethodName = shippingMethod.name;
            const titleSaveSuccess = this.$tc('sw-settings-shipping.list.titleDeleteSuccess');
            const messageSaveSuccess = this.$tc('sw-settings-shipping.list.messageDeleteSuccess', 0, {
                name: shippingMethodName
            });

            this.shippingMethodStore.store[id].delete(true).then(() => {
                this.showDeleteModal = false;

                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });

                this.getList();
            }).catch(this.onCloseDeleteModal());
        },

        onInlineEditSave(item) {
            this.isLoading = true;

            item.save().then(() => {
                this.isLoading = false;
            }).catch(() => {
                this.isLoading = false;
            });
        }
    }
});
