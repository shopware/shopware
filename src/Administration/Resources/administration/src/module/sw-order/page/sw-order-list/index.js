import template from './sw-order-list.html.twig';
import './sw-order-list.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-order-list', {
    template,

    inject: [
        'repositoryFactory',
        'apiContext',
        'stateStyleDataProviderService'
    ],

    mixins: [
        Mixin.getByName('listing')
    ],

    data() {
        return {
            orders: [],
            sortBy: 'orderDateTime',
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
        orderRepsitory() {
            return this.repositoryFactory.create('order');
        },

        orderColumns() {
            return this.getOrderColumns();
        },

        orderCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            criteria.setTerm(this.term);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            criteria.addAssociation('addresses');
            criteria.addAssociation('salesChannel');
            criteria.addAssociation('orderCustomer');
            criteria.addAssociation('currency');
            criteria.addAssociation('transactions');
            criteria.addAssociation('deliveries');

            return criteria;
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

            return this.orderRepsitory.search(this.orderCriteria, this.apiContext).then((response) => {
                this.total = response.total;
                this.orders = response;
                this.isLoading = false;

                return response;
            }).catch(() => {
                this.isLoading = false;
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
                label: this.$tc('sw-order.list.columnOrderNumber'),
                routerLink: 'sw.order.detail',
                allowResize: true,
                primary: true
            }, {
                property: 'salesChannel.name',
                label: this.$tc('sw-order.list.columnSalesChannel'),
                allowResize: true
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
                label: this.$tc('sw-order.list.columnAmount'),
                align: 'right',
                allowResize: true
            }, {
                property: 'stateMachineState.name',
                label: this.$tc('sw-order.list.columnState'),
                allowResize: true
            }, {
                property: 'transactions[0].stateMachineState.name',
                label: this.$tc('sw-order.list.columnTransactionState'),
                allowResize: true
            }, {
                property: 'deliveries[0].stateMachineState.name',
                label: this.$tc('sw-order.list.columnDeliveryState'),
                allowResize: true
            }, {
                property: 'orderDateTime',
                label: this.$tc('sw-order.list.orderDate'),
                allowResize: true
            }];
        },

        getVariantFromOrderState(order) {
            return this.stateStyleDataProviderService.getStyle(
                'order.state', order.stateMachineState.technicalName
            ).variant;
        },

        getVariantFromPaymentState(order) {
            return this.stateStyleDataProviderService.getStyle(
                'order_transaction.state', order.transactions[0].stateMachineState.technicalName
            ).variant;
        },

        getVariantFromDeliveryState(order) {
            return this.stateStyleDataProviderService.getStyle(
                'order_delivery.state', order.deliveries[0].stateMachineState.technicalName
            ).variant;
        }
    }
});
