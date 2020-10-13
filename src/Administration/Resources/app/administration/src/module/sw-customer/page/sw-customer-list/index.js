import template from './sw-customer-list.html.twig';
import './sw-customer-list.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-customer-list', {
    template,

    inject: ['repositoryFactory', 'acl', 'feature'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('salutation'),
        Mixin.getByName('listing')
    ],

    data() {
        return {
            customers: null,
            sortBy: 'customerNumber',
            sortDirection: 'DESC',
            naturalSorting: true,
            isLoading: false,
            showDeleteModal: false,
            filterLoading: false,
            availableAffiliateCodes: [],
            affiliateCodeFilter: [],
            availableCampaignCodes: [],
            campaignCodeFilter: [],
            showOnlyCustomerGroupRequests: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        customerRepository() {
            return this.repositoryFactory.create('customer');
        },

        customerColumns() {
            return this.getCustomerColumns();
        },

        defaultCriteria() {
            const criteria = new Criteria(this.page, this.limit);
            this.naturalSorting = this.sortBy === 'customerNumber';

            criteria.setTerm(this.term);
            if (this.affiliateCodeFilter.length > 0) {
                criteria.addFilter(Criteria.equalsAny('affiliateCode', this.affiliateCodeFilter));
            }
            if (this.campaignCodeFilter.length > 0) {
                criteria.addFilter(Criteria.equalsAny('campaignCode', this.campaignCodeFilter));
            }

            if (this.showOnlyCustomerGroupRequests) {
                criteria.addFilter(Criteria.not('OR', [Criteria.equals('requestedGroupId', null)]));
            }

            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));
            criteria
                .addAssociation('defaultBillingAddress')
                .addAssociation('group')
                .addAssociation('requestedGroup');

            if (this.feature.isActive('FEATURE_NEXT_10555')) {
                criteria.addAssociation('salesChannel');
            }

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

        onInlineEditSave(promise, customer) {
            promise.then(() => {
                this.createNotificationSuccess({
                    message: this.$tc('sw-customer.detail.messageSaveSuccess', 0, { name: this.salutation(customer) })
                });
            }).catch(() => {
                this.getList();
                this.createNotificationError({
                    message: this.$tc('sw-customer.detail.messageSaveError')
                });
            });
        },

        getList() {
            this.isLoading = true;

            this.customerRepository.search(this.defaultCriteria, Shopware.Context.api).then((items) => {
                this.total = items.total;
                this.customers = items;
                this.isLoading = false;

                return items;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        onDelete(id) {
            this.showDeleteModal = id;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete(id) {
            this.showDeleteModal = false;

            return this.customerRepository.delete(id, Shopware.Context.api).then(() => {
                this.getList();
            });
        },

        getCustomerColumns() {
            const columns = [{
                property: 'firstName',
                dataIndex: 'firstName,lastName',
                inlineEdit: 'string',
                label: 'sw-customer.list.columnName',
                routerLink: 'sw.customer.detail',
                width: '250px',
                allowResize: true,
                primary: true
            }, {
                property: 'defaultBillingAddress.street',
                label: 'sw-customer.list.columnStreet',
                allowResize: true
            }, {
                property: 'defaultBillingAddress.zipcode',
                label: 'sw-customer.list.columnZip',
                align: 'right',
                allowResize: true
            }, {
                property: 'defaultBillingAddress.city',
                label: 'sw-customer.list.columnCity',
                allowResize: true
            }, {
                property: 'customerNumber',
                dataIndex: 'customerNumber',
                naturalSorting: true,
                label: 'sw-customer.list.columnCustomerNumber',
                allowResize: true,
                inlineEdit: 'string',
                align: 'right'
            }, {
                property: 'group',
                dataIndex: 'group',
                naturalSorting: true,
                label: 'sw-customer.list.columnGroup',
                allowResize: true,
                inlineEdit: 'string',
                align: 'right'
            }, {
                property: 'email',
                inlineEdit: 'string',
                label: 'sw-customer.list.columnEmail',
                allowResize: true
            }, {
                property: 'affiliateCode',
                inlineEdit: 'string',
                label: 'sw-customer.list.columnAffiliateCode',
                allowResize: true,
                visible: false
            }, {
                property: 'campaignCode',
                inlineEdit: 'string',
                label: 'sw-customer.list.columnCampaignCode',
                allowResize: true,
                visible: false
            }];

            if (this.feature.isActive('FEATURE_NEXT_10555')) {
                columns.push({
                    property: 'boundSalesChannelId',
                    dataIndex: 'boundSalesChannel',
                    inlineEdit: 'string',
                    label: 'sw-customer.list.columnBoundSalesChannel',
                    allowResize: true,
                    visible: false
                });
            }

            return columns;
        },

        loadFilterValues() {
            this.filterLoading = true;

            return this.customerRepository.search(this.filterSelectCriteria, Shopware.Context.api)
                .then(({ aggregations }) => {
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

        onChangeRequestedGroupFilter(value) {
            this.showOnlyCustomerGroupRequests = value;
            this.getList();
        }
    }
});
