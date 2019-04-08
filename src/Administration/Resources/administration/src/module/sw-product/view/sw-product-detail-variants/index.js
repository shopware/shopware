import { Component } from 'src/core/shopware';
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
            return this.product.getAssociation('configuratorSettings');
        }
    },

    methods: {
        updateVariations() {
            this.$refs.generatedVariants.getList();
        },
        updateVariantListHasContent(variantList) {
            const searchTerm = this.$route.query ? this.$route.query.term : '';
            this.variantListHasContent = variantList.length > 0 || searchTerm;
        }
    }
});
