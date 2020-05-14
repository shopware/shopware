import template from './sw-order-list.html.twig';
import './sw-order-list.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-order-list', {
    template,

    inject: [
        'repositoryFactory',
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
            isLoading: false,
            filterLoading: false,
            showDeleteModal: false,
            availableAffiliateCodes: [],
            affiliateCodeFilter: [],
            availableCampaignCodes: [],
            campaignCodeFilter: []
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        orderRepository() {
            return this.repositoryFactory.create('order');
        },

        orderColumns() {
            return this.getOrderColumns();
        },

        orderCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            criteria.setTerm(this.term);
            if (this.affiliateCodeFilter.length > 0) {
                criteria.addFilter(Criteria.equalsAny('affiliateCode', this.affiliateCodeFilter));
            }
            if (this.campaignCodeFilter.length > 0) {
                criteria.addFilter(Criteria.equalsAny('campaignCode', this.campaignCodeFilter));
            }

            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            criteria.addAssociation('addresses');
            criteria.addAssociation('salesChannel');
            criteria.addAssociation('orderCustomer');
            criteria.addAssociation('currency');
            criteria.addAssociation('documents');
            criteria.addAssociation('transactions');
            criteria.addAssociation('deliveries');
            criteria.getAssociation('transactions').addSorting(Criteria.sort('createdAt'));

            return criteria;
        },

        filterSelectCriteria() {
            const criteria = new Criteria(1, 1);
            criteria.addFilter(Criteria.not(
                'AND',
                [Criteria.equals('affiliateCode', null), Criteria.equals('campaignCode', null)]
            ));
            criteria.addAggregation(Criteria.terms('affiliateCodes', 'affiliateCode', null, null, null));
            criteria.addAggregation(Criteria.terms('campaignCodes', 'campaignCode', null, null, null));

            return criteria;
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadFilterValues();
        },

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

            return this.orderRepository.search(this.orderCriteria, Shopware.Context.api).then((response) => {
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

        disableDeletion(order) {
            return order.documents.length > 0;
        },

        getOrderColumns() {
            return [{
                property: 'orderNumber',
                label: 'sw-order.list.columnOrderNumber',
                routerLink: 'sw.order.detail',
                allowResize: true,
                primary: true
            }, {
                property: 'salesChannel.name',
                label: 'sw-order.list.columnSalesChannel',
                allowResize: true
            }, {
                property: 'orderCustomer.firstName',
                dataIndex: 'orderCustomer.firstName,orderCustomer.lastName',
                label: 'sw-order.list.columnCustomerName',
                allowResize: true
            }, {
                property: 'billingAddressId',
                label: 'sw-order.list.columnBillingAddress',
                allowResize: true
            }, {
                property: 'amountTotal',
                label: 'sw-order.list.columnAmount',
                align: 'right',
                allowResize: true
            }, {
                property: 'stateMachineState.name',
                label: 'sw-order.list.columnState',
                allowResize: true
            }, {
                property: 'transactions.last().stateMachineState.name',
                label: 'sw-order.list.columnTransactionState',
                allowResize: true
            }, {
                property: 'deliveries[0].stateMachineState.name',
                label: 'sw-order.list.columnDeliveryState',
                allowResize: true
            }, {
                property: 'orderDate',
                label: 'sw-order.list.orderDate',
                allowResize: true
            }, {
                property: 'affiliateCode',
                inlineEdit: 'string',
                label: 'sw-order.list.columnAffiliateCode',
                allowResize: true,
                visible: false
            }, {
                property: 'campaignCode',
                inlineEdit: 'string',
                label: 'sw-order.list.columnCampaignCode',
                allowResize: true,
                visible: false
            }];
        },

        getVariantFromOrderState(order) {
            return this.stateStyleDataProviderService.getStyle(
                'order.state', order.stateMachineState.technicalName
            ).variant;
        },

        getVariantFromPaymentState(order) {
            let technicalName = order.transactions.last().stateMachineState.technicalName;
            for (let i = 0; i < order.transactions.length; i += 1) {
                if (order.transactions[i].stateMachineState.technicalName !== 'cancelled') {
                    technicalName = order.stateMachineState.technicalName;
                }
            }
            return this.stateStyleDataProviderService.getStyle(
                'order_transaction.state', technicalName
            ).variant;
        },

        getVariantFromDeliveryState(order) {
            return this.stateStyleDataProviderService.getStyle(
                'order_delivery.state', order.deliveries[0].stateMachineState.technicalName
            ).variant;
        },

        loadFilterValues() {
            this.filterLoading = true;

            return this.orderRepository.search(this.filterSelectCriteria, Shopware.Context.api).then(({ aggregations }) => {
                this.availableAffiliateCodes = aggregations.affiliateCodes.buckets;
                this.availableCampaignCodes = aggregations.campaignCodes.buckets;
                this.filterLoading = false;

                return aggregations;
            }).catch(() => {
                this.filterLoading = false;
            });
        },

        onChangeAffiliateCodeFilter(value) {
            this.affiliateCodeFilter = value;
            this.getList();
        },

        onChangeCampaignCodeFilter(value) {
            this.campaignCodeFilter = value;
            this.getList();
        },

        onDelete(id) {
            this.showDeleteModal = id;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete(id) {
            this.showDeleteModal = false;

            return this.orderRepository.delete(id, Shopware.Context.api).then(() => {
                this.getList();
            });
        }
    }
});
