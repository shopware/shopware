import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-product-detail-base.html.twig';
import './sw-product-detail-base.scss';

const { Component } = Shopware;
const { mapState, mapGetters } = Shopware.Component.getComponentHelper();

Component.register('sw-product-detail-base', {
    template,
    inject: ['repositoryFactory'],

    props: {
        productId: {
            type: String,
            required: false,
            default: null
        }
    },

    data() {
        return {
            showReviewDeleteModal: false,
            toDeleteReviewId: null,
            reviewItemData: [],
            page: 1,
            limit: 10,
            total: 0
        };
    },

    watch: {
        product() {
            this.reloadReviews();
        }
    },

    created() {
        this.createdComponent();
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

        reviewRepository() {
            return this.repositoryFactory.create('product_review');
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
                property: 'title',
                dataIndex: 'title',
                routerLink: 'sw.review.detail',
                label: this.$tc('sw-product.reviewForm.labelTitle')
            }];
        },

        productMediaRepository() {
            return this.repositoryFactory.create(this.product.media.entity);
        }
    },

    methods: {
        createdComponent() {
            if (this.product) {
                this.reloadReviews();
            }
        },

        mediaRemoveInheritanceFunction(newValue) {
            newValue.forEach(({ id, mediaId, position }) => {
                const media = this.productMediaRepository.create(Shopware.Context.api);
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
        },

        onStartReviewDelete(review) {
            this.toDeleteReviewId = review.id;
            this.onShowReviewDeleteModal();
        },

        onConfirmReviewDelete() {
            this.onCloseReviewDeleteModal();

            this.reviewRepository.delete(this.toDeleteReviewId, Shopware.Context.api).then(() => {
                this.toDeleteReviewId = null;
                this.reloadReviews();
            });
        },

        onCancelReviewDelete() {
            this.toDeleteReviewId = null;
            this.onCloseReviewDeleteModal();
        },

        onShowReviewDeleteModal() {
            this.showReviewDeleteModal = true;
        },

        onCloseReviewDeleteModal() {
            this.showReviewDeleteModal = false;
        },

        reloadReviews() {
            if (!this.product || !this.product.id) {
                return;
            }
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('productId', this.product.id));
            criteria.setPage(this.page);
            criteria.setLimit(this.limit);
            criteria.setTotalCountMode(1);

            // load all our individual codes of our promotion
            // into our local promotion object.
            this.reviewRepository.search(criteria, Shopware.Context.api).then((reviewCollection) => {
                // assign our ui data
                this.total = reviewCollection.total;
                this.reviewItemData = reviewCollection;

                // if we have no data on the current page
                // but still a total count, then this means
                // that we are on a page that has been removed due to
                // deleting some codes.
                // so just try to reduce the page and refresh again
                if (this.total > 0 && this.reviewItemData.length <= 0) {
                    // decrease, but stick with minimum of 1
                    this.page = (this.page === 1) ? 1 : this.page -= 1;
                    this.reloadReviews();
                }
            });
        },

        onChangePage(data) {
            this.page = data.page;
            this.limit = data.limit;

            this.reloadReviews();
        },

        onMainCategoryAdded(mainCategory) {
            this.product.mainCategories.push(mainCategory);
        }
    }
});
