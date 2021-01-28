import template from './sw-extension-store-listing-filter.html.twig';
import './sw-extension-store-listing-filter.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-extension-store-listing-filter', {
    template,

    mixins: [
        'notification'
    ],

    inject: ['extensionStoreDataService'],

    data() {
        return {
            isLoading: true,
            listingFilters: [],
            listingSorting: {}
        };
    },

    computed: {
        search() {
            return Shopware.State.get('shopwareExtensions').search;
        },

        activeFilters: {
            get() {
                return Shopware.State.get('shopwareExtensions').search.filter;
            },
            set(newFilter) {
                Shopware.State.get('shopwareExtensions').search.filter = newFilter;
            }
        },

        sortingOptions() {
            if (!this.listingSorting.options) {
                return [];
            }

            return this.listingSorting.options.map(option => {
                option.orderIdentifier = `${option.orderBy}##${option.orderSequence}`;

                return option;
            });
        },

        defaultSortingValue() {
            if (!this.listingSorting.default) {
                return null;
            }

            return `${this.listingSorting.default.orderBy}##${this.listingSorting.default.orderSequence}`;
        },

        sortingValue() {
            const field = this.search.sorting && this.search.sorting.field;
            const order = this.search.sorting && this.search.sorting.order;

            if (!field || !order) {
                return this.defaultSortingValue;
            }

            // Sorting value contains field and order which are seperated by two #
            return `${field}##${order}`;
        },

        listingFiltersSorted() {
            const listingFiltersCopy = [...this.listingFilters];

            // sort filters
            listingFiltersCopy.sort((a, b) => a.position - b.position);

            // sort options in each filter
            listingFiltersCopy.forEach(filter => {
                filter.options.sort((a, b) => a.position - b.position);
            });

            // special case for categories - add children behind parents
            const categoryFilter = listingFiltersCopy.find(filter => filter.type === 'category');

            if (categoryFilter) {
                const rootOptions = categoryFilter.options.filter(option => option.parent === null);

                categoryFilter.options = rootOptions.reduce((acc, rootOption) => {
                    const children = categoryFilter.options.filter(option => option.parent === rootOption.value);

                    acc.push(rootOption, ...children);

                    return acc;
                }, []);
            }

            return listingFiltersCopy;
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.fetchListingFilters();
        },

        fetchListingFilters() {
            return this.extensionStoreDataService.listingFilters()
                .then(({ filter, sorting }) => {
                    this.listingFilters = filter;
                    this.listingSorting = sorting;
                })
                .catch((e) => {
                    this.createNotificationError({
                        message: e
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        getValueForFilter(filter) {
            return this.activeFilters[filter.name] || null;
        },

        changeValueForFilter(filter, value) {
            if (!value) {
                this.$delete(this.activeFilters, filter.name);
                return;
            }

            this.$set(this.activeFilters, filter.name, value);
        },

        getOptionsForFilter(filter) {
            const anyOption = {
                label: this.$tc('sw-extension.store.listing.anyOption'),
                value: null
            };

            return [
                anyOption,
                ...filter.options
            ];
        },

        setSelectedSorting(orderIdentifier) {
            // Sorting value contains field and order which are seperated by two #
            //  Here we extract both values
            const [field, order] = orderIdentifier.split('##');

            Shopware.State.commit(
                'shopwareExtensions/setSearchValue',
                { key: 'sorting', value: Criteria.sort(field, order) }
            );
        },

        isRootCategory(category) {
            return category.parent === null || typeof category.parent === 'undefined';
        },

        categoryDepth(category) {
            let depth = 0;
            let currentParent = category.parent;

            while (currentParent) {
                depth += 1;
                currentParent = currentParent.parent;
            }

            return depth;
        }
    }
});
