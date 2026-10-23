/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-detail-products.html.twig';
import './sw-sales-channel-detail-products.scss';

const { Mixin, Context } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * Mirrors the limits of the product assignment action. Product groups and categories
 * are resolved against the whole catalog on the server, so their lists stay short.
 */
const ASSIGNMENT_LIMITS = {
    productIds: { max: 1000, message: 'sw-sales-channel.detail.products.messageProductLimitExceeded' },
    productStreamIds: { max: 25, message: 'sw-sales-channel.detail.products.messageProductStreamLimitExceeded' },
    categoryIds: { max: 50, message: 'sw-sales-channel.detail.products.messageCategoryLimitExceeded' },
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'feature',
        'acl',
        'salesChannelProductAssignmentApiService',
    ],

    mixins: [Mixin.getByName('notification')],

    props: {
        salesChannel: {
            type: Object,
            required: false,
            default: () => {},
        },
    },

    data() {
        return {
            products: [],
            productSelection: [],
            isLoading: false,
            searchTerm: null,
            page: 1,
            limit: 25,
            total: 0,
            showProductsModal: false,
            isAssignProductLoading: false,
        };
    },

    computed: {
        productRepository() {
            return this.repositoryFactory.create('product');
        },

        productVisibilityRepository() {
            return this.repositoryFactory.create('product_visibility');
        },

        productCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            criteria.setTotalCountMode(1);

            criteria.addAssociation('visibilities.salesChannel');
            criteria.addAssociation('options.group');
            criteria.addFilter(Criteria.equals('product.visibilities.salesChannelId', this.salesChannel.id));

            if (this.searchTerm) {
                criteria.setTerm(this.searchTerm);
            }

            return criteria;
        },

        productColumns() {
            return [
                {
                    property: 'name',
                    label: this.$t('sw-sales-channel.detail.products.columnProductName'),
                    allowResize: true,
                    primary: true,
                },
                {
                    property: 'active',
                    label: this.$t('sw-sales-channel.detail.products.columnActive'),
                    allowResize: true,
                    align: 'center',
                },
                {
                    property: 'productNumber',
                    label: this.$t('sw-sales-channel.detail.products.columnProductNumber'),
                    allowResize: true,
                },
            ];
        },

        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },
    },

    watch: {
        salesChannel: {
            deep: true,
            immediate: true,
            handler(newValue, oldValue) {
                if (!newValue || oldValue?.id === newValue.id) {
                    return;
                }

                this.getProducts();
            },
        },
    },

    methods: {
        getProducts() {
            if (!this.salesChannel?.id) {
                return Promise.reject();
            }

            const context = { ...Context.api };
            context.inheritance = true;

            this.isLoading = true;
            return this.productRepository
                .search(this.productCriteria, context)
                .then((products) => {
                    this.products = products;
                    this.total = products.total;

                    if (this.total > 0 && this.products.length <= 0) {
                        this.page = this.page === 1 ? 1 : this.page - 1;
                        this.getProducts();
                    }
                })
                .catch(() => {
                    this.products = [];
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onDeleteProduct(product) {
            const deleteId = this.getDeleteId(product);

            return this.productVisibilityRepository
                .delete(deleteId, Context.api)
                .then(() => {
                    this.getProducts();

                    this.$refs.entityListing.resetSelection();
                })
                .catch((error) => {
                    if (error?.response?.data?.errors) {
                        this.showNotificationError(error.response.data.errors);

                        return;
                    }

                    this.createNotificationError({
                        message: error.message,
                    });
                });
        },

        onDeleteProducts() {
            const deleteIds = Object.values(this.productSelection).map((product) => {
                return this.getDeleteId(product);
            });

            this.isLoading = true;
            return this.productVisibilityRepository
                .syncDeleted(deleteIds, Context.api)
                .then(() => {
                    this.isLoading = false;
                    this.getProducts();

                    this.$refs.entityListing.resetSelection();
                })
                .catch((error) => {
                    this.isLoading = false;

                    if (error?.response?.data?.data?.product_visibility?.result) {
                        this.showNotificationError(error.response.data.data.product_visibility.result);

                        return;
                    }

                    this.createNotificationError({
                        message: error.message,
                    });
                });
        },

        getDeleteId(product) {
            return product.visibilities.find((visibility) => {
                return visibility.salesChannelId === this.salesChannel.id;
            }).id;
        },

        showNotificationError(errors) {
            errors.forEach((error) => {
                if (error.errors) {
                    this.showNotificationError(error.errors);
                } else {
                    this.createNotificationError({
                        message: `${error.code}: ${error.detail}`,
                    });
                }
            });
        },

        onChangePage(data) {
            this.page = data.page;
            this.limit = data.limit;
            this.products.criteria.sortings.forEach(({ field, naturalSorting, order }) => {
                this.productCriteria.addSorting(Criteria.sort(field, order, naturalSorting));
            });

            this.getProducts();
        },

        onChangeSearchTerm(searchTerm) {
            this.searchTerm = searchTerm;

            if (searchTerm) {
                this.page = 1;
            }

            this.getProducts();
        },

        openAddProductsModal() {
            this.showProductsModal = true;
        },

        onAddProducts(products, selection = null) {
            const { productIds = products.map(({ id }) => id), productStreamIds = [], categoryIds = [] } = selection ?? {};

            if (productIds.length + productStreamIds.length + categoryIds.length <= 0) {
                this.showProductsModal = false;
                return Promise.reject();
            }

            const exceeded = this.getExceededAssignmentLimit({ productIds, productStreamIds, categoryIds });
            if (exceeded) {
                this.createNotificationError({
                    message: this.$t(exceeded.message, { limit: exceeded.max }),
                });

                return Promise.resolve();
            }

            this.isAssignProductLoading = true;

            return this.salesChannelProductAssignmentApiService
                .assign(this.salesChannel.id, { productIds, productStreamIds, categoryIds })
                .then((assigned) => {
                    this.createNotificationSuccess({
                        message: this.$t(
                            'sw-sales-channel.detail.products.messageProductsAssigned',
                            { count: assigned },
                            assigned,
                        ),
                    });

                    return this.getProducts();
                })
                .catch((error) => {
                    this.createNotificationError({
                        message: error.message,
                    });
                })
                .finally(() => {
                    this.showProductsModal = false;
                    this.isAssignProductLoading = false;
                });
        },

        /**
         * Returns the limit of the first id list that is too long for the assignment action,
         * so the selection can be corrected in the still open modal instead of failing on the server.
         */
        getExceededAssignmentLimit(idLists) {
            const key = Object.keys(ASSIGNMENT_LIMITS).find(
                (listKey) => idLists[listKey].length > ASSIGNMENT_LIMITS[listKey].max,
            );

            return key ? ASSIGNMENT_LIMITS[key] : null;
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed. Use salesChannelProductAssignmentApiService.assign() instead.
         */
        saveProductVisibilities(data) {
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                'sw-sales-channel-detail-products.saveProductVisibilities() is deprecated. ' +
                    'Use salesChannelProductAssignmentApiService.assign() instead.',
            );

            if (data.length <= 0) {
                return Promise.resolve();
            }

            return this.productVisibilityRepository.saveAll(data, Context.api);
        },

        isProductRemovable(product) {
            const relevantVisibility = product.visibilities.find(
                (visibility) => visibility.salesChannelId === this.salesChannel.id,
            );

            return product.parentId !== relevantVisibility?.productId;
        },

        onProductSelectionChanged(selection) {
            this.productSelection = selection;
        },
    },
};
