import template from './sw-extension-store-listing-filter.html.twig';
import './sw-extension-store-listing-filter.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-extension-store-listing-filter', {
    template,

    data() {
        return {
            maxRating: 5
        };
    },

    computed: {
        currentSearch() {
            return Shopware.State.get('shopwareExtensions').search;
        },

        categories() {
            return [{
                name: null,
                details: { name: this.$tc('sw-extension-store.listing.placeHolderCategories') }
            }].concat(Shopware.State.get('shopwareExtensions').storeCategories);
        },

        category: {
            get() {
                return this.currentSearch.category;
            },
            set(category) {
                Shopware.State.commit('shopwareExtensions/setSearchValue', { key: 'category', value: category });
            }
        },

        rating: {
            get() {
                return this.currentSearch.rating;
            },
            set(rating) {
                Shopware.State.commit('shopwareExtensions/setSearchValue', { key: 'rating', value: rating });
            }
        },

        ratings() {
            return [{
                label: this.$tc('sw-extension-store.listing.placeholderRatings'),
                value: null
            }].concat(Array.from({ length: this.maxRating }, (element, index) => {
                const value = this.maxRating - index;
                return {
                    label: `${value}`,
                    value
                };
            }));
        },

        sortings() {
            return [{
                value: 'releaseDate.DESC',
                label: this.$tc('sw-extension-store.listing.sorting.releaseDateDesc')
            }, {
                value: 'releaseDate.ASC',
                label: this.$tc('sw-extension-store.listing.sorting.releaseDateAsc')
            }, {
                value: 'name.ASC',
                label: this.$tc('sw-extension-store.listing.sorting.nameAsc')
            }, {
                value: 'name.DESC',
                label: this.$tc('sw-extension-store.listing.sorting.nameDesc')
            }, {
                value: 'rating.ASC',
                label: this.$tc('sw-extension-store.listing.sorting.ratingAsc')
            }, {
                value: 'rating.DESC',
                label: this.$tc('sw-extension-store.listing.sorting.ratingDesc')
            }];
        },

        sorting: {
            get() {
                if (!this.currentSearch.sorting) {
                    this.sorting = this.sortings[0].value;
                }

                return `${this.currentSearch.sorting.field}.${this.currentSearch.sorting.order}`;
            },
            set(sorting) {
                if (sorting === null) {
                    sorting = this.sortings[0].value;
                }

                const [field, order] = sorting.split('.');

                Shopware.State.commit(
                    'shopwareExtensions/setSearchValue',
                    {
                        key: 'sorting',
                        value: Criteria.sort(field, order)
                    }
                );
            }
        }
    },

    methods: {
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
