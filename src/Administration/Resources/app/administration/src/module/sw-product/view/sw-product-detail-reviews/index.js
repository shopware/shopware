/*
 * @package inventory
 */

import template from './sw-product-detail-reviews.html.twig';
import './sw-product-detail-reviews.scss';

const { Component, Data, Context } = Shopware;
const { Criteria } = Data;
const { mapState, mapGetters } = Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl'],

    data() {
        return {
            showReviewDeleteModal: false,
            deleteReviewId: null,
            dataSource: [],
            page: 1,
            limit: 10,
            total: 0,
        };
    },

    computed: {
        ...mapState('swProductDetail', [
            'product',
        ]),

        ...mapGetters('swProductDetail', [
            'isLoading',
        ]),

        cardTitle() {
            return this.total ? this.$tc('sw-product.reviews.cardTitleReviews') : null;
        },

        reviewRepository() {
            return this.repositoryFactory.create('product_review');
        },

        reviewCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            criteria.addFilter(
                Criteria.equals('productId', this.product.id),
            );
            criteria.setTotalCountMode(1);

            return criteria;
        },

        reviewColumns() {
            return [
                {
                    property: 'points',
                    dataIndex: 'points',
                    label: this.$tc('sw-product.reviewForm.labelPoints'),
                },
                {
                    property: 'status',
                    dataIndex: 'status',
                    label: this.$tc('sw-product.reviewForm.labelStatus'),
                    align: 'center',
                },
                {
                    property: 'createdAt',
                    dataIndex: 'createdAt',
                    label: this.$tc('sw-product.reviewForm.labelCreatedAt'),
                },
                {
                    property: 'title',
                    dataIndex: 'title',
                    label: this.$tc('sw-product.reviewForm.labelTitle'),
                    routerLink: 'sw.review.detail',
                },
            ];
        },
    },

    watch: {
        'product.id': {
            immediate: true,
            handler(newValue) {
                if (!newValue) {
                    return;
                }

                this.getReviews();
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getReviews();
        },

        getReviews() {
            if (!this.product || !this.product.id) {
                return;
            }

            this.reviewRepository.search(this.reviewCriteria, Context.api).then((reviews) => {
                this.total = reviews.total;
                this.dataSource = reviews;

                if (this.total > 0 && this.dataSource.length <= 0) {
                    this.page = (this.page === 1) ? 1 : this.page - 1;
                    this.getReviews();
                }
            });
        },

        onStartReviewDelete(review) {
            this.deleteReviewId = review.id;
            this.onShowReviewDeleteModal();
        },

        onCancelReviewDelete() {
            this.deleteReviewId = null;
            this.onCloseReviewDeleteModal();
        },

        onShowReviewDeleteModal() {
            this.showReviewDeleteModal = true;
        },

        onCloseReviewDeleteModal() {
            this.showReviewDeleteModal = false;
        },

        onConfirmReviewDelete() {
            this.onCloseReviewDeleteModal();

            this.reviewRepository.delete(this.deleteReviewId, Context.api).then(() => {
                this.deleteReviewId = null;
                this.getReviews();
            });
        },

        onChangePage(data) {
            this.page = data.page;
            this.limit = data.limit;
            this.getReviews();
        },
    },
};
