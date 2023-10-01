/**
 * @package services-settings
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
                updateIndexes: false,
            },
            processSuccess: {
                normalClearCache: false,
                updateIndexes: false,
            },
            indexingMethod: 'skip',
            indexerSelection: [],
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

        updateIndexes() {
            this.processes.updateIndexes = true;

            let skip = [];
            let only = [];

            if (this.indexingMethod === 'skip') {
                skip = this.indexerSelection;
            } else {
                // eslint-disable-next-line no-restricted-syntax
                for (const [indexerName, updaters] of Object.entries(this.indexers)) {
                    if (this.indexerSelection.indexOf(indexerName) > -1) {
                        only.push(indexerName);
                    }

                    const selectedUpdaters = [];

                    // eslint-disable-next-line no-restricted-syntax
                    for (const updater of updaters) {
                        if (this.indexerSelection.indexOf(updater) > -1) {
                            selectedUpdaters.push(updater);
                        }
                    }

                    if (selectedUpdaters.length > 0) {
                        only.push(indexerName);
                    }

                    only.push(...selectedUpdaters);
                }
            }

            this.cacheApiService.index(skip, only).then(() => {
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

        changeSelection(selected, name) {
            if (selected) {
                this.indexerSelection.push(name);

                return;
            }

            const index = this.indexerSelection.indexOf(name);

            if (index > -1) {
                this.indexerSelection.splice(index, 1);
            }
        },

        flipIndexers() {
            const leafs = [];

            // eslint-disable-next-line no-restricted-syntax
            for (const [indexerName, updaters] of Object.entries(this.indexers)) {
                if (updaters.length > 0) {
                    leafs.push(...updaters);
                } else {
                    leafs.push(indexerName);
                }
            }

            this.indexerSelection = leafs.filter(entry => this.indexerSelection.indexOf(entry) === -1);
        },
    },
};
