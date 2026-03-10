/*
 * @sw-package inventory
 */

import template from './sw-product-detail-seo.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'feature',
        'acl',
        'repositoryFactory',
    ],

    data() {
        return {
            currentSalesChannelId: undefined,
        };
    },

    computed: {
        product() {
            return Shopware.Store.get('swProductDetail').product;
        },

        parentProduct() {
            return Shopware.Store.get('swProductDetail').parentProduct;
        },

        isLoading() {
            return Shopware.Store.get('swProductDetail').isLoading;
        },

        categories() {
            if (this.product.categories.length > 0) {
                return this.product.categories;
            }

            return this.parentProduct.categories ?? [];
        },

        mainCategoryRepository() {
            return this.repositoryFactory.create('main_category');
        },

        parentMainCategory() {
            if (this.parentProduct.mainCategories && this.currentSalesChannelId) {
                return this.parentProduct.mainCategories.find((category) => {
                    return category.salesChannelId === this.currentSalesChannelId;
                });
            }

            return null;
        },

        productMainCategory: {
            get() {
                return this.product.mainCategories.find((category) => {
                    return category.salesChannelId === this.currentSalesChannelId;
                });
            },
            set(newMainCategory) {
                if (!newMainCategory) {
                    this.product.mainCategories = this.product.mainCategories.filter((category) => {
                        return category.salesChannelId !== this.currentSalesChannelId;
                    });
                    return;
                }

                const newEntity = this.mainCategoryRepository.create();
                newEntity.productId = this.product.id;
                newEntity.categoryId = newMainCategory.categoryId;
                newEntity.salesChannelId = newMainCategory.salesChannelId;

                if (newMainCategory.category) {
                    newEntity.category = newMainCategory.category;
                }

                this.onRemoveMainCategory(newMainCategory);
                this.onAddMainCategory(newEntity);
            },
        },
    },

    methods: {
        onAddMainCategory(mainCategory) {
            if (this.product.mainCategories) {
                this.product.mainCategories.push(mainCategory);
            }
        },

        onRemoveMainCategory(mainCategory) {
            if (!this.product.mainCategories) {
                return;
            }

            this.product.mainCategories = this.product.mainCategories.filter((item) => {
                return item.salesChannelId !== mainCategory.salesChannelId;
            });
        },

        onChangeSalesChannel(currentSalesChannelId) {
            this.currentSalesChannelId = currentSalesChannelId;
        },
    },
};
