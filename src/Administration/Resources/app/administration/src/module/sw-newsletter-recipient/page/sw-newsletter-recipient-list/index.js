import template from './sw-newsletter-recipient-list.html.twig';
import './sw-newsletter-recipient-list.scss';

const {
    Mixin,
    Data: { Criteria },
} = Shopware;

/**
 * @sw-package after-sales
 *
 * @deprecated tag:v6.8.0 - Will be private
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
    ],

    mixins: [
        Mixin.getByName('listing'),
    ],

    data() {
        return {
            isLoading: false,
            items: null,
            total: 0,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            filterSidebarIsOpen: false,
            languageFilters: [],
            languageFilterValue: [],
            salesChannelFilters: [],
            salesChannelFilterValue: [],
            statusFilterValue: [],
            tagFilters: [],
            tagFilterValue: [],
            internalFilters: {},
            /**
             * @deprecated tag:v6.8.0 - tagCollection will be removed
             */
            tagCollection: null,
            /**
             * @deprecated tag:v6.8.0 - searchConfigEntity will be removed
             */
            searchConfigEntity: 'newsletter_recipient',
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        columns() {
            return this.getColumns();
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        newsletterRecipientRepository() {
            return this.repositoryFactory.create('newsletter_recipient');
        },

        tagRepository() {
            return this.repositoryFactory.create('tag');
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed, because the filter is unused
         */
        dateFilter() {
            return Shopware.Filter.getByName('date');
        },

        emailIdnFilter() {
            return Shopware.Filter.getByName('decode-idn-email');
        },

        statusData() {
            return [
                { value: 'notSet', label: this.$t('sw-newsletter-recipient.list.notSet') },
                { value: 'direct', label: this.$t('sw-newsletter-recipient.list.direct') },
                { value: 'optIn', label: this.$t('sw-newsletter-recipient.list.optIn') },
                { value: 'optOut', label: this.$t('sw-newsletter-recipient.list.optOut') },
            ];
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.isLoading = true;

            const criteria = new Criteria(1, 100);
            try {
                const [
                    languages,
                    salesChannels,
                    tags,
                ] = await Promise.all([
                    this.repositoryFactory.create('language').search(criteria, Shopware.Context.api),
                    this.salesChannelRepository.search(criteria),
                    this.tagRepository.search(criteria),
                ]);

                this.languageFilters = languages;
                this.salesChannelFilters = salesChannels;
                this.tagFilters = tags;

                await this.getList();
            } finally {
                this.isLoading = false;
            }
        },

        async getList() {
            this.isLoading = true;

            let criteria = new Criteria(this.page, this.limit)
                .setTerm(this.term)
                .addSorting(Criteria.sort(this.sortBy, this.sortDirection))
                .addAssociation('salesChannel');

            Object.values(this.internalFilters).forEach((item) => {
                criteria.addFilter(item);
            });

            criteria = await this.addQueryScores(this.term, criteria);

            if (!this.entitySearchable) {
                this.total = 0;
                this.isLoading = false;
                return;
            }

            if (this.freshSearchTerm) {
                criteria.resetSorting();
            }

            try {
                const searchResult = await this.newsletterRecipientRepository.search(criteria);

                this.items = searchResult;
                this.total = searchResult.total;
            } finally {
                this.isLoading = false;
            }
        },

        async onStatusSelectionChanged(value) {
            this.statusFilterValue = value;
            if (this.statusFilterValue.length) {
                this.internalFilters.status = Criteria.equalsAny('status', this.statusFilterValue);
            } else {
                delete this.internalFilters.status;
            }

            await this.getList();
        },

        async onLanguageSelectionChanged(value) {
            this.languageFilterValue = value;
            if (this.languageFilterValue.length) {
                this.internalFilters.languageId = Criteria.equalsAny('languageId', this.languageFilterValue);
            } else {
                delete this.internalFilters.languageId;
            }

            await this.getList();
        },

        async onSalesChannelSelectionChanged(value) {
            this.salesChannelFilterValue = value;
            if (this.salesChannelFilterValue.length) {
                this.internalFilters.salesChannelId = Criteria.equalsAny('salesChannelId', this.salesChannelFilterValue);
            } else {
                delete this.internalFilters.salesChannelId;
            }

            await this.getList();
        },

        async onTagSelectionChanged(value) {
            this.tagFilterValue = value;
            if (this.tagFilterValue.length) {
                this.internalFilters.tags = Criteria.equalsAny('tags.id', this.tagFilterValue);
            } else {
                delete this.internalFilters.tags;
            }

            await this.getList();
        },

        closeContent() {
            if (this.filterSidebarIsOpen) {
                this.$refs.filterSideBar.closeContent();
                this.filterSidebarIsOpen = false;

                return;
            }

            this.$refs.filterSideBar.openContent();
            this.filterSidebarIsOpen = true;
        },

        getColumns() {
            return [
                {
                    property: 'email',
                    label: 'sw-newsletter-recipient.list.email',
                    routerLink: 'sw.newsletter.recipient.detail',
                    allowResize: true,
                    inlineEdit: 'string',
                },
                {
                    property: 'firstName',
                    inlineEdit: 'string',
                    label: 'sw-newsletter-recipient.list.name',
                    allowResize: true,
                    primary: true,
                },
                {
                    property: 'salesChannel.name',
                    label: 'sw-newsletter-recipient.list.salesChannel',
                    allowResize: true,
                    primary: false,
                    visible: false,
                },
                {
                    property: 'status',
                    label: 'sw-newsletter-recipient.list.status',
                    allowResize: true,
                },
                {
                    property: 'zipCode',
                    label: 'sw-newsletter-recipient.list.zipCode',
                    allowResize: true,
                    align: 'right',
                },
                {
                    property: 'city',
                    label: 'sw-newsletter-recipient.list.city',
                    allowResize: true,
                },
                {
                    property: 'street',
                    label: 'sw-newsletter-recipient.list.street',
                    allowResize: true,
                    visible: false,
                },
                {
                    property: 'updatedAt',
                    label: 'sw-newsletter-recipient.list.updatedAt',
                    allowResize: true,
                    visible: false,
                },
                {
                    property: 'createdAt',
                    label: 'sw-newsletter-recipient.list.createdAt',
                    allowResize: true,
                    visible: false,
                },
            ];
        },

        /**
         * @deprecated tag:v6.8.0 - Use dedicated "onTagSelectionChanged" function
         */
        handleTagFilter(filter) {
            if (filter.length === 0) {
                delete this.internalFilters.tags;
                return;
            }

            const ids = filter.map((item) => {
                return item.id;
            });

            this.internalFilters.tags = Criteria.equalsAny('tags.id', ids);
        },

        /**
         * @deprecated tag:v6.8.0 - Use dedicated "on___SelectionChanged" function
         */
        handleBooleanFilter(filter) {
            if (!Array.isArray(this[filter.group])) {
                this[filter.group] = [];
            }

            if (!filter.value) {
                this[filter.group] = this[filter.group].filter((x) => {
                    return x !== filter.id;
                });

                if (this[filter.group].length > 0) {
                    this.internalFilters[filter.group] = Criteria.equalsAny(filter.group, this[filter.group]);
                } else {
                    delete this.internalFilters[filter.group];
                }

                return;
            }

            this[filter.group].push(filter.id);
            this.internalFilters[filter.group] = Criteria.equalsAny(filter.group, this[filter.group]);
        },

        /**
         * @deprecated tag:v6.8.0 - Use dedicated "on___SelectionChanged" function
         */
        async onChange(filter) {
            if (filter === null) {
                filter = [];
            }

            if (Array.isArray(filter)) {
                this.handleTagFilter(filter);
                await this.getList();

                return;
            }

            this.handleBooleanFilter(filter);
            await this.getList();
        },
    },
};
