import template from './sw-order-list.html.twig';
import './sw-order-list.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-order-list', {
    template,

    inject: [
        'repositoryFactory',
        'stateStyleDataProviderService',
        'acl',
        'filterFactory',
        'feature',
    ],

    mixins: [
        Mixin.getByName('listing'),
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
            availableCampaignCodes: [],

            /** @deprecated tag:v6.5.0 - values will be handled by filterFactory */
            affiliateCodeFilter: [],

            /** @deprecated tag:v6.5.0 - values will be handled by filterFactory */
            campaignCodeFilter: [],

            filterCriteria: [],
            defaultFilters: [
                'affiliate-code-filter',
                'campaign-code-filter',
                'document-filter',
                'order-date-filter',
                'status-filter',
                'payment-status-filter',
                'delivery-status-filter',
                'payment-method-filter',
                'shipping-method-filter',
                'sales-channel-filter',
                'billing-country-filter',
                'customer-group-filter',
                'shipping-country-filter',
                'customer-group-filter',
                'tag-filter',
                'line-item-filter',
            ],
            storeKey: 'grid.filter.order',
            activeFilterNumber: 0,
            showBulkEditModal: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
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

            this.sortBy.split(',').forEach(sortBy => {
                criteria.addSorting(Criteria.sort(sortBy, this.sortDirection));
            });

            this.filterCriteria.forEach(filter => {
                criteria.addFilter(filter);
            });

            criteria.addAssociation('addresses');
            criteria.addAssociation('billingAddress');
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
                [Criteria.equals('affiliateCode', null), Criteria.equals('campaignCode', null)],
            ));
            criteria.addAggregation(Criteria.terms('affiliateCodes', 'affiliateCode', null, null, null));
            criteria.addAggregation(Criteria.terms('campaignCodes', 'campaignCode', null, null, null));

            return criteria;
        },

        listFilters() {
            return this.filterFactory.create('order', {
                'affiliate-code-filter': {
                    property: 'affiliateCode',
                    type: 'multi-select-filter',
                    label: this.$tc('sw-order.filters.affiliateCodeFilter.label'),
                    placeholder: this.$tc('sw-order.filters.affiliateCodeFilter.placeholder'),
                    valueProperty: 'key',
                    labelProperty: 'key',
                    options: this.availableAffiliateCodes,
                },
                'campaign-code-filter': {
                    property: 'campaignCode',
                    type: 'multi-select-filter',
                    label: this.$tc('sw-order.filters.campaignCodeFilter.label'),
                    placeholder: this.$tc('sw-order.filters.campaignCodeFilter.placeholder'),
                    valueProperty: 'key',
                    labelProperty: 'key',
                    options: this.availableCampaignCodes,
                },
                'document-filter': {
                    property: 'documents',
                    label: this.$tc('sw-order.filters.documentFilter.label'),
                    placeholder: this.$tc('sw-order.filters.documentFilter.placeholder'),
                    optionHasCriteria: this.$tc('sw-order.filters.documentFilter.textHasCriteria'),
                    optionNoCriteria: this.$tc('sw-order.filters.documentFilter.textNoCriteria'),
                },
                'order-date-filter': {
                    property: 'orderDateTime',
                    label: this.$tc('sw-order.filters.orderDateFilter.label'),
                    dateType: 'datetime-local',
                },
                'status-filter': {
                    property: 'stateMachineState',
                    criteria: this.getStatusCriteria('order.state'),
                    label: this.$tc('sw-order.filters.statusFilter.label'),
                    placeholder: this.$tc('sw-order.filters.statusFilter.placeholder'),
                },
                'payment-status-filter': {
                    property: 'transactions.stateMachineState',
                    criteria: this.getStatusCriteria('order_transaction.state'),
                    label: this.$tc('sw-order.filters.paymentStatusFilter.label'),
                    placeholder: this.$tc('sw-order.filters.paymentStatusFilter.placeholder'),
                },
                'delivery-status-filter': {
                    property: 'deliveries.stateMachineState',
                    criteria: this.getStatusCriteria('order_delivery.state'),
                    label: this.$tc('sw-order.filters.deliveryStatusFilter.label'),
                    placeholder: this.$tc('sw-order.filters.deliveryStatusFilter.placeholder'),
                },
                'payment-method-filter': {
                    property: 'transactions.paymentMethod',
                    label: this.$tc('sw-order.filters.paymentMethodFilter.label'),
                    placeholder: this.$tc('sw-order.filters.paymentMethodFilter.placeholder'),
                },
                'shipping-method-filter': {
                    property: 'deliveries.shippingMethod',
                    label: this.$tc('sw-order.filters.shippingMethodFilter.label'),
                    placeholder: this.$tc('sw-order.filters.shippingMethodFilter.placeholder'),
                },
                'sales-channel-filter': {
                    property: 'salesChannel',
                    label: this.$tc('sw-order.filters.salesChannelFilter.label'),
                    placeholder: this.$tc('sw-order.filters.salesChannelFilter.placeholder'),
                },
                'billing-country-filter': {
                    property: 'billingAddress.country',
                    label: this.$tc('sw-order.filters.billingCountryFilter.label'),
                    placeholder: this.$tc('sw-order.filters.billingCountryFilter.placeholder'),
                },
                'shipping-country-filter': {
                    property: 'deliveries.shippingOrderAddress.country',
                    label: this.$tc('sw-order.filters.shippingCountryFilter.label'),
                    placeholder: this.$tc('sw-order.filters.shippingCountryFilter.placeholder'),
                },
                'customer-group-filter': {
                    property: 'orderCustomer.customer.group',
                    label: this.$tc('sw-order.filters.customerGroupFilter.label'),
                    placeholder: this.$tc('sw-order.filters.customerGroupFilter.placeholder'),
                },
                'tag-filter': {
                    property: 'tags',
                    label: this.$tc('sw-order.filters.tagFilter.label'),
                    placeholder: this.$tc('sw-order.filters.tagFilter.placeholder'),
                },
                'line-item-filter': {
                    property: 'lineItems.product',
                    label: this.$tc('sw-order.filters.productFilter.label'),
                    placeholder: this.$tc('sw-order.filters.productFilter.placeholder'),
                    criteria: this.productCriteria,
                    displayVariants: true,
                },
            });
        },

        productCriteria() {
            const productCriteria = new Criteria();
            productCriteria.addAssociation('options.group');

            return productCriteria;
        },
    },

    watch: {
        orderCriteria: {
            handler() {
                this.getList();
            },
            deep: true,
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadFilterValues();
        },

        onEdit(order) {
            if (order?.id) {
                this.$router.push({
                    name: 'sw.order.detail',
                    params: {
                        id: order.id,
                    },
                });
            }
        },

        onInlineEditSave(order) {
            order.save();
        },

        onChangeLanguage() {
            this.getList();
        },

        async getList() {
            this.isLoading = true;

            const criteria = await Shopware.Service('filterService')
                .mergeWithStoredFilters(this.storeKey, this.orderCriteria);

            this.activeFilterNumber = criteria.filters.length;

            try {
                const response = await this.orderRepository.search(criteria);

                this.total = response.total;
                this.orders = response;
                this.isLoading = false;
            } catch {
                this.isLoading = false;
            }
        },

        getBillingAddress(order) {
            return order.addresses.find((address) => {
                return address.id === order.billingAddressId;
            });
        },

        disableDeletion(order) {
            if (!this.acl.can('order.deleter')) {
                return true;
            }

            return order.documents.length > 0;
        },

        getOrderColumns() {
            return [{
                property: 'orderNumber',
                label: 'sw-order.list.columnOrderNumber',
                routerLink: 'sw.order.detail',
                allowResize: true,
                primary: true,
            }, {
                property: 'salesChannel.name',
                label: 'sw-order.list.columnSalesChannel',
                allowResize: true,
            }, {
                property: 'orderCustomer.firstName',
                dataIndex: 'orderCustomer.lastName,orderCustomer.firstName',
                label: 'sw-order.list.columnCustomerName',
                allowResize: true,
            }, {
                property: 'billingAddressId',
                dataIndex: 'billingAddress.street',
                label: 'sw-order.list.columnBillingAddress',
                allowResize: true,
            }, {
                property: 'amountTotal',
                label: 'sw-order.list.columnAmount',
                align: 'right',
                allowResize: true,
            }, {
                property: 'stateMachineState.name',
                label: 'sw-order.list.columnState',
                allowResize: true,
            }, {
                property: 'transactions.last().stateMachineState.name',
                dataIndex: 'transactions.stateMachineState.name',
                label: 'sw-order.list.columnTransactionState',
                allowResize: true,
            }, {
                property: 'deliveries[0].stateMachineState.name',
                dataIndex: 'deliveries.stateMachineState.name',
                label: 'sw-order.list.columnDeliveryState',
                allowResize: true,
            }, {
                property: 'orderDateTime',
                label: 'sw-order.list.orderDate',
                allowResize: true,
            }, {
                property: 'affiliateCode',
                inlineEdit: 'string',
                label: 'sw-order.list.columnAffiliateCode',
                allowResize: true,
                visible: false,
            }, {
                property: 'campaignCode',
                inlineEdit: 'string',
                label: 'sw-order.list.columnCampaignCode',
                allowResize: true,
                visible: false,
            }];
        },

        getVariantFromOrderState(order) {
            return this.stateStyleDataProviderService.getStyle(
                'order.state', order.stateMachineState.technicalName,
            ).variant;
        },

        getVariantFromPaymentState(order) {
            let technicalName = order.transactions.last().stateMachineState.technicalName;
            // set the payment status to the first transaction that is not cancelled
            for (let i = 0; i < order.transactions.length; i += 1) {
                if (order.transactions[i].stateMachineState.technicalName !== 'cancelled') {
                    technicalName = order.transactions[i].stateMachineState.technicalName;
                }
            }
            return this.stateStyleDataProviderService.getStyle(
                'order_transaction.state', technicalName,
            ).variant;
        },

        getVariantFromDeliveryState(order) {
            return this.stateStyleDataProviderService.getStyle(
                'order_delivery.state', order.deliveries[0].stateMachineState.technicalName,
            ).variant;
        },

        loadFilterValues() {
            this.filterLoading = true;

            return this.orderRepository.search(this.filterSelectCriteria).then(({ aggregations }) => {
                this.availableAffiliateCodes = aggregations.affiliateCodes.buckets;
                this.availableCampaignCodes = aggregations.campaignCodes.buckets;
                this.filterLoading = false;

                return aggregations;
            }).catch(() => {
                this.filterLoading = false;
            });
        },

        /** @deprecated tag:v6.5.0 - will be handled by filterFactory */
        onChangeAffiliateCodeFilter(value) {
            this.affiliateCodeFilter = value;
            this.getList();
        },

        /** @deprecated tag:v6.5.0 - will be handled by filterFactory */
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

            return this.orderRepository.delete(id).then(() => {
                this.getList();
            });
        },

        updateCriteria(criteria) {
            this.page = 1;

            this.filterCriteria = criteria;
        },

        getStatusCriteria(value) {
            const criteria = new Criteria();

            criteria.addFilter(Criteria.equals('stateMachine.technicalName', value));

            return criteria;
        },
    },
});
