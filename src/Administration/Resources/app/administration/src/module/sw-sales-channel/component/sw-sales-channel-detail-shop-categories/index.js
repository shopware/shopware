import template from './sw-sales-channel-detail-shop-categories.html.twig';
import './sw-sales-channel-detail-shop-categories.scss';

const { Component } = Shopware;

Component.register('sw-sales-channel-detail-shop-categories', {
    template,

    props: {
        isLoadingCategories: {
            type: Boolean,
            required: true,
            default: false
        },

        categories: {
            type: Array,
            required: true,
            default() {
                return [];
            }
        },

        categoryId: {
            type: String,
            required: true,
            default: ''
        },

        onChangeRoute: {
            type: Function,
            required: true,
            default: null
        }
    },

    data() {
        return {
            routeParamsActiveElementId: 'categoryId'
        };
    },

    methods: {
        onGetTreeItems(parentId) {
            this.$emit('get-tree-items', parentId);
        }
    }
});
