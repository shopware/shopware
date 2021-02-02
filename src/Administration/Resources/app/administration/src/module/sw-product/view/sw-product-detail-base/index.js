import Criteria from 'src/core/data/criteria.data';
import template from './sw-product-detail-base.html.twig';
import './sw-product-detail-base.scss';

const { Component } = Shopware;
const { mapState, mapGetters } = Shopware.Component.getComponentHelper();

Component.register('sw-product-detail-base', {
    template,

    inject: ['repositoryFactory', 'acl', 'feature'],

    props: {
        productId: {
            type: String,
            required: false,
            default: null
        }
    },

    data() {
        return {
            /**
             * @deprecated tag:v6.5.0 - The variable "showReviewDeleteModal" will be removed because
             * its relevant view was moved from this component to `sw-product-detail-reviews` component.
             */
            showReviewDeleteModal: false,

            /**
             * @deprecated tag:v6.5.0 - The variable "toDeleteReviewId" will be removed because
             * its relevant view was moved from this component to `sw-product-detail-reviews` component.
             */
            toDeleteReviewId: null,

            /**
             * @deprecated tag:v6.5.0 - The variable "reviewItemData" will be removed because
             * its relevant view was moved from this component to `sw-product-detail-reviews` component.
             */
            reviewItemData: [],

            /**
             * @deprecated tag:v6.5.0 - The variable "page" will be removed because
             * its relevant view was moved from this component to `sw-product-detail-reviews` component.
             */
            page: 1,

            /**
             * @deprecated tag:v6.5.0 - The variable "limit" will be removed because
             * its relevant view was moved from this component to `sw-product-detail-reviews` component.
             */
            limit: 10,

            /**
             * @deprecated tag:v6.5.0 - The variable "total" will be removed because
             * its relevant view was moved from this component to `sw-product-detail-reviews` component.
             */
            total: 0,

            /**
             * @deprecated tag:v6.5.0 - The variable "showLayoutModal" will be removed because
             * the modal was moved from this component to `sw-product-detail-layout` component.
             */
            showLayoutModal: false
        };
    },

    watch: {
        product() {
            /**
             * @deprecated tag:v6.5.0 - The logic `onLayoutSelect` will be removed because
             * the modal was moved from this component to `sw-product-detail-layout` component.
             */
            if (this.product.cmsPageId) {
                this.onLayoutSelect(this.product.cmsPageId);
            }

            /**
             * @deprecated tag:v6.5.0 - The logic `reloadReviews` will be removed because
             * its relevant view was moved from this component to `sw-product-detail-reviews` component.
             */
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

        /**
         * @deprecated tag:v6.5.0 - The property "customFieldSetsArray" will be removed because
         * its relevant view was moved from this component to `sw-product-detail-specifications` component.
         */
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

        /**
         * @deprecated tag:v6.5.0 - The property "reviewRepository" will be removed because
         * its relevant view was moved from this component to `sw-product-detail-reviews` component.
         */
        reviewRepository() {
            return this.repositoryFactory.create('product_review');
        },

        /**
         * @deprecated tag:v6.5.0 - The property "reviewColumns" will be removed because
         * its relevant view was moved from this component to `sw-product-detail-reviews` component.
         */
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
        },

        /**
         * @deprecated tag:v6.5.0 - The property "cmsPageRepository" will be removed because
         * the modal was moved from this component to `sw-product-detail-layout` component.
         */
        cmsPageRepository() {
            return this.repositoryFactory.create('cms_page');
        },

        /**
         * @deprecated tag:v6.5.0 - The property "cmsPage" will be removed because
         * the modal was moved from this component to `sw-product-detail-layout` component.
         */
        cmsPage() {
            return Shopware.State.get('cmsPageState').currentPage;
        }
    },

    methods: {
        createdComponent() {
            /**
             * @deprecated tag:v6.5.0 - The logic `reloadReviews` will be removed because
             * its relevant view was moved from this component to `sw-product-detail-reviews` component.
             */
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

        /**
         * @deprecated tag:v6.5.0 - The method "onStartReviewDelete" will be removed because
         * its relevant view was moved from this component to `sw-product-detail-reviews` component.
         */
        onStartReviewDelete(review) {
            this.toDeleteReviewId = review.id;
            this.onShowReviewDeleteModal();
        },

        /**
         * @deprecated tag:v6.5.0 - The method "onConfirmReviewDelete" will be removed because
         * its relevant view was moved from this component to `sw-product-detail-reviews` component.
         */
        onConfirmReviewDelete() {
            this.onCloseReviewDeleteModal();

            this.reviewRepository.delete(this.toDeleteReviewId, Shopware.Context.api).then(() => {
                this.toDeleteReviewId = null;
                this.reloadReviews();
            });
        },

        /**
         * @deprecated tag:v6.5.0 - The method "onCancelReviewDelete" will be removed because
         * its relevant view was moved from this component to `sw-product-detail-reviews` component.
         */
        onCancelReviewDelete() {
            this.toDeleteReviewId = null;
            this.onCloseReviewDeleteModal();
        },

        /**
         * @deprecated tag:v6.5.0 - The method "onShowReviewDeleteModal" will be removed because
         * its relevant view was moved from this component to `sw-product-detail-reviews` component.
         */
        onShowReviewDeleteModal() {
            this.showReviewDeleteModal = true;
        },

        /**
         * @deprecated tag:v6.5.0 - The method "onCloseReviewDeleteModal" will be removed because
         * its relevant view was moved from this component to `sw-product-detail-reviews` component.
         */
        onCloseReviewDeleteModal() {
            this.showReviewDeleteModal = false;
        },

        /**
         * @deprecated tag:v6.5.0 - The method "reloadReviews" will be removed because
         * its relevant view was moved from this component to `sw-product-detail-reviews` component.
         */
        reloadReviews() {
            if (this.feature.isActive('FEATURE_NEXT_12429') || !this.product || !this.product.id) {
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

        /**
         * @deprecated tag:v6.5.0 - The method "onChangePage" will be removed because
         * its relevant view was moved from this component to `sw-product-detail-reviews` component.
         */
        onChangePage(data) {
            this.page = data.page;
            this.limit = data.limit;

            this.reloadReviews();
        },

        /**
         * @deprecated tag:v6.5.0 - The method "onMainCategoryAdded" will be removed because
         * its relevant view was moved from this component to `sw-product-detail-seo` component.
         */
        onMainCategoryAdded(mainCategory) {
            this.product.mainCategories.push(mainCategory);
        },

        /**
         * @deprecated tag:v6.5.0 - The method "openLayoutModal" will be removed because
         * the modal was moved from this component to `sw-product-detail-layout` component.
         */
        openLayoutModal() {
            this.showLayoutModal = true;
        },

        /**
         * @deprecated tag:v6.5.0 - The method "closeLayoutModal" will be removed because
         * the modal was moved from this component to `sw-product-detail-layout` component.
         */
        closeLayoutModal() {
            this.showLayoutModal = false;
        },

        /**
         * @deprecated tag:v6.5.0 - The method "onLayoutSelect" will be removed because
         * the modal was moved from this component to `sw-product-detail-layout` component.
         */
        onLayoutSelect(selectedLayout) {
            this.product.cmsPageId = selectedLayout;

            Shopware.State.commit('swProductDetail/setProduct', this.product);

            this.cmsPageRepository.get(selectedLayout, Shopware.Context.api).then((cmsPage) => {
                Shopware.State.commit('cmsPageState/setCurrentPage', cmsPage);
            });
        },

        /**
         * @deprecated tag:v6.5.0 - The method "openInPageBuilder" will be removed because
         * the modal was moved from this component to `sw-product-detail-layout` component.
         */
        openInPageBuilder() {
            if (!this.cmsPage) {
                this.$router.push({ name: 'sw.cms.create' });
            } else {
                this.$router.push({ name: 'sw.cms.detail', params: { id: this.product.cmsPageId } });
            }
        },

        /**
         * @deprecated tag:v6.5.0 - The method "onLayoutReset" will be removed because
         * the modal was moved from this component to `sw-product-detail-layout` component.
         */
        onLayoutReset() {
            this.onLayoutSelect(null);
        }
    }
});
