/*
 * @sw-package inventory
 */

import template from './sw-product-detail-seo.html.twig';
import { aclService, featureService, repositoryFactoryService, useProductDetailStore } from '../dependencies';

/**
 * @public
 */
export default {
    template,

    inject: [featureService, aclService, repositoryFactoryService],

    data() {
        return {
            currentSalesChannelId: undefined,
        };
    },

    computed: {
        product() {
            return useProductDetailStore().product;
        },

        parentProduct() {
            return useProductDetailStore().parentProduct;
        },

        isLoading() {
            return useProductDetailStore().isLoading;
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
