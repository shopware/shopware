/**
 * @package sales-channel
 */

import template from './sw-seo-main-category.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    props: {
        currentSalesChannelId: {
            type: String,
            required: false,
            default: null,
        },
        categories: {
            type: Array,
            required: true,
        },
        mainCategories: {
            type: Array,
            required: true,
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
        allowEdit: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
        overwriteLabel: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            mainCategoryForSalesChannel: null,
        };
    },

    computed: {
        mainCategoryRepository() {
            return this.repositoryFactory.create('main_category');
        },

        isHeadlessSalesChannel() {
            if (Shopware.State.get('swSeoUrl').salesChannelCollection === null) {
                return true;
            }

            const salesChannel = Shopware.State.get('swSeoUrl').salesChannelCollection.find((entry) => {
                return entry.id === this.currentSalesChannelId;
            });

            // from Defaults.php
            return this.currentSalesChannelId !== null && salesChannel.typeId === 'f183ee5650cf4bdb8a774337575067a6';
        },

        selectedCategory() {
            return this.mainCategoryForSalesChannel !== null ? this.mainCategoryForSalesChannel.categoryId : null;
        },
    },

    watch: {
        currentSalesChannelId() {
            this.refreshMainCategoryForSalesChannel();
        },
        mainCategories() {
            this.refreshMainCategoryForSalesChannel();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.refreshMainCategoryForSalesChannel();
        },
        onMainCategorySelected(categoryId) {
            if (categoryId === null) {
                return;
            }

            const selectedCategory = this.categories.find((value) => {
                return value.id === categoryId;
            });

            if (this.mainCategoryForSalesChannel !== null) {
                this.mainCategoryForSalesChannel.category = selectedCategory;
                this.mainCategoryForSalesChannel.categoryId = selectedCategory.id;
                return;
            }

            const mainCategory = this.mainCategoryRepository.create();
            mainCategory.salesChannelId = this.currentSalesChannelId;
            mainCategory.category = selectedCategory;
            mainCategory.categoryId = selectedCategory.id;
            this.$emit('main-category-add', mainCategory);
            this.refreshMainCategoryForSalesChannel();
        },
        refreshMainCategoryForSalesChannel() {
            const mainCategory = this.mainCategories.find((category) => {
                return category.salesChannelId === this.currentSalesChannelId;
            });

            if (mainCategory === undefined) {
                this.mainCategoryForSalesChannel = null;
                return;
            }

            this.mainCategoryForSalesChannel = mainCategory;
        },
    },
};
