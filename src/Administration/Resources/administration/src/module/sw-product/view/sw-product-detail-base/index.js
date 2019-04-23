import { Component } from 'src/core/shopware';
import { mapState } from 'vuex';
import template from './sw-product-detail-base.html.twig';

Component.register('sw-product-detail-base', {
    template,

    props: {
        productId: {
            type: String,
            required: false,
            default: null
        }
    },

    computed: {
        ...mapState('swProductDetail', [
            'product',
            'customFieldSets',
            'loading'
        ]),

        ...mapState('swProductDetail', {
            customFieldSetsArray: state => {
                if (!state.customFieldSets.items) {
                    return [];
                }
                return Object.values(state.customFieldSets.items);
            }
        })
    }
});
