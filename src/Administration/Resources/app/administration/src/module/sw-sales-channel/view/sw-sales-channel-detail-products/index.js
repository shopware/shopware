import template from './sw-sales-channel-detail-products.html.twig';
import './sw-sales-channel-detail-products.scss';

const { Component, Mixin, Context } = Shopware;
const { EntityCollection, Criteria } = Shopware.Data;

Component.register('sw-sales-channel-detail-products', {
    template,

    inject: ['repositoryFactory', 'feature', 'acl'],

    mixins: [
        Mixin.getByName('notification'),
    ],

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
            isLoading: false,
            isBulkLoading: false,
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
            const criteria = new Criteria();

            criteria.setPage(this.page);
            criteria.setLimit(this.limit);
            criteria.setTotalCountMode(1);

            criteria.addAssociation('visibilities.salesChannel');
            criteria.addFilter(
                Criteria.equals('product.visibilities.salesChannelId', this.salesChannel.id),
            );

            if (this.searchTerm) {
                criteria.setTerm(this.searchTerm);
            }

            return criteria;
        },

        productColumns() {
            return [
                {
                    property: 'name',
                    label: this.$tc('sw-sales-channel.detail.products.columnProductName'),
                    allowResize: true,
                    primary: true,
                    routerLink: 'sw.product.detail',
                },
                {
                    property: 'active',
                    label: this.$tc('sw-sales-channel.detail.products.columnActive'),
                    allowResize: true,
                    align: 'center',
                },
                {
                    property: 'productNumber',
                    label: this.$tc('sw-sales-channel.detail.products.columnProductNumber'),
                    allowResize: true,
                },
            ];
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

            this.isLoading = true;
            return this.productRepository.search(this.productCriteria, Context.api)
                .then((products) => {
                    this.products = products;
                    this.total = products.total;

                    if (this.total > 0 && this.products.length <= 0) {
                        this.page = (this.page === 1) ? 1 : this.page - 1;
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

            this.$refs.entityListing.deleteId = null;
            return this.productVisibilityRepository.delete(deleteId, Context.api)
                .then(() => {
                    this.$refs.entityListing.closeModal();
                    this.getProducts();
                })
                .catch((error) => {
                    this.$refs.entityListing.closeModal();

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
            const deleteIds = Object.values(this.$refs.entityListing.selection).map((product) => {
                return this.getDeleteId(product);
            });

            this.isBulkLoading = true;
            return this.productVisibilityRepository.syncDeleted(deleteIds, Context.api)
                .then(() => {
                    this.isBulkLoading = false;
                    this.$refs.entityListing.showBulkDeleteModal = false;
                    this.getProducts();
                })
                .catch((error) => {
                    this.isBulkLoading = false;
                    this.$refs.entityListing.showBulkDeleteModal = false;

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

        onAddProducts(products) {
            if (products.length <= 0) {
                this.showProductsModal = false;
                return Promise.reject();
            }

            const visibilities = new EntityCollection(
                this.productVisibilityRepository.route,
                this.productVisibilityRepository.entityName,
                Context.api,
            );

            products.forEach(el => {
                if (this.products?.has(el.id)) {
                    return;
                }

                const visibility = this.productVisibilityRepository.create(Context.api);
                Object.assign(visibility, {
                    visibility: 30,
                    productId: el.id,
                    salesChannelId: this.salesChannel.id,
                    salesChannel: this.salesChannel,
                });

                visibilities.add(visibility);
            });

            this.isAssignProductLoading = true;

            return this.saveProductVisibilities(visibilities)
                .then(() => {
                    this.getProducts();
                })
                .catch((error) => {
                    this.createNotificationError({
                        message: error,
                    });
                })
                .finally(() => {
                    this.showProductsModal = false;
                    this.isAssignProductLoading = false;
                });
        },

        saveProductVisibilities(data) {
            if (data.length <= 0) {
                return Promise.resolve();
            }

            return this.productVisibilityRepository.saveAll(data, Context.api);
        },
    },
});
