import template from './sw-review-list.html.twig';
import './sw-review-list.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

const DEFAULT_FILTERS = Object.freeze([
    'sales-channel-filter',
    'status-filter',
    'language-filter',
    'customer-filter',
    'product-filter',
    'points-filter',
]);

/**
 * @sw-package after-sales
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
        'filterFactory',
    ],

    mixins: [
        Mixin.getByName('listing'),
    ],

    data() {
        return {
            items: null,
            isLoading: false,
            sortBy: 'status,createdAt',
            defaultFilters: DEFAULT_FILTERS,
            storeKey: 'grid.filter.product_review',
            activeFilterNumber: 0,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        listFilterOptions() {
            return {
                'sales-channel-filter': {
                    property: 'salesChannel',
                    label: this.$t('sw-review.filters.salesChannelFilter.label'),
                    placeholder: this.$t('sw-review.filters.salesChannelFilter.placeholder'),
                    criteria: this.salesChannelCriteria,
                },
                'status-filter': {
                    property: 'status',
                    type: 'boolean-filter',
                    label: this.$t('sw-review.filters.statusFilter.label'),
                    optionTrue: this.$t('global.default.yes'),
                    optionFalse: this.$t('global.default.no'),
                },
                'language-filter': {
                    property: 'language',
                    label: this.$t('sw-review.filters.languageFilter.label'),
                    placeholder: this.$t('sw-review.filters.languageFilter.placeholder'),
                    criteria: this.languageCriteria,
                },
                'customer-filter': {
                    property: 'customer',
                    label: this.$t('sw-review.filters.customerFilter.label'),
                    placeholder: this.$t('sw-review.filters.customerFilter.placeholder'),
                    criteria: this.customerCriteria,
                    labelProperty: 'email',
                },
                'product-filter': {
                    property: 'product',
                    label: this.$t('sw-review.filters.productFilter.label'),
                    placeholder: this.$t('sw-review.filters.productFilter.placeholder'),
                    criteria: this.productCriteria,
                },
                'points-filter': {
                    property: 'points',
                    type: 'number-filter',
                    label: this.$t('sw-review.filters.pointsFilter.label'),
                    fromFieldLabel: null,
                    toFieldLabel: null,
                    fromPlaceholder: this.$t('global.default.from'),
                    toPlaceholder: this.$t('global.default.to'),
                },
            };
        },

        listFilters() {
            return this.filterFactory.create('product_review', this.listFilterOptions);
        },

        salesChannelCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addSorting(Criteria.sort('name'));

            return criteria;
        },

        languageCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addSorting(Criteria.sort('name'));

            return criteria;
        },

        customerCriteria() {
            return new Criteria(1, 25);
        },

        productCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addSorting(Criteria.sort('name'));

            return criteria;
        },

        columns() {
            return [
                {
                    property: 'title',
                    dataIndex: 'title',
                    label: 'sw-review.list.columnTitle',
                },
                {
                    property: 'points',
                    dataIndex: 'points',
                    label: 'sw-review.list.columnPoints',
                },
                {
                    property: 'product',
                    dataIndex: 'product.name',
                    label: 'sw-review.list.columnProduct',
                    routerLink: 'sw.review.detail',
                    primary: true,
                },
                {
                    property: 'user',
                    dataIndex: 'customer.lastName,customer.firstName',
                    label: 'sw-review.list.columnUser',
                },
                {
                    property: 'createdAt',
                    dataIndex: 'createdAt',
                    label: 'sw-review.list.columnCreatedAt',
                },
                {
                    property: 'status',
                    dataIndex: 'status',
                    label: 'sw-review.list.columnStatus',
                    align: 'center',
                },
                {
                    property: 'comment',
                    dataIndex: 'comment',
                    label: 'sw-review.list.columnComment',
                    align: 'center',
                },
            ];
        },

        repository() {
            return this.repositoryFactory.create('product_review');
        },

        criteria() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.setTerm(this.term);
            criteria.addAssociation('customer');
            criteria.addAssociation('product');
            criteria.addAssociation('salesChannel');
            criteria.addAssociation('language');

            this.sortBy.split(',').forEach((sorting) => {
                criteria.addSorting(Criteria.sort(sorting, this.sortDirection, this.naturalSorting));
            });

            return criteria;
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed, because the filter is unused
         */
        dateFilter() {
            return Shopware.Filter.getByName('date');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getList();
        },

        async getList() {
            this.isLoading = true;

            const criteria = await Shopware.Service('filterService').mergeWithStoredFilters(this.storeKey, this.criteria);

            this.activeFilterNumber = criteria.filters.length;

            const context = {
                ...Shopware.Context.api,
                inheritance: true,
            };

            return this.repository
                .search(criteria, context)
                .then((result) => {
                    this.total = result.total;
                    this.items = result;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        updateCriteria(criteria) {
            this.page = 1;
            this.criteria.filters = criteria;
            this.getList();
        },

        onDelete(option) {
            this.$refs.listing.deleteItem(option);

            this.repository
                .search(this.criteria, {
                    ...Shopware.Context.api,
                    inheritance: true,
                })
                .then((result) => {
                    this.total = result.total;
                    this.items = result;
                });
        },
    },
};
