import template from './sw-customer-list.html.twig';
import './sw-customer-list.scss';

/**
 * @package customer-order
 */

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl', 'filterFactory', 'feature'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('salutation'),
        Mixin.getByName('listing'),
    ],

    data() {
        return {
            customers: null,
            sortBy: 'customerNumber',
            naturalSorting: true,
            sortDirection: 'DESC',
            isLoading: false,
            showDeleteModal: false,
            filterLoading: false,
            availableAffiliateCodes: [],
            availableCampaignCodes: [],
            filterCriteria: [],
            defaultFilters: [
                'affiliate-code-filter',
                'campaign-code-filter',
                'customer-group-request-filter',
                'salutation-filter',
                'account-status-filter',
                'default-payment-method-filter',
                'group-filter',
                'billing-address-country-filter',
                'shipping-address-country-filter',
                'tags-filter',
            ],
            storeKey: 'grid.filter.customer',
            activeFilterNumber: 0,
            searchConfigEntity: 'customer',
            showBulkEditModal: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
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
            const defaultCriteria = new Criteria(this.page, this.limit);
            // eslint-disable-next-line vue/no-side-effects-in-computed-properties
            this.naturalSorting = this.sortBy === 'customerNumber';

            defaultCriteria.setTerm(this.term);

            this.sortBy.split(',').forEach(sortBy => {
                defaultCriteria.addSorting(Criteria.sort(sortBy, this.sortDirection, this.naturalSorting));
            });

            defaultCriteria
                .addAssociation('defaultBillingAddress')
                .addAssociation('group')
                .addAssociation('requestedGroup')
                .addAssociation('salesChannel');

            this.filterCriteria.forEach(filter => {
                defaultCriteria.addFilter(filter);
            });

            return defaultCriteria;
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

        listFilterOptions() {
            return {
                'affiliate-code-filter': {
                    property: 'affiliateCode',
                    type: 'multi-select-filter',
                    label: this.$tc('sw-customer.filter.affiliateCode.label'),
                    placeholder: this.$tc('sw-customer.filter.affiliateCode.placeholder'),
                    valueProperty: 'key',
                    labelProperty: 'key',
                    options: this.availableAffiliateCodes,
                },
                'campaign-code-filter': {
                    property: 'campaignCode',
                    type: 'multi-select-filter',
                    label: this.$tc('sw-customer.filter.campaignCode.label'),
                    placeholder: this.$tc('sw-customer.filter.campaignCode.placeholder'),
                    valueProperty: 'key',
                    labelProperty: 'key',
                    options: this.availableCampaignCodes,
                },
                'customer-group-request-filter': {
                    property: 'requestedGroupId',
                    type: 'existence-filter',
                    label: this.$tc('sw-customer.filter.customerGroupRequest.label'),
                    placeholder: this.$tc('sw-customer.filter.customerGroupRequest.placeholder'),
                    optionHasCriteria: this.$tc('sw-customer.filter.customerGroupRequest.textHasCriteria'),
                    optionNoCriteria: this.$tc('sw-customer.filter.customerGroupRequest.textNoCriteria'),
                },
                'salutation-filter': {
                    property: 'salutation',
                    label: this.$tc('sw-customer.filter.salutation.label'),
                    placeholder: this.$tc('sw-customer.filter.salutation.placeholder'),
                    labelProperty: 'displayName',
                },
                'account-status-filter': {
                    property: 'active',
                    label: this.$tc('sw-customer.filter.status.label'),
                    placeholder: this.$tc('sw-customer.filter.status.placeholder'),
                },
                'default-payment-method-filter': {
                    property: 'defaultPaymentMethod',
                    label: this.$tc('sw-customer.filter.defaultPaymentMethod.label'),
                    placeholder: this.$tc('sw-customer.filter.defaultPaymentMethod.placeholder'),
                },
                'group-filter': {
                    property: 'group',
                    label: this.$tc('sw-customer.filter.customerGroup.label'),
                    placeholder: this.$tc('sw-customer.filter.customerGroup.placeholder'),
                },
                'billing-address-country-filter': {
                    property: 'defaultBillingAddress.country',
                    label: this.$tc('sw-customer.filter.billingCountry.label'),
                    placeholder: this.$tc('sw-customer.filter.billingCountry.placeholder'),
                },
                'shipping-address-country-filter': {
                    property: 'defaultShippingAddress.country',
                    label: this.$tc('sw-customer.filter.shippingCountry.label'),
                    placeholder: this.$tc('sw-customer.filter.shippingCountry.placeholder'),
                },
                'tags-filter': {
                    property: 'tags',
                    label: this.$tc('sw-customer.filter.tags.label'),
                    placeholder: this.$tc('sw-customer.filter.tags.placeholder'),
                },
            };
        },

        listFilters() {
            return this.filterFactory.create('customer', this.listFilterOptions);
        },
    },

    watch: {
        defaultCriteria: {
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
            return this.loadFilterValues();
        },

        onInlineEditSave(promise, customer) {
            promise.then(() => {
                this.createNotificationSuccess({
                    message: this.$tc('sw-customer.detail.messageSaveSuccess', 0, { name: this.salutation(customer) }),
                });
            }).catch(() => {
                this.getList();
                this.createNotificationError({
                    message: this.$tc('sw-customer.detail.messageSaveError'),
                });
            });
        },

        async getList() {
            this.isLoading = true;

            const criteria = await Shopware.Service('filterService')
                .mergeWithStoredFilters(this.storeKey, this.defaultCriteria);

            const newCriteria = await this.addQueryScores(this.term, criteria);

            this.activeFilterNumber = criteria.filters.length;

            if (!this.entitySearchable) {
                this.isLoading = false;
                this.total = 0;

                return;
            }

            if (this.freshSearchTerm) {
                newCriteria.resetSorting();
            }

            try {
                const items = await this.customerRepository.search(newCriteria);

                this.total = items.total;
                this.customers = items;
                this.isLoading = false;
                this.selection = {};
            } catch {
                this.isLoading = false;
            }
        },

        onDelete(id) {
            this.showDeleteModal = id;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete(id) {
            this.showDeleteModal = false;

            return this.customerRepository.delete(id).then(() => {
                this.getList();
            });
        },

        async onChangeLanguage() {
            await this.createdComponent();
            await this.getList();
        },

        getCustomerColumns() {
            const columns = [{
                property: 'firstName',
                dataIndex: 'lastName,firstName',
                inlineEdit: 'string',
                label: 'sw-customer.list.columnName',
                routerLink: 'sw.customer.detail',
                width: '250px',
                allowResize: true,
                primary: true,
                useCustomSort: true,
            }, {
                property: 'defaultBillingAddress.street',
                label: 'sw-customer.list.columnStreet',
                allowResize: true,
                useCustomSort: true,
            }, {
                property: 'defaultBillingAddress.zipcode',
                label: 'sw-customer.list.columnZip',
                align: 'right',
                allowResize: true,
                useCustomSort: true,
            }, {
                property: 'defaultBillingAddress.city',
                label: 'sw-customer.list.columnCity',
                allowResize: true,
                useCustomSort: true,
            }, {
                property: 'customerNumber',
                dataIndex: 'customerNumber',
                naturalSorting: true,
                label: 'sw-customer.list.columnCustomerNumber',
                allowResize: true,
                inlineEdit: 'string',
                align: 'right',
                useCustomSort: true,
            }, {
                property: 'group',
                dataIndex: 'group',
                naturalSorting: true,
                label: 'sw-customer.list.columnGroup',
                allowResize: true,
                inlineEdit: 'string',
                align: 'right',
                useCustomSort: true,
            }, {
                property: 'email',
                inlineEdit: 'string',
                label: 'sw-customer.list.columnEmail',
                allowResize: true,
                useCustomSort: true,
            }, {
                property: 'affiliateCode',
                inlineEdit: 'string',
                label: 'sw-customer.list.columnAffiliateCode',
                allowResize: true,
                visible: false,
                useCustomSort: true,
            }, {
                property: 'campaignCode',
                inlineEdit: 'string',
                label: 'sw-customer.list.columnCampaignCode',
                allowResize: true,
                visible: false,
                useCustomSort: true,
            }, {
                property: 'boundSalesChannelId',
                label: 'sw-customer.list.columnBoundSalesChannel',
                allowResize: true,
                visible: false,
                useCustomSort: true,
            }, {
                property: 'active',
                inlineEdit: 'boolean',
                label: 'sw-customer.list.columnActive',
                allowResize: true,
                visible: false,
                useCustomSort: true,
            }];

            return columns;
        },

        loadFilterValues() {
            this.filterLoading = true;

            return this.customerRepository.search(this.filterSelectCriteria)
                .then(({ aggregations }) => {
                    this.availableAffiliateCodes = aggregations?.affiliateCodes?.buckets ?? [];
                    this.availableCampaignCodes = aggregations?.campaignCodes?.buckets ?? [];
                    this.filterLoading = false;

                    return aggregations;
                }).catch(() => {
                    this.filterLoading = false;
                });
        },

        updateCriteria(criteria) {
            this.page = 1;
            this.filterCriteria = criteria;
        },

        async onBulkEditItems() {
            await this.$nextTick();
            this.$router.push({ name: 'sw.bulk.edit.customer' });
        },

        onBulkEditModalOpen() {
            this.showBulkEditModal = true;
        },

        onBulkEditModalClose() {
            this.showBulkEditModal = false;
        },
    },
};
