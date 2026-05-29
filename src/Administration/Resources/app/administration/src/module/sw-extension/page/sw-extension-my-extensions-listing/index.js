import template from './sw-extension-my-extensions-listing.html.twig';
import './sw-extension-my-extensions-listing.scss';

/**
 * @sw-package checkout
 * @private
 */
export default {
    template,

    inject: [
        'shopwareExtensionService',
        'cacheApiService',
        'acl',
    ],

    data() {
        return {
            filterByActiveState: false,
            sortingOption: 'updated-at',
            selectedNames: [],
            isBulkRunning: false,
        };
    },

    computed: {
        isAppUrlReachable() {
            return Shopware.Store.get('context').app.config.settings?.appUrlReachable;
        },

        isLoading() {
            // Prevents extension listing loading skeleton over the whole grid mid-batch
            if (this.isBulkRunning) {
                return false;
            }

            const state = Shopware.Store.get('shopwareExtensions');

            return state.myExtensions.loading;
        },

        myExtensions() {
            return Shopware.Store.get('shopwareExtensions').myExtensions.data;
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

            return this.extensionListSearched.slice(begin, begin + this.limit);
        },

        extensionListSearched() {
            return this.extensionList.filter((extension) => {
                const searchTerm = this.term && this.term.toLowerCase();
                if (!this.term) {
                    return true;
                }

                const label = extension.label || '';
                const name = extension.name || '';

                return label.toLowerCase().includes(searchTerm) || name.toLowerCase().includes(searchTerm);
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

        skeletonVariant() {
            if (this.isThemeRoute) {
                return 'extension-themes';
            }

            return 'extension-apps';
        },

        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },

        extensionManagementDisabled() {
            return Shopware.Store.get('context').app.config.settings?.disableExtensionManagement;
        },

        selectedExtensions() {
            return this.myExtensions.filter((extension) => this.selectedNames.includes(extension.name));
        },

        hasSelection() {
            return this.selectedExtensions.length > 0;
        },

        canManage() {
            return !this.extensionManagementDisabled && this.acl.can('system.plugin_maintain');
        },

        applicableCounts() {
            const actions = [
                'install',
                'activate',
                'deactivate',
                'update',
                'uninstall',
            ];

            return actions.reduce((counts, action) => {
                counts[action] = this.canManage
                    ? this.selectedExtensions.filter((extension) => this.actionApplies(action, extension)).length
                    : 0;

                return counts;
            }, {});
        },
    },

    watch: {
        '$route.name'() {
            this.updateList();
            this.filterByActiveState = false;
            this.clearSelection();
        },

        // Never act on extensions that are no longer shown.
        '$route.query.term'() {
            this.clearSelection();
        },

        '$route.query.page'() {
            this.clearSelection();
        },
    },

    created() {
        // Holds each card component instance so a bulk action can call its native method
        this.cardRefs = {};
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

        openThemesStore() {
            this.$router.push({
                name: 'sw.extension.store.listing.theme',
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
            return extensions.filter((extension) => {
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
            this.clearSelection();
        },

        filterExtensionsByActiveState(extensions) {
            return extensions.filter((extension) => {
                return extension.active;
            });
        },

        isSelected(extension) {
            return this.selectedNames.includes(extension.name);
        },

        onSelectChange(extension, checked) {
            if (checked) {
                if (!this.selectedNames.includes(extension.name)) {
                    this.selectedNames = [
                        ...this.selectedNames,
                        extension.name,
                    ];
                }
                return;
            }

            this.selectedNames = this.selectedNames.filter((name) => name !== extension.name);
        },

        selectAllVisible() {
            this.selectedNames = this.extensionListPaginated.map((extension) => extension.name);
        },

        clearSelection() {
            this.selectedNames = [];
        },

        actionApplies(action, extension) {
            const installed = extension.installedAt !== null;

            switch (action) {
                case 'install':
                    return !installed;
                case 'activate':
                    return installed && !extension.active;
                case 'deactivate':
                    return installed && extension.active && extension.allowDisable;
                case 'update':
                    return (
                        installed &&
                        extension.allowUpdate &&
                        !!extension.latestVersion &&
                        extension.latestVersion !== extension.version
                    );
                case 'uninstall':
                    return installed;
                default:
                    return false;
            }
        },

        registerCardRef(name, card) {
            if (card) {
                this.cardRefs[name] = card;
            } else {
                delete this.cardRefs[name];
            }
        },

        async runBulkAction(action) {
            const items = this.selectedExtensions.filter((extension) => this.actionApplies(action, extension));

            if (!this.canManage || this.isBulkRunning || items.length === 0) {
                return;
            }

            this.isBulkRunning = true;

            try {
                // Let defer reload prop reach the cards before action runs, so an individual card action does not reload the page mid-batch
                await this.$nextTick();

                // Drive each card own native action method, the exact same code path an individual click takes.
                // So the loading animation and state transitions are identical to a single operation.
                // The cards defer their per item reload, so the page reloads once at the end.
                for (let i = 0; i < items.length; i += 1) {
                    await this.runCardAction(action, items[i]);
                }

                this.clearSelection();

                await this.cacheApiService.clear();
                this._reloadPage();
            } finally {
                this.isBulkRunning = false;
            }
        },

        /** Thin wrapper so tests can spy on navigation without mocking window.location (non-configurable in JSDOM v26). */
        _reloadPage() {
            window.location.reload();
        },

        runCardAction(action, extension) {
            const card = this.cardRefs[extension.name];

            // Selection is per page, so the card is normally mounted; guard defensively anyway.
            if (!card) {
                return Promise.resolve();
            }

            switch (action) {
                case 'install':
                    return card.installExtension();
                case 'activate':
                    return card.activateExtension();
                case 'deactivate':
                    return card.deactivateExtension();
                case 'update':
                    return card.updateExtension();
                case 'uninstall':
                    return card.closeModalAndUninstallExtension(false);
                default:
                    return Promise.resolve();
            }
        },
    },
};
