import { Component, State } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-product-variants-generated-variants.html.twig';

Component.register('sw-product-variants-generated-variants', {
    template,

    data() {
        return {
            variantList: []
        };
    },

    props: {
        product: {
            type: Object,
            required: true,
            default: {}
        }
    },

    computed: {
        variantStore() {
            return State.getStore('product');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.updateVariants();
        },

        updateVariants() {
            const criteria = {
                page: 1,
                criteria: CriteriaFactory.equals('product.parentId', this.product.id),
                limit: 25,
                associations: { variations: {} }
            };

            this.variantStore.getList(criteria).then((res) => {
                this.variantList = res.items;
            });
        }
    }
});
