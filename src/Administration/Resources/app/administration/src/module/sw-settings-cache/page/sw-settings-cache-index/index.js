/**
 * @sw-package framework
 */
import { POLL_BACKGROUND_INTERVAL, POLL_FOREGROUND_INTERVAL } from 'src/core/worker/worker-notification-listener';
import template from './sw-settings-cache-index.html.twig';
import './sw-settings-cache-index.scss';

const { Mixin } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: [
        'cacheApiService',
        'feature',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            componentIsBuilding: true,
            isLoading: true,
            cacheInfo: null,
            processes: {
                normalClearCache: false,
                updateIndexes: false,
            },
            processSuccess: {
                normalClearCache: false,
                updateIndexes: false,
            },
            indexingMethod: 'skip',
            indexerSelection: [],
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        httpCacheValue() {
            // adding validation to prevent the console to throw an error.
            if (this.cacheInfo === null) {
                return '';
            }

            return this.cacheInfo.httpCache
                ? this.$tc('sw-settings-cache.toolbar.httpCacheOn')
                : this.$tc('sw-settings-cache.toolbar.httpCacheOff');
        },

        environmentValue() {
            // adding validation to prevent the console to throw an error.
            if (this.cacheInfo === null) {
                return '';
            }

            return this.cacheInfo.environment === 'dev'
                ? this.$tc('sw-settings-cache.toolbar.environmentDev')
                : this.$tc('sw-settings-cache.toolbar.environmentProd');
        },

        cacheAdapterValue() {
            // adding validation to prevent the console to throw an error.
            if (this.cacheInfo === null) {
                return '';
            }

            return this.cacheInfo.cacheAdapter;
        },

        indexers() {
            return this.cacheInfo?.indexers ?? {};
        },
    },

    watch: {
        indexingMethod(value) {
            if (value !== 'only') {
                return;
            }

            this.indexerSelection = this.indexerSelection.filter((selection) =>
                Object.prototype.hasOwnProperty.call(this.indexers, selection),
            );
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.cacheApiService.info().then((result) => {
                this.cacheInfo = result.data;
                this.componentIsBuilding = false;
                this.isLoading = false;
            });
        },

        resetButtons() {
            this.processSuccess = {
                normalClearCache: false,
                updateIndexes: false,
            };
        },

        decreaseWorkerPoll() {
            Shopware.State.commit('notification/setWorkerProcessPollInterval', POLL_FOREGROUND_INTERVAL);

            setTimeout(() => {
                Shopware.State.commit('notification/setWorkerProcessPollInterval', POLL_BACKGROUND_INTERVAL);
            }, 60000);
        },

        clearDataCache() {
            this.createNotificationInfo({
                message: this.$tc('sw-settings-cache.notifications.clearDataCache.started'),
            });

            this.processes.normalClearCache = true;
            this.cacheApiService
                .delayed()
                .then(() => {
                    this.processSuccess.normalClearCache = true;

                    this.createNotificationSuccess({
                        message: this.$tc('sw-settings-cache.notifications.clearDataCache.success'),
                    });
                })
                .catch(() => {
                    this.processSuccess.normalClearCache = false;

                    this.createNotificationError({
                        message: this.$tc('sw-settings-cache.notifications.clearDataCache.error'),
                    });
                })
                .finally(() => {
                    this.processes.normalClearCache = false;
                });
        },

        clearCache() {
            this.createNotificationInfo({
                message: this.$tc('sw-settings-cache.notifications.clearCache.started'),
            });

            this.processes.normalClearCache = true;
            this.cacheApiService
                .clear()
                .then(() => {
                    this.processSuccess.normalClearCache = true;

                    this.createNotificationSuccess({
                        message: this.$tc('sw-settings-cache.notifications.clearCache.success'),
                    });
                })
                .catch(() => {
                    this.processSuccess.normalClearCache = false;

                    this.createNotificationError({
                        message: this.$tc('sw-settings-cache.notifications.clearCache.error'),
                    });
                })
                .finally(() => {
                    this.processes.normalClearCache = false;
                });
        },

        updateIndexes() {
            this.processes.updateIndexes = true;

            let skip = [];
            const only = [];

            if (this.indexingMethod === 'skip') {
                skip = this.indexerSelection;
            } else {
                this.createOnlySelection(only);
            }

            this.cacheApiService
                .index(skip, only)
                .then(() => {
                    this.decreaseWorkerPoll();
                    this.createNotificationInfo({
                        message: this.$tc('sw-settings-cache.notifications.index.started'),
                    });
                    this.processSuccess.updateIndexes = true;
                })
                .catch(() => {
                    this.processSuccess.updateIndexes = false;
                })
                .finally(() => {
                    this.processes.updateIndexes = false;
                });
        },

        changeSelection(selected, name) {
            if (selected) {
                this.indexerSelection.push(name);

                return;
            }

            const selectedIndex = this.indexerSelection.indexOf(name);
            if (selectedIndex > -1) {
                this.indexerSelection.splice(selectedIndex, 1);
            }
        },

        clearIndexerSelection() {
            this.indexerSelection = [];
        },

        createOnlySelection(only) {
            // eslint-disable-next-line no-restricted-syntax
            for (const indexerName of Object.keys(this.indexers)) {
                if (this.indexerSelection.indexOf(indexerName) > -1) {
                    only.push(indexerName);
                }
            }
        },

        /**
         * @deprecated tag:v6.7.0 - Will be removed
         */
        flipIndexers() {
            const leafs = [];

            // eslint-disable-next-line no-restricted-syntax
            for (const [
                indexerName,
                updaters,
            ] of Object.entries(this.indexers)) {
                if (updaters.length > 0) {
                    leafs.push(...updaters);
                } else {
                    leafs.push(indexerName);
                }
            }

            this.indexerSelection = leafs.filter((entry) => this.indexerSelection.indexOf(entry) === -1);
        },
    },
};
