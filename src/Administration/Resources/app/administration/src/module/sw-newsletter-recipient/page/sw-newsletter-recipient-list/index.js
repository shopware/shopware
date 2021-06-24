import template from './sw-newsletter-recipient-list.html.twig';
import './sw-newsletter-recipient-list.scss';

const { Component, Mixin, Data: { Criteria, EntityCollection } } = Shopware;

Component.register('sw-newsletter-recipient-list', {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [
        Mixin.getByName('listing'),
    ],

    data() {
        return {
            isLoading: false,
            items: null,
            total: 0,
            repository: null,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            filterSidebarIsOpen: false,
            languageFilters: [],
            salesChannelFilters: [],
            tagFilters: [],
            internalFilters: {},
            tagCollection: null,
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

        tagRepository() {
            return this.repositoryFactory.create('tag');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.tagCollection = new EntityCollection('/tag', 'tag', Shopware.Context.api, new Criteria());

            const criteria = new Criteria(1, 100);
            this.repositoryFactory.create('language').search(criteria, Shopware.Context.api).then((items) => {
                this.languageFilters = items;
            });

            this.salesChannelRepository.search(new Criteria(1, 100)).then((salesChannels) => {
                this.salesChannelFilters = salesChannels;
            });

            this.getList();
        },

        getList() {
            this.isLoading = true;
            const criteria = new Criteria(this.page, this.limit);
            criteria.setTerm(this.term);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));
            criteria.addAssociation('salesChannel');

            Object.values(this.internalFilters).forEach((item) => {
                criteria.addFilter(item);
            });

            this.repository = this.repositoryFactory.create('newsletter_recipient');
            this.repository.search(criteria).then((searchResult) => {
                this.items = searchResult;
                this.total = searchResult.total;

                this.isLoading = false;
            }).catch(() => {
                this.isLoading = false;
            });
        },

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

        handleBooleanFilter(filter) {
            if (!Array.isArray(this[filter.group])) {
                this[filter.group] = [];
            }

            if (!filter.value) {
                this[filter.group] = this[filter.group].filter((x) => { return x !== filter.id; });

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

        onChange(filter) {
            if (filter === null) {
                filter = [];
            }

            if (Array.isArray(filter)) {
                this.handleTagFilter(filter);
                this.getList();
                return;
            }

            this.handleBooleanFilter(filter);
            this.getList();
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
            return [{
                property: 'email',
                label: 'sw-newsletter-recipient.list.email',
                routerLink: 'sw.newsletter.recipient.detail',
                allowResize: true,
                inlineEdit: 'string',
            }, {
                property: 'firstName',
                dataIndex: 'firstName,lastName',
                inlineEdit: 'string',
                label: 'sw-newsletter-recipient.list.name',
                allowResize: true,
                primary: true,
            }, {
                property: 'salesChannel.name',
                label: 'sw-newsletter-recipient.list.salesChannel',
                allowResize: true,
                primary: false,
                visible: false,
            }, {
                property: 'status',
                label: 'sw-newsletter-recipient.list.status',
                allowResize: true,
            }, {
                property: 'zipCode',
                label: 'sw-newsletter-recipient.list.zipCode',
                allowResize: true,
                align: 'right',
            }, {
                property: 'city',
                label: 'sw-newsletter-recipient.list.city',
                allowResize: true,
            }, {
                property: 'street',
                label: 'sw-newsletter-recipient.list.street',
                allowResize: true,
                visible: false,
            }, {
                property: 'updatedAt',
                label: 'sw-newsletter-recipient.list.updatedAt',
                allowResize: true,
                visible: false,
            }, {
                property: 'createdAt',
                label: 'sw-newsletter-recipient.list.createdAt',
                allowResize: true,
                visible: false,
            }];
        },
    },
});
