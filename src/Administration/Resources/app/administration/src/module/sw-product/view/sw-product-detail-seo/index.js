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
                if (this.product.mainCategories && !newMainCategory) {
                    this.product.mainCategories = this.product.mainCategories.filter((category) => {
                        return category.salesChannelId !== this.currentSalesChannelId;
                    });

                    return;
                }

                this.product.mainCategories.push(newMainCategory);
            },
        },
    },

    methods: {
        onAddMainCategory(mainCategory) {
            if (this.product.mainCategories) {
                this.product.mainCategories.push(mainCategory);
            }
        },
        onChangeSalesChannel(currentSalesChannelId) {
            this.currentSalesChannelId = currentSalesChannelId;
        },
    },
};
