import { mapGetters, mapState } from 'vuex';
import template from './sw-product-detail-base.html.twig';

const { Component } = Shopware;

Component.register('sw-product-detail-base', {
    template,
    inject: ['repositoryFactory', 'context'],

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
        },

        reviewColumns() {
            return [{
                property: 'points',
                dataIndex: 'points',
                label: this.$tc('sw-product.reviewForm.labelPoints')
            }, {
                property: 'status',
                dataIndex: 'status',
                label: this.$tc('sw-product.reviewForm.labelStatus'),
                align: 'center'
            }, {
                property: 'createdAt',
                dataIndex: 'createdAt',
                label: this.$tc('sw-product.reviewForm.labelCreatedAt')
            }, {
                property: 'content',
                dataIndex: 'content',
                label: this.$tc('sw-product.reviewForm.labelContent')
            }];
        },

        reviewItemData() {
            if (!this.product.productReviews) {
                return null;
            }
            this.product.productReviews.forEach((review) => {
                review.additional = true;
            });
            return this.product.productReviews;
        },

        productMediaRepository() {
            return this.repositoryFactory.create(this.product.media.entity);
        }
    },

    methods: {
        mediaRemoveInheritanceFunction(newValue) {
            newValue.forEach(({ id, mediaId, position }) => {
                const media = this.productMediaRepository.create(this.context);
                Object.assign(media, { mediaId, position, productId: this.product.id });
                if (this.parentProduct.coverId === id) {
                    this.product.coverId = media.id;
                }

                this.product.media.push(media);
            });

            this.$refs.productMediaInheritance.forceInheritanceRemove = true;

            return this.product.media;
        },

        mediaRestoreInheritanceFunction() {
            this.$refs.productMediaInheritance.forceInheritanceRemove = false;
            this.product.coverId = null;

            this.product.media.getIds().forEach((mediaId) => {
                this.product.media.remove(mediaId);
            });

            return this.product.media;
        }
    }
});
