/**
 * @sw-package framework
 */

import template from './sw-license-violation.html.twig';
import './sw-license-violation.scss';

/**
 * @private
 */
export default {
    template,

    inject: [
        'cacheApiService',
        'extensionStoreActionService',
        'licenseViolationService',
        'loginService',
    ],

    mixins: [
        Shopware.Mixin.getByName('notification'),
    ],

    data() {
        return {
            licenseSubscription: null,
            showViolation: false,
            readNotice: false,
            loading: [],
            showDeleteModal: false,
            deletePluginItem: null,
        };
    },

    computed: {
        violations() {
            return Shopware.Store.get('licenseViolation').violations;
        },

        warnings() {
            return Shopware.Store.get('licenseViolation').warnings;
        },

        visible() {
            if (!this.showViolation) {
                return false;
            }

            return this.violations.length > 0;
        },

        pluginCriteria() {
            return new Shopware.Data.Criteria(1, 50);
        },

        isLoading() {
            return this.loading.length > 0;
        },
    },

    watch: {
        $route: {
            handler() {
                this.$nextTick(() => {
                    this.getPluginViolation();
                });
            },
            immediate: true,
        },
        visible: {
            handler(newValue) {
                if (newValue !== true) {
                    return;
                }

                this.fetchPlugins();
            },
            immediate: true,
        },
    },

    methods: {
        getPluginViolation() {
            if (!this.loginService.isLoggedIn()) {
                return Promise.resolve();
            }

            this.showViolation = this.licenseViolationService.isTimeExpired(
                this.licenseViolationService.key.showViolationsKey,
            );

            this.addLoading('getPluginViolation');

            return this.licenseViolationService
                .checkForLicenseViolations()
                .then(({ violations, warnings, other }) => {
                    const licenseViolationStore = Shopware.Store.get('licenseViolation');
                    const updateViolationStore = (currentViolations) => {
                        licenseViolationStore.violations = currentViolations;
                        licenseViolationStore.warnings = warnings;
                        licenseViolationStore.other = other;
                    };

                    if (!this.showViolation || violations.length === 0) {
                        updateViolationStore(violations);

                        return;
                    }

                    return this.extensionStoreActionService
                        .getMyExtensions()
                        .then((extensions) => {
                            const installedExtensionNames = new Set(extensions.map((extension) => extension.name));
                            const currentViolations = violations.filter((violation) => {
                                return installedExtensionNames.has(violation.name);
                            });
                            const cachedViolations = this.licenseViolationService.getViolationsFromCache();

                            // Remove stale cached violations only when the modal is about to display them.
                            this.licenseViolationService.saveViolationsToCache(
                                cachedViolations.filter((violation) => installedExtensionNames.has(violation.name)),
                            );

                            updateViolationStore(currentViolations);
                        })
                        .catch(() => {
                            // Keep cached violations when the local lookup fails so a temporary API error
                            // does not hide valid violations.
                            updateViolationStore(violations);
                        });
                })
                .finally(() => {
                    this.finishLoading('getPluginViolation');
                });
        },

        reloadViolations() {
            this.licenseViolationService.resetLicenseViolations();

            return this.getPluginViolation();
        },

        deactivateTemporary() {
            this.licenseViolationService.saveTimeToLocalStorage(this.licenseViolationService.key.showViolationsKey);

            this.readNotice = false;
            this.showViolation = this.licenseViolationService.isTimeExpired(
                this.licenseViolationService.key.showViolationsKey,
            );
        },

        fetchPlugins() {
            if (!this.loginService.isLoggedIn()) {
                return;
            }

            this.addLoading('fetchPlugins');

            this.extensionStoreActionService
                .getMyExtensions()
                .then((response) => {
                    this.plugins = response;
                })
                .finally(() => {
                    this.finishLoading('fetchPlugins');
                });
        },

        deletePlugin(violation) {
            this.deletePluginItem = violation;
            this.showDeleteModal = true;
        },

        onCloseDeleteModal() {
            this.deletePluginItem = null;
            this.showDeleteModal = false;
        },

        onConfirmDelete() {
            const violation = this.deletePluginItem;

            this.showDeleteModal = false;
            this.addLoading('deletePlugin');

            const matchingPlugin = this.plugins.find((plugin) => plugin.name === violation.name);

            if (!matchingPlugin) {
                this.licenseViolationService.resetLicenseViolations();

                const licenseViolationStore = Shopware.Store.get('licenseViolation');
                licenseViolationStore.violations = licenseViolationStore.violations.filter(
                    (item) => item.name !== violation.name,
                );

                this.createNotificationInfo({
                    message: this.$t('sw-license-violation.alreadyRemoved'),
                });

                this.finishLoading('deletePlugin');

                return Promise.resolve();
            }

            return this.licenseViolationService
                .forceDeletePlugin(matchingPlugin)
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$t('sw-license-violation.successfullyDeleted'),
                    });

                    return this.reloadViolations();
                })
                .finally(() => {
                    this.finishLoading('deletePlugin');
                });
        },

        getPluginForViolation(violation) {
            if (!Array.isArray(this.plugins)) {
                return null;
            }

            const matchingPlugin = this.plugins.find((plugin) => {
                return plugin.name === violation.name;
            });

            return matchingPlugin || null;
        },

        addLoading(key) {
            this.loading.push(key);
        },

        finishLoading(key) {
            this.loading = this.loading.filter((value) => value !== key);
        },
    },
};
