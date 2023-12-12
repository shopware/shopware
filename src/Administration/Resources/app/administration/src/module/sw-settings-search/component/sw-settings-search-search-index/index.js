/**
 * @package buyers-experience
 */
import template from './sw-settings-search-search-index.html.twig';
import './sw-settings-search-search-index.scss';

const PRODUCT_INDEXER_INTERVAL = 3000;
const { Mixin, Context } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'productIndexService',
        'repositoryFactory',
        'acl',
    ],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            isLoading: true,
            isRebuildSuccess: false,
            isRebuildInProgress: false,
            progressBarValue: 0,
            offset: 0,
            syncPolling: null,
            totalProduct: 0,
            latestIndex: null,
        };
    },

    computed: {
        productRepository() {
            return this.repositoryFactory.create('product');
        },

        productSearchKeywordRepository() {
            return this.repositoryFactory.create('product_search_keyword');
        },

        productCriteria() {
            const productCriteria = new Criteria(1, 1);
            productCriteria.addFilter(Criteria.equals('product.parentId', null));
            return productCriteria;
        },

        productSearchKeywordsCriteria() {
            const criteria = new Criteria(1, 1);
            criteria.addAggregation(Criteria.min('firstDate', 'createdAt'));
            criteria.addAggregation(Criteria.max('lastDate', 'createdAt'));
            return criteria;
        },
    },

    created() {
        this.createdComponent();
    },

    beforeDestroy() {
        this.beforeDestroyComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = false;
            this.getTotalProduct();
            this.getLatestProductKeywordIndexed();
        },

        beforeDestroyComponent() {
            this.clearPolling();
        },

        getLatestProductKeywordIndexed() {
            this.isLoading = true;
            this.productSearchKeywordRepository.search(this.productSearchKeywordsCriteria, Context.api)
                .then((result) => {
                    this.latestIndex = {
                        firstDate: result.aggregations.firstDate.min,
                        lastDate: result.aggregations.lastDate.max,
                    };
                })
                .catch((err) => {
                    this.createNotificationError({
                        message: err.message,
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        getTotalProduct() {
            this.isLoading = true;
            this.productRepository.search(this.productCriteria, Context.api)
                .then((result) => {
                    this.totalProduct = result?.total;
                })
                .catch((err) => {
                    this.createNotificationError({
                        message: err.message,
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        updateProgress() {
            this.productIndexService.index(this.offset)
                .then((response) => {
                    const data = response.data;
                    this.isRebuildSuccess = data.finish;

                    if (this.isRebuildSuccess) {
                        this.clearPolling();
                        this.getLatestProductKeywordIndexed();
                        this.progressBarValue = 100;
                        this.createNotificationSuccess({
                            message: this.$tc('sw-settings-search.notification.index.success'),
                        });
                    } else {
                        this.progressBarValue = ((this.offset ?? 1) / this.totalProduct) * 100;
                        this.offset = data.offset.offset;
                        this.updateProgress();
                    }
                })
                .catch((err) => {
                    this.createNotificationError({
                        message: err.message,
                    });
                    this.isRebuildSuccess = false;
                });
        },

        pollData() {
            if (this.syncPolling === null) {
                this.syncPolling = setTimeout(
                    this.updateProgress,
                    PRODUCT_INDEXER_INTERVAL,
                );
            }
        },

        clearPolling() {
            if (this.syncPolling !== null) {
                clearTimeout(this.syncPolling);
                this.syncPolling = null;
            }
        },

        rebuildSearchIndex() {
            this.isRebuildInProgress = true;
            this.progressBarValue = 1;
            this.offset = 0;

            this.$emit('edit-change', this.isRebuildInProgress);
            this.pollData();
            this.createNotificationInfo({
                message: this.$tc('sw-settings-search.notification.index.started'),
            });
        },

        buildFinish() {
            this.isRebuildSuccess = false;
            this.isRebuildInProgress = false;
            this.progressBarValue = 0;
            this.$emit('edit-change', this.isRebuildInProgress);
        },
    },
};
