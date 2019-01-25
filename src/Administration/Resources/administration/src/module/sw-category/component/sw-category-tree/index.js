import { Component, State } from 'src/core/shopware';
import template from './sw-category-tree.html.twig';
import './sw-category-tree.less';

Component.register('sw-category-tree', {
    template,

    props: {
        categories: {
            type: Array,
            required: true,
            default: []
        }
    },

    data() {
        return {
            activeCategoryId: null
        };
    },

    computed: {
        categoryStore() {
            return State.getStore('category');
        }
    },

    watch: {
        '$route.params.id'() {
            this.getActiveCategory();
        }
    },

    methods: {
        getActiveCategory() {
            this.activeCategoryId = this.$route.params.id;
        },

        onAddSubcategory() {
            // @todo
        },

        onUpdatePositions() {
            this.$emit('sw-category-on-save');
        },

        onDeleteCategory(item) {
            const category = this.categoryStore.getById(item.id);
            category.delete(true).then(() => {
                this.$emit('sw-category-on-refresh');
                if (item.id === this.activeCategoryId) {
                    this.$emit('sw-category-on-reset-details');
                }
            });
        },

        onDuplicateCategory(item) {
            this.$emit('sw-category-on-duplicate', item);
        }
    }
});
