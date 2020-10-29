import template from './sw-review-detail.html.twig';
import './sw-review-detail.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-review-detail', {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [
        'placeholder',
        'notification',
        'salutation'
    ],

    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.acl.can('review.editor');
            },
            method: 'onSave'
        },
        ESCAPE: 'onCancel'
    },

    data() {
        return {
            isLoading: null,
            isSaveSuccessful: false,
            reviewId: null,
            review: {}
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            return this.review.title;
        },

        repository() {
            return this.repositoryFactory.create('product_review');
        },

        stars() {
            if (this.review.points >= 0) {
                return this.review.points;
            }

            return 0;
        },

        /** @deprecated tag:v6.4.0 No need to calculate when using `sw-rating-stars` component instead */
        missingStars() {
            if (this.review.points >= 0) {
                return 5 - this.review.points;
            }

            return 5;
        },

        languageCriteria() {
            const criteria = new Criteria();

            criteria.addSorting(Criteria.sort('name', 'ASC', false));

            return criteria;
        },

        tooltipSave() {
            if (!this.acl.can('review.editor')) {
                return {
                    message: this.$tc('sw-privileges.tooltip.warning'),
                    disabled: true,
                    showOnDisabledElements: true
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light'
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light'
            };
        }
    },

    watch: {
        '$route.params.id'() {
            this.createdComponent();
        }
    },

    created() {
        this.createdComponent();
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
            this.isSaveSuccessful = false;
            const messageSaveError = this.$tc(
                'global.notification.notificationSaveErrorMessageRequiredFieldsInvalid'
            );

            this.repository.save(this.review, Shopware.Context.api).then(() => {
                this.isSaveSuccessful = true;
            }).catch(() => {
                this.createNotificationError({
                    message: messageSaveError
                });
            });
        },

        onSaveFinish() {
            this.isSaveSuccessful = false;
        },

        onCancel() {
            this.$router.push({ name: 'sw.review.index' });
        }
    }
});
