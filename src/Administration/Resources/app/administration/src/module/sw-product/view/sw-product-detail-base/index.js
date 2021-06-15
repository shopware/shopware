import Criteria from 'src/core/data/criteria.data';
import template from './sw-product-detail-base.html.twig';
import './sw-product-detail-base.scss';

const { Component, Context, Utils, Mixin } = Shopware;
const { mapState, mapGetters } = Component.getComponentHelper();
const { isEmpty } = Utils.types;

Component.register('sw-product-detail-base', {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        productId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            showMediaModal: false,
            mediaDefaultFolderId: null,

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
            showLayoutModal: false,
        };
    },

    computed: {
        ...mapState('swProductDetail', [
            'product',
            'parentProduct',
            'customFieldSets',
            'loading',
        ]),

        ...mapGetters('swProductDetail', [
            'isLoading',
            'showModeSetting',
            'showProductCard',
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
            },
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
                label: this.$tc('sw-product.reviewForm.labelPoints'),
            }, {
                property: 'status',
                dataIndex: 'status',
                label: this.$tc('sw-product.reviewForm.labelStatus'),
                align: 'center',
            }, {
                property: 'createdAt',
                dataIndex: 'createdAt',
                label: this.$tc('sw-product.reviewForm.labelCreatedAt'),
            }, {
                property: 'title',
                dataIndex: 'title',
                routerLink: 'sw.review.detail',
                label: this.$tc('sw-product.reviewForm.labelTitle'),
            }];
        },

        productMediaRepository() {
            return this.repositoryFactory.create(this.product.media.entity);
        },

        mediaDefaultFolderRepository() {
            return this.repositoryFactory.create('media_default_folder');
        },

        mediaDefaultFolderCriteria() {
            const criteria = new Criteria(1, 1);

            criteria.addAssociation('folder');
            criteria.addFilter(Criteria.equals('entity', 'product'));

            return criteria;
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
        },
    },


    watch: {
        product() {
            /**
             * @deprecated tag:v6.5.0 - The logic `reloadReviews` will be removed because
             * its relevant view was moved from this component to `sw-product-detail-reviews` component.
             */
            this.reloadReviews();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getMediaDefaultFolderId().then((mediaDefaultFolderId) => {
                this.mediaDefaultFolderId = mediaDefaultFolderId;
            });

            /**
             * @deprecated tag:v6.5.0 - The logic `reloadReviews` will be removed because
             * its relevant view was moved from this component to `sw-product-detail-reviews` component.
             */
            if (this.product) {
                this.reloadReviews();
            }
        },

        getMediaDefaultFolderId() {
            return this.mediaDefaultFolderRepository.search(this.mediaDefaultFolderCriteria, Context.api)
                .then((mediaDefaultFolder) => {
                    const defaultFolder = mediaDefaultFolder.first();

                    if (defaultFolder.folder?.id) {
                        return defaultFolder.folder.id;
                    }

                    return null;
                });
        },

        mediaRemoveInheritanceFunction(newValue) {
            newValue.forEach(({ id, mediaId, position }) => {
                const media = this.productMediaRepository.create(Context.api);
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

        onOpenMediaModal() {
            this.showMediaModal = true;
        },

        onCloseMediaModal() {
            this.showMediaModal = false;
        },

        onAddMedia(media) {
            if (isEmpty(media)) {
                return;
            }

            media.forEach((item) => {
                this.addMedia(item).catch(({ fileName }) => {
                    this.createNotificationError({
                        message: this.$tc('sw-product.mediaForm.errorMediaItemDuplicated', 0, { fileName }),
                    });
                });
            });
        },

        addMedia(media) {
            if (this.isExistingMedia(media)) {
                return Promise.reject(media);
            }

            const newMedia = this.productMediaRepository.create(Context.api);
            newMedia.mediaId = media.id;
            newMedia.media = {
                url: media.url,
                id: media.id,
            };

            if (isEmpty(this.product.media)) {
                this.setMediaAsCover(newMedia);
            }

            this.product.media.add(newMedia);

            return Promise.resolve();
        },

        isExistingMedia(media) {
            return this.product.media.some(({ id, mediaId }) => {
                return id === media.id || mediaId === media.id;
            });
        },

        setMediaAsCover(media) {
            media.position = 0;
            this.product.coverId = media.id;
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

            this.reviewRepository.delete(this.toDeleteReviewId).then(() => {
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
            this.reviewRepository.search(criteria).then((reviewCollection) => {
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

            this.cmsPageRepository.get(selectedLayout).then((cmsPage) => {
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
        },
    },
});
