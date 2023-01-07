import template from './sw-review-detail.html.twig';
import './sw-review-detail.scss';

const { Criteria } = Shopware.Data;

/**
 * @content
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl', 'customFieldDataProviderService'],

    mixins: [
        'placeholder',
        'notification',
        'salutation',
    ],

    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.acl.can('review.editor');
            },
            method: 'onSave',
        },
        ESCAPE: 'onCancel',
    },

    data() {
        return {
            isLoading: null,
            isSaveSuccessful: false,
            reviewId: null,
            review: {},
            customFieldSets: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
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

        languageCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addSorting(Criteria.sort('name', 'ASC', false));

            return criteria;
        },

        tooltipSave() {
            if (!this.acl.can('review.editor')) {
                return {
                    message: this.$tc('sw-privileges.tooltip.warning'),
                    disabled: true,
                    showOnDisabledElements: true,
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light',
            };
        },

        showCustomFields() {
            return this.review && this.customFieldSets && this.customFieldSets.length > 0;
        },
    },

    watch: {
        '$route.params.id'() {
            this.createdComponent();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            Shopware.ExtensionAPI.publishData({
                id: 'sw-review-detail__review',
                path: 'review',
                scope: this,
            });
            if (this.$route.params.id) {
                this.reviewId = this.$route.params.id;

                this.loadEntityData();
                this.loadCustomFieldSets();
            }
        },

        loadEntityData() {
            this.isLoading = true;
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('customer');
            criteria.addAssociation('salesChannel');
            criteria.addAssociation('product');

            const context = { ...Shopware.Context.api, inheritance: true };

            this.repository.get(this.reviewId, context, criteria).then((review) => {
                this.review = review;
                this.isLoading = false;
            });
        },

        loadCustomFieldSets() {
            this.customFieldDataProviderService.getCustomFieldSets('product_review').then((sets) => {
                this.customFieldSets = sets;
            });
        },

        onSave() {
            this.isSaveSuccessful = false;
            const messageSaveError = this.$tc(
                'global.notification.notificationSaveErrorMessageRequiredFieldsInvalid',
            );

            this.repository.save(this.review).then(() => {
                this.isSaveSuccessful = true;
            }).catch(() => {
                this.createNotificationError({
                    message: messageSaveError,
                });
            });
        },

        onSaveFinish() {
            this.loadEntityData();
            this.isSaveSuccessful = false;
        },

        onCancel() {
            this.$router.push({ name: 'sw.review.index' });
        },
    },
};
