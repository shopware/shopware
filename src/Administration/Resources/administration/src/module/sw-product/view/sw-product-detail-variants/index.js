import { Component } from 'src/core/shopware';
// import EntityStore from 'src/core/data/EntityStore';
// import StoreLoader from 'src/core/helper/store-loader.helper';
import template from './sw-product-detail-variants.html.twig';
import './sw-product-detail-variants.scss';

Component.register('sw-product-detail-variants', {
    template,

    data() {
        return {
            languageId: null,
            variantListHasContent: false
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
        configuratorStore() {
            return this.product.getAssociation('configurators');
        }
    },

    methods: {
        updateVariations() {
            this.$refs.generatedVariants.getList();
        },
        updateVariantListHasContent(variantList) {
            this.variantListHasContent = variantList.length > 0;
        }
    }
});
