import { Component, State, Mixin } from 'src/core/shopware';
import { deepCopyObject } from 'src/core/service/utils/object.utils';
import template from './sw-order-list.html.twig';
import './sw-order-list.scss';

Component.register('sw-order-list', {
    template,

    mixins: [
        Mixin.getByName('listing')
    ],

    data() {
        return {
            orders: [],
            isLoading: false
        };
    },

    computed: {
        orderStore() {
            return State.getStore('order');
        }
    },

    methods: {
        onEdit(order) {
            if (order && order.id) {
                this.$router.push({
                    name: 'sw.order.detail',
                    params: {
                        id: order.id
                    }
                });
            }
        },

        onInlineEditSave(order) {
            this.isLoading = true;

            order.save().then(() => {
                this.isLoading = false;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        onChangeLanguage() {
            this.getList();
        },

        getList() {
            this.isLoading = true;
            const params = this.getListingParams();

            this.orders = [];

            // Use the order date as the default sorting
            if (!params.sortBy && !params.sortDirection) {
                params.sortBy = 'date';
                params.sortDirection = 'DESC';
            }

            return this.orderStore.getList(params, true).then((response) => {
                this.total = response.total;
                this.orders = response.items;

                for (let i = 0; i < this.orders.length; i += 1) {
                    const order = this.orders[i];
                    let billingAddress = order.addresses.find((address) => {
                        return address.id === order.billingAddressId;
                    });

                    billingAddress = deepCopyObject(billingAddress);

                    let newOrder = deepCopyObject(order);
                    newOrder = { ...newOrder, ...{ billingAddress } };

                    this.orders[i].setLocalData(newOrder, false, false);
                }

                this.isLoading = false;

                return this.orders;
            });
        }
    }
});
