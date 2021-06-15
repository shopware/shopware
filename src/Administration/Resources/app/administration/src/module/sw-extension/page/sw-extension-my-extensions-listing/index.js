import template from './sw-extension-my-extensions-listing.html.twig';
import './sw-extension-my-extensions-listing.scss';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-extension-my-extensions-listing', {
    template,

    inject: ['shopwareExtensionService'],

    data() {
        return {
            filterByActiveState: false,
            sortingOption: 'updated-at',
        };
    },

    computed: {
        isLoading() {
            const state = Shopware.State.get('shopwareExtensions');

            return state.myExtensions.loading;
        },

        myExtensions() {
            return Shopware.State.get('shopwareExtensions').myExtensions.data;
        },

        extensionList() {
            const byTypeFilteredExtensions = this.filterExtensionsByType(this.myExtensions);
            const sortedExtensions = this.sortExtensions(byTypeFilteredExtensions, this.sortingOption);

            if (this.filterByActiveState) {
                return this.filterExtensionsByActiveState(sortedExtensions);
            }

            return sortedExtensions;
        },

        extensionListPaginated() {
            const begin = (this.page - 1) * this.limit;

            return this.extensionListSearched
                .slice(begin, begin + this.limit);
        },

        extensionListSearched() {
            return this.extensionList
                .filter(extension => {
                    const searchTerm = this.term && this.term.toLowerCase();
                    if (!this.term) {
                        return true;
                    }

                    const label = extension.label || '';
                    const name = extension.name || '';

                    return label.toLowerCase().includes(searchTerm) ||
                        name.toLowerCase().includes(searchTerm);
                });
        },

        isAppRoute() {
            return this.$route.name === 'sw.extension.my-extensions.listing.app';
        },

        isThemeRoute() {
            return this.$route.name === 'sw.extension.my-extensions.listing.theme';
        },

        total() {
            return this.extensionListSearched.length || 0;
        },

        limit: {
            get() {
                return Number(this.$route.query.limit) || 25;
            },
            set(newLimit) {
                this.updateRouteQuery({ limit: newLimit });
            },
        },

        page: {
            get() {
                return Number(this.$route.query.page) || 1;
            },
            set(newPage) {
                this.updateRouteQuery({ page: newPage });
            },
        },

        term: {
            get() {
                return this.$route.query.term || undefined;
            },

            set(newTerm) {
                this.updateRouteQuery({
                    term: newTerm,
                    page: 1,
                });
            },
        },
    },

    watch: {
        '$route.name'() {
            this.updateList();
            this.filterByActiveState = false;
        },
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            this.updateList();
            this.updateRouteQuery();
        },

        updateList() {
            this.shopwareExtensionService.updateExtensionData();
        },

        openStore() {
            this.$router.push({
                name: 'sw.extension.store.listing',
            });
        },

        updateRouteQuery(query = {}) {
            const routeQuery = this.$route.query;
            const limit = query.limit || this.$route.query.limit;
            const page = query.page || this.$route.query.page;
            const term = query.term || this.$route.query.term;

            // Create new route
            const route = {
                name: this.$route.name,
                params: this.$route.params,
                query: {
                    limit: limit || 25,
                    page: page || 1,
                    term: term || undefined,
                },
            };

            // If query is empty then replace route, otherwise push
            if (Shopware.Utils.types.isEmpty(routeQuery)) {
                this.$router.replace(route);
            } else {
                this.$router.push(route);
            }
        },

        changePage({ page, limit }) {
            this.updateRouteQuery({ page, limit });
        },

        filterExtensionsByType(extensions) {
            return extensions.filter(extension => {
                // app route and no theme
                if (this.isAppRoute && !extension.isTheme) {
                    return true;
                }

                // theme route and theme
                if (this.isThemeRoute && extension.isTheme) {
                    return true;
                }

                return false;
            });
        },

        sortExtensions(extensions, sortingOption) {
            return extensions.sort((firstExtension, secondExtension) => {
                if (sortingOption === 'name-asc') {
                    return firstExtension.label.localeCompare(secondExtension.label, { sensitivity: 'base' });
                }

                if (sortingOption === 'name-desc') {
                    return firstExtension.label.localeCompare(secondExtension.label, { sensitivity: 'base' }) * -1;
                }

                if (sortingOption === 'updated-at') {
                    if (firstExtension.updatedAt === null && secondExtension.updatedAt !== null) {
                        return 1;
                    }

                    if (firstExtension.updatedAt !== null && secondExtension.updatedAt === null) {
                        return -1;
                    }

                    if (secondExtension.updatedAt === null && firstExtension.updatedAt === null) {
                        return 0;
                    }

                    const firstExtensionDate = new Date(firstExtension.updatedAt.date);
                    const secondExtensionDate = new Date(secondExtension.updatedAt.date);

                    if (firstExtensionDate > secondExtensionDate) {
                        return -1;
                    }

                    if (firstExtensionDate < secondExtensionDate) {
                        return 1;
                    }

                    if (firstExtensionDate === secondExtensionDate) {
                        return 0;
                    }
                }

                return 0;
            });
        },

        changeSortingOption(value) {
            this.sortingOption = value;
        },

        changeActiveState(value) {
            this.filterByActiveState = value;
        },

        filterExtensionsByActiveState(extensions) {
            return extensions.filter(extension => {
                return extension.active;
            });
        },
    },
});
