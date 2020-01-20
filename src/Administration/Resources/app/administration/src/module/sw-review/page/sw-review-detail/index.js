import template from './sw-review-detail.html.twig';
import './sw-review-detail.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { warn } = Shopware.Utils.debug;

Component.register('sw-review-detail', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification'),
        Mixin.getByName('salutation')
    ],

    data() {
        return {
            isLoading: null,
            reviewId: null,
            review: {}
        };
    },

    created() {
        this.createdComponent();
    },

    watch: {
        '$route.params.id'() {
            this.createdComponent();
        }
    },

    computed: {
        repository() {
            return this.repositoryFactory.create('product_review');
        },
        stars() {
            if (this.review.points >= 0) {
                return this.review.points;
            }

            return 0;
        },
        missingStars() {
            if (this.review.points >= 0) {
                return 5 - this.review.points;
            }

            return 5;
        }
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.reviewId = this.$route.params.id;

                this.loadEntityData();
            }
        },
        loadEntityData() {
            this.isLoading = true;
            const criteria = new Criteria();
            criteria.addAssociation('customer');
            criteria.addAssociation('salesChannel');
            criteria.addAssociation('product');

            const context = { ...Shopware.Context.api, inheritance: true };

            this.repository.get(this.reviewId, context, criteria).then((review) => {
                this.review = review;
                this.isLoading = false;
            });
        },

        onSave() {
            const reviewName = this.review.title;
            const titleSaveSuccess = this.$tc('sw-review.detail.titleSaveSuccess');
            const messageSaveSuccess = this.$tc('sw-review.detail.messageSaveSuccess', 0, { name: reviewName });
            const titleSaveError = this.$tc('global.default.error');
            const messageSaveError = this.$tc(
                'global.notification.notificationSaveErrorMessage', 0, { entityName: reviewName }
            );
            this.repository.save(this.review, Shopware.Context.api).then(() => {
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });
            }).catch((exception) => {
                this.createNotificationError({
                    title: titleSaveError,
                    message: messageSaveError
                });
                warn(this._name, exception.message, exception.response);
                throw exception;
            });
        }
    }
});
