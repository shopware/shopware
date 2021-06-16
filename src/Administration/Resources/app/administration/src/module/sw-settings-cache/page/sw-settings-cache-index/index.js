import { POLL_BACKGROUND_INTERVAL, POLL_FOREGROUND_INTERVAL } from 'src/core/worker/worker-notification-listener';
import template from './sw-settings-cache-index.html.twig';
import './sw-settings-cache-index.scss';

const { Component, Mixin } = Shopware;

Component.register('sw-settings-cache-index', {
    template,

    inject: [
        'cacheApiService',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],


    data() {
        return {
            isLoading: true,
            cacheInfo: null,
            processes: {
                normalClearCache: false,
                clearAndWarmUpCache: false,
                updateIndexes: false,
            },
            processSuccess: {
                normalClearCache: false,
                clearAndWarmUpCache: false,
                updateIndexes: false,
            },
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

            return this.cacheInfo.httpCache ?
                this.$tc('sw-settings-cache.toolbar.httpCacheOn') :
                this.$tc('sw-settings-cache.toolbar.httpCacheOff');
        },

        environmentValue() {
            // adding validation to prevent the console to throw an error.
            if (this.cacheInfo === null) {
                return '';
            }

            return this.cacheInfo.environment === 'dev' ?
                this.$tc('sw-settings-cache.toolbar.environmentDev') :
                this.$tc('sw-settings-cache.toolbar.environmentProd');
        },

        cacheAdapterValue() {
            // adding validation to prevent the console to throw an error.
            if (this.cacheInfo === null) {
                return '';
            }

            return this.cacheInfo.cacheAdapter;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.cacheApiService.info().then(result => {
                this.cacheInfo = result.data;
                this.isLoading = false;
            });
        },

        resetButtons() {
            this.processSuccess = {
                normalClearCache: false,
                clearAndWarmUpCache: false,
                updateIndexes: false,
            };
        },

        decreaseWorkerPoll() {
            Shopware.State.commit('notification/setWorkerProcessPollInterval', POLL_FOREGROUND_INTERVAL);

            setTimeout(() => {
                Shopware.State.commit('notification/setWorkerProcessPollInterval', POLL_BACKGROUND_INTERVAL);
            }, 60000);
        },

        clearCache() {
            this.createNotificationInfo({
                message: this.$tc('sw-settings-cache.notifications.clearCache.started'),
            });

            this.processes.normalClearCache = true;
            this.cacheApiService.clear().then(() => {
                this.processSuccess.normalClearCache = true;

                this.createNotificationSuccess({
                    message: this.$tc('sw-settings-cache.notifications.clearCache.success'),
                });
            }).catch(() => {
                this.processSuccess.normalClearCache = false;

                this.createNotificationError({
                    message: this.$tc('sw-settings-cache.notifications.clearCache.error'),
                });
            }).finally(() => {
                this.processes.normalClearCache = false;
            });
        },

        clearAndWarmUpCache() {
            this.processes.clearAndWarmUpCache = true;
            this.cacheApiService.clearAndWarmup().then(() => {
                this.decreaseWorkerPoll();
                setTimeout(() => {
                    this.cacheApiService.cleanupOldCaches();
                }, 30000);

                this.createNotificationInfo({
                    message: this.$tc('sw-settings-cache.notifications.clearCacheAndWarmup.started'),
                });

                this.processSuccess.clearAndWarmUpCache = true;
            }).catch(() => {
                this.processSuccess.clearAndWarmUpCache = false;
            }).finally(() => {
                this.processes.clearAndWarmUpCache = false;
            });
        },

        updateIndexes() {
            this.processes.updateIndexes = true;
            this.cacheApiService.index().then(() => {
                this.decreaseWorkerPoll();
                this.createNotificationInfo({
                    message: this.$tc('sw-settings-cache.notifications.index.started'),
                });
                this.processSuccess.updateIndexes = true;
            }).catch(() => {
                this.processSuccess.updateIndexes = false;
            }).finally(() => {
                this.processes.updateIndexes = false;
            });
        },
    },
});
