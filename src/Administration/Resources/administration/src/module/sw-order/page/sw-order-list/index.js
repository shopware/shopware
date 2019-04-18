import { Component, State, Mixin } from 'src/core/shopware';
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
            sortBy: 'orderDate',
            sortDirection: 'DESC',
            isLoading: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        orderStore() {
            return State.getStore('order');
        },

        orderColumns() {
            return this.getOrderColumns();
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
            order.save();
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
                params.sortBy = this.sortBy;
                params.sortDirection = this.sortDirection;
            }

            if (!params.associations) {
                params.associations = {};
            }

            params.associations.addresses = {};

            return this.orderStore.getList(params, true).then((response) => {
                this.total = response.total;
                this.orders = response.items;

                this.isLoading = false;

                return this.orders;
            });
        },
        getBillingAddress(order) {
            return order.addresses.find((address) => {
                return address.id === order.billingAddressId;
            });
        },

        getOrderColumns() {
            return [{
                property: 'orderNumber',
                dataIndex: 'orderNumber',
                label: this.$tc('sw-order.list.columnOrderNumber'),
                routerLink: 'sw.order.detail',
                allowResize: true,
                primary: true
            }, {
                property: 'orderCustomer.firstName',
                dataIndex: 'orderCustomer.firstName,orderCustomer.lastName',
                label: this.$tc('sw-order.list.columnCustomerName'),
                allowResize: true
            }, {
                property: 'billingAddressId',
                label: this.$tc('sw-order.list.columnBillingAddress'),
                allowResize: true
            }, {
                property: 'amountTotal',
                dataIndex: 'amountTotal',
                label: this.$tc('sw-order.list.columnAmount'),
                align: 'right',
                allowResize: true
            }, {
                property: 'stateMachineState.name',
                dataIndex: 'stateMachineState.name',
                label: this.$tc('sw-order.list.columnState'),
                allowResize: true
            }, {
                property: 'orderDate',
                dataIndex: 'orderDate',
                label: this.$tc('sw-order.list.orderDate'),
                allowResize: true
            }];
        }
    }
});
