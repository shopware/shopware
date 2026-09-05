import { isAirGapped } from 'src/core/helper/air-gapped.helper';
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
        'extensionStoreActionService',
        'cacheApiService',
        'acl',
    ],

    mixins: ['sw-extension-error'],

    data() {
        return {
            filterByActiveState: false,
            selectedNames: [],
            isBulkRunning: false,
            bulkProcessingNames: [],
            bulkConsent: null,
            showBulkConsentModal: false,
            showBulkUninstallModal: false,
            bulkUninstallItems: [],
            showBulkDeactivationModal: false,
            bulkDeactivationItems: [],
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

        sortingOption: {
            get() {
                const sorting = this.$route.query.sorting;

                return [
                    'updated-at',
                    'name-asc',
                    'name-desc',
                ].includes(sorting)
                    ? sorting
                    : 'updated-at';
            },

            set(newSorting) {
                this.updateRouteQuery({ sorting: newSorting });
            },
        },

        skeletonVariant() {
            if (this.isThemeRoute) {
                return 'extension-themes';
            }

            return 'extension-apps';
        },

        emptyStateHeadline() {
            return this.isThemeRoute
                ? this.$t('sw-extension-store.component.sw-extension-my-extensions-listing.themes.titleEmptyState')
                : this.$t('sw-extension-store.component.sw-extension-my-extensions-listing.apps.titleEmptyState');
        },

        emptyStateDescription() {
            return this.isThemeRoute
                ? this.$t('sw-extension-store.component.sw-extension-my-extensions-listing.themes.textEmptyState')
                : this.$t('sw-extension-store.component.sw-extension-my-extensions-listing.apps.textEmptyState');
        },

        noActivePluginsHeadline() {
            return this.isThemeRoute
                ? this.$t('sw-extension-store.component.sw-extension-my-extensions-listing.themes.noActivePlugins')
                : this.$t('sw-extension-store.component.sw-extension-my-extensions-listing.apps.noActivePlugins');
        },

        /** @deprecated tag:v6.9.0 - Will be removed, use Shopware.Filter.getByName('asset') instead. */
        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },

        extensionManagementDisabled() {
            return Shopware.Store.get('context').app.config.settings?.disableExtensionManagement;
        },

        airGapped() {
            return isAirGapped();
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

        bulkConsentCount() {
            return this.bulkConsent?.items.length ?? 0;
        },

        bulkConsentTitle() {
            const key = this.bulkConsent?.action === 'update' ? 'updateTitle' : 'installTitle';

            return this.$t(
                `sw-extension.my-extensions.bulk.consent.${key}`,
                { count: this.bulkConsentCount },
                this.bulkConsentCount,
            );
        },

        bulkConsentDescription() {
            const key = this.bulkConsent?.action === 'update' ? 'updateDescription' : 'installDescription';

            return this.$t(`sw-extension.my-extensions.bulk.consent.${key}`);
        },

        bulkConsentActionLabel() {
            const key = this.bulkConsent?.action === 'update' ? 'updateAction' : 'installAction';

            return this.$t(`sw-extension.my-extensions.bulk.consent.${key}`);
        },

        bulkConsentExtensionLabel() {
            return this.$t(
                'sw-extension.my-extensions.bulk.consent.extensionLabel',
                { count: this.bulkConsentCount },
                this.bulkConsentCount,
            );
        },

        rentedBulkDeactivationItems() {
            return this.bulkDeactivationItems.filter((extension) => this.isRentedExtension(extension));
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

        // Decreasing the page size could hide a selected extension, so drop the selected extensions
        '$route.query.limit'() {
            this.clearSelection();
        },

        // Sorting reorders the page and can put a selected extension out of view.
        sortingOption() {
            this.clearSelection();
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
            const sorting = query.sorting || this.$route.query.sorting;

            // Create new route
            const route = {
                name: this.$route.name,
                params: this.$route.params,
                query: {
                    limit: limit || 25,
                    page: page || 1,
                    term: term || undefined,
                    sorting: sorting || 'updated-at',
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

        isRentedExtension(extension) {
            return extension.storeLicense?.variant === this.shopwareExtensionService.EXTENSION_VARIANT_TYPES.RENT;
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

        async runBulkAction(action) {
            const items = this.selectedExtensions.filter((extension) => this.actionApplies(action, extension));

            if (!this.canManage || this.isBulkRunning || items.length === 0) {
                return;
            }

            this.isBulkRunning = true;

            try {
                // Each action mirrors its single extension flow:
                //  - install: gate on permissions up front: no permissions -> install and activate immediately.
                //  - update: attempt the update first: only items the backend rejects for new privileges open a aggregated consent modal.
                //  - uninstall: always confirm first with a data removal choice.
                //  - deactivate: batches containing rented extensions confirm first that subscription fees continue.
                //  - activate: no consent, just run directly.
                switch (action) {
                    case 'install':
                        await this.startBulkInstall(items);
                        return;
                    case 'update':
                        await this.startBulkUpdate(items);
                        return;
                    case 'uninstall':
                        this.bulkUninstallItems = items;
                        this.showBulkUninstallModal = true;
                        return;
                    case 'deactivate': {
                        if (items.some((extension) => this.isRentedExtension(extension))) {
                            this.bulkDeactivationItems = items;
                            this.showBulkDeactivationModal = true;
                            return;
                        }

                        const results = await this.applyBulk(action, items);
                        await this.finalizeBulkRun({ reload: this.hasAppliedChanges(results) });
                        return;
                    }
                    default: {
                        const results = await this.applyBulk(action, items);
                        await this.finalizeBulkRun({ reload: this.hasAppliedChanges(results) });
                    }
                }
            } catch (e) {
                this.showExtensionErrors(e);
                this.isBulkRunning = false;
            }
        },

        /**
         * @returns {Promise<{ status: 'success' | 'failed' | 'requiresConsent', extension, deltas? }>}
         */
        async runExtensionAction(action, extension, options = {}) {
            this.markBulkProcessing(extension.name);

            try {
                switch (action) {
                    case 'install':
                        if (extension.source === 'store') {
                            await this.extensionStoreActionService.downloadExtension(extension.name);
                        }
                        // Mirror the single card: an extension without permissions is installed AND activated
                        if (Object.keys(extension.permissions || {}).length > 0) {
                            await this.shopwareExtensionService.installExtension(extension.name, extension.type);
                        } else {
                            await this.shopwareExtensionService.installAndActivateExtension(extension.name, extension.type);
                        }
                        break;
                    case 'activate':
                        await this.shopwareExtensionService.activateExtension(extension.name, extension.type);
                        break;
                    case 'deactivate':
                        await this.shopwareExtensionService.deactivateExtension(extension.name, extension.type);
                        break;
                    case 'update':
                        if (extension.updateSource === 'store') {
                            await this.extensionStoreActionService.downloadExtension(extension.name);
                        }
                        if (extension.installedAt) {
                            await this.shopwareExtensionService.updateExtension(
                                extension.name,
                                extension.type,
                                options.allowNewPermissions ?? false,
                            );
                        }
                        break;
                    case 'uninstall':
                        await this.shopwareExtensionService.uninstallExtension(
                            extension.name,
                            extension.type,
                            options.removeData ?? false,
                        );
                        break;
                    default:
                        break;
                }

                return { status: 'success', extension };
            } catch (e) {
                const error = e.response?.data?.errors?.[0];

                // An update that needs new privileges is not a failure, it needs consent before it can apply.
                if (error?.code === 'FRAMEWORK__EXTENSION_UPDATE_REQUIRES_CONSENT_AFFIRMATION') {
                    return { status: 'requiresConsent', extension, deltas: error.meta.parameters.deltas };
                }

                this.showExtensionErrors(e);

                return { status: 'failed', extension };
            } finally {
                this.unmarkBulkProcessing(extension.name);
            }
        },

        markBulkProcessing(name) {
            if (!this.bulkProcessingNames.includes(name)) {
                this.bulkProcessingNames = [
                    ...this.bulkProcessingNames,
                    name,
                ];
            }
        },

        unmarkBulkProcessing(name) {
            this.bulkProcessingNames = this.bulkProcessingNames.filter((processing) => processing !== name);
        },

        async applyBulk(action, items, options = {}) {
            const results = [];

            for (let i = 0; i < items.length; i += 1) {
                results.push(await this.runExtensionAction(action, items[i], options));
            }

            return results;
        },

        // At least one item of a batch actually applied a change.
        hasAppliedChanges(results) {
            return results.some((result) => result.status === 'success');
        },

        // Shared tail for every bulk flow: clear selection and release the lock.
        // When nothing changed (every item failed) the page is left as it is.
        async finalizeBulkRun({ reload = true } = {}) {
            this.clearSelection();
            this.bulkProcessingNames = [];

            if (reload) {
                await this.cacheApiService.clear();
                this._reloadPage();
            }

            this.isBulkRunning = false;
        },

        // Aggregate permissions and domains across the given items into the shape sw-extension-permissions-modal.
        aggregateConsent(items) {
            const permissions = {};
            const seen = new Set();
            const domains = new Set();

            items.forEach((item) => {
                Object.entries(item.permissions || {}).forEach(
                    ([
                        category,
                        perms,
                    ]) => {
                        (perms || []).forEach((perm) => {
                            const key = `${category}|${perm.entity}|${perm.operation}`;
                            if (seen.has(key)) {
                                return;
                            }
                            seen.add(key);

                            if (!permissions[category]) {
                                permissions[category] = [];
                            }
                            permissions[category].push(perm);
                        });
                    },
                );

                (item.domains || []).forEach((domain) => domains.add(domain));
            });

            return { permissions, domains: [...domains] };
        },

        async startBulkInstall(items) {
            const { permissions, domains } = this.aggregateConsent(items);

            if (Object.keys(permissions).length === 0) {
                const results = await this.applyBulk('install', items);
                await this.finalizeBulkRun({ reload: this.hasAppliedChanges(results) });
                return;
            }

            this.bulkConsent = { action: 'install', items, permissions, domains };
            this.showBulkConsentModal = true;
        },

        async startBulkUpdate(items) {
            const results = await this.applyBulk('update', items);
            const deltaItems = results.filter((result) => result.status === 'requiresConsent');

            if (deltaItems.length === 0) {
                await this.finalizeBulkRun({ reload: this.hasAppliedChanges(results) });
                return;
            }

            const consentItems = deltaItems.map((deltaItem) => ({
                name: deltaItem.extension.name,
                type: deltaItem.extension.type,
                permissions: deltaItem.deltas.permissions,
                domains: deltaItem.deltas.domains,
            }));
            const { permissions, domains } = this.aggregateConsent(consentItems);

            this.bulkConsent = {
                action: 'update',
                items: deltaItems.map((deltaItem) => deltaItem.extension),
                permissions,
                domains,
                changesApplied: results.some((result) => result.status === 'success'),
            };
            this.showBulkConsentModal = true;
        },

        async onBulkConsentAccept() {
            this.showBulkConsentModal = false;
            const consent = this.bulkConsent;
            this.bulkConsent = null;

            let results;
            if (consent.action === 'install') {
                results = await this.applyBulk('install', consent.items);
            } else {
                // Re-run only the consent required updates, now permitting the new privileges.
                results = await this.applyBulk('update', consent.items, { allowNewPermissions: true });
            }

            // Clean updates already applied during the preflight also count as changes that need the reload.
            const reload = (consent.changesApplied ?? false) || this.hasAppliedChanges(results);
            await this.finalizeBulkRun({ reload });
        },

        async onBulkConsentCancel() {
            const changesApplied = this.bulkConsent?.changesApplied ?? false;

            this.showBulkConsentModal = false;
            this.bulkConsent = null;

            if (changesApplied) {
                await this.finalizeBulkRun({ reload: true });
                return;
            }

            this.isBulkRunning = false;
        },

        async confirmBulkUninstall(removeData) {
            // Mirror the single uninstall modal: close it immediately, then run the uninstalls.
            this.showBulkUninstallModal = false;

            const items = this.bulkUninstallItems;
            this.bulkUninstallItems = [];

            const results = await this.applyBulk('uninstall', items, { removeData });

            await this.finalizeBulkRun({ reload: this.hasAppliedChanges(results) });
        },

        cancelBulkUninstall() {
            this.showBulkUninstallModal = false;
            this.bulkUninstallItems = [];
            this.isBulkRunning = false;
        },

        async confirmBulkDeactivation() {
            // Mirror the single deactivation modal: close it immediately, then run the deactivations.
            this.showBulkDeactivationModal = false;

            const items = this.bulkDeactivationItems;
            this.bulkDeactivationItems = [];

            const results = await this.applyBulk('deactivate', items);

            await this.finalizeBulkRun({ reload: this.hasAppliedChanges(results) });
        },

        cancelBulkDeactivation() {
            this.showBulkDeactivationModal = false;
            this.bulkDeactivationItems = [];
            this.isBulkRunning = false;
        },

        /** Thin wrapper so tests can spy on navigation without mocking window.location (non-configurable in JSDOM v26). */
        _reloadPage() {
            window.location.reload();
        },
    },
};
