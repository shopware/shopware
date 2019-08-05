import { Component } from 'src/core/shopware';
import { mapState, mapGetters } from 'vuex';
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
            'parentProduct',
            'customFieldSets',
            'loading'
        ]),

        ...mapGetters('swProductDetail', [
            'isLoading'
        ]),

        ...mapState('swProductDetail', {
            customFieldSetsArray: state => {
                if (!state.customFieldSets) {
                    return [];
                }
                return state.customFieldSets;
            }
        }),

        mediaFormVisible() {
            return !this.loading.product &&
                   !this.loading.parentProduct &&
                   !this.loading.customFieldSets &&
                   !this.loading.media;
        }
    },

    methods: {
        mediaRemoveInheritanceFunction(newValue) {
            // remove all items
            this.mediaRestoreInheritanceFunction();

            this.$refs.productMediaInheritance.forceInheritanceRemove = true;

            // add each item from the parentValue to the original value
            this.$nextTick(() => {
                newValue.forEach((item) => {
                    this.$root.$emit('media-added', item.mediaId);
                });
            });

            return this.product.media;
        },

        mediaRestoreInheritanceFunction() {
            this.$refs.productMediaInheritance.forceInheritanceRemove = false;
            this.product.coverId = null;

            const productMediaIds = this.product.media.map(media => media.id);

            // remove all items from value
            productMediaIds.forEach((mediaId) => {
                this.product.media.remove(mediaId);
            });

            return this.product.media;
        }
    }
});
