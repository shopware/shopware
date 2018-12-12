import { Component, State } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-product-category-form.html.twig';

Component.register('sw-product-category-form', {
    template,

    props: {
        product: {
            type: Object,
            required: true,
            default: {}
        }
    },

    data() {
        return {
            catalogs: []
        };
    },

    computed: {
        categoryStore() {
            return State.getStore('category');
        },

        catalogStore() {
            return State.getStore('catalog');
        },

        categoryAssociationStore() {
            return this.product.getAssociation('categories');
        },

        criteria() {
            if (!this.product.catalogId) {
                return null;
            }

            return CriteriaFactory.equals('catalogId', this.product.catalogId);
        },

        hasCriteria() {
            return this.criteria !== null;
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.catalogStore.getList({
                page: 1,
                limit: 25
            }).then(response => {
                this.catalogs = response.items;
            });
        },

        onChangeCatalog() {
            this.product.categories.forEach((category) => {
                this.$refs.multiSelectCategories.dismissSelection(category.id);
            });
        }
    }
});
