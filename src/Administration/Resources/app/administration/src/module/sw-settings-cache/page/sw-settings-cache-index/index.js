/**
 * @package system-settings
 */
import { POLL_BACKGROUND_INTERVAL, POLL_FOREGROUND_INTERVAL } from 'src/core/worker/worker-notification-listener';
import template from './sw-settings-cache-index.html.twig';
import './sw-settings-cache-index.scss';

const { Mixin } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
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
            skip: [],
            indexers: {
                'category.indexer': [
                    'category.child-count',
                    'category.tree',
                    'category.breadcrumb',
                    'category.seo-url',
                ],
                'customer.indexer': [
                    'customer.many-to-many-id-field',
                ],
                'landing_page.indexer': [
                    'landing_page.many-to-many-id-field',
                    'landing_page.seo-url',
                ],
                'media.indexer': [],
                'media_folder.indexer': [
                    'media_folder.child-count',
                ],
                'media_folder_configuration.indexer': [],
                'payment_method.indexer': [],
                'product.indexer': [
                    'product.inheritance',
                    'product.stock',
                    'product.variant-listing',
                    'product.child-count',
                    'product.many-to-many-id-field',
                    'product.category-denormalizer',
                    'product.cheapest-price',
                    'product.rating-averaget',
                    'product.stream',
                    'product.search-keyword',
                    'product.seo-url',
                ],
                'product_stream.indexer': [],
                'product_stream_mapping.indexer': [],
                'promotion.indexer': [
                    'promotion.exclusion',
                    'promotion.redemption',
                ],
                'rule.indexer': [
                    'rule.payload',
                ],
                'sales_channel.indexer': [
                    'sales_channel.many-to-many',
                ],
                'flow.indexer': [],
                'newsletter_recipient.indexer': [],
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
            this.cacheApiService.index(this.skip).then(() => {
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

        changeSkip(selected, name) {
            if (selected) {
                this.skip.push(name);

                return;
            }

            const index = this.skip.indexOf(name);

            if (index > -1) {
                this.skip.splice(index, 1);
            }
        },
    },
};
