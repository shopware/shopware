import template from './sw-extension-ratings-card.html.twig';
import './sw-extension-ratings-card.scss';

/**
 * @package merchant-services
 * @private
 */
export default {
    template,

    mixins: ['sw-extension-error'],

    props: {
        extension: {
            type: Object,
            required: true,
        },
        producerName: {
            type: String,
            required: true,
        },
        isInstalledAndLicensed: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            reviews: [],
            isLoading: false,
            criteriaPage: 1,
            criteriaLimit: 4,
            summary: null,
            numberOfRatings: 0,
        };
    },

    computed: {
        canShowMore() {
            return this.summary.numberOfRatings > this.reviews.length;
        },

        numberOfRatingsHasChanged() {
            return this.numberOfRatings !== this.summary.numberOfRatings;
        },

        extensionStoreDataService() {
            return Shopware.Service('extensionStoreDataService');
        },

        hasReviews() {
            return this.reviews.length > 0;
        },
    },

    watch: {
        extension() {
            this.fetchReviews();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.fetchReviews();
        },

        async fetchReviews(isLoadingMore = false) {
            this.isLoading = true;

            try {
                const { reviews, summary } = await this.getReviews();
                this.summary = summary;

                if (isLoadingMore && this.numberOfRatingsHasChanged) {
                    this.criteriaPage = 1;
                    this.criteriaLimit = 4;
                    this.reviews = [];
                    await this.fetchReviews();

                    return;
                }

                this.numberOfRatings = this.summary.numberOfRatings;
                this.reviews = this.reviews.concat(reviews);
            } catch (e) {
                this.showExtensionErrors(e);
            } finally {
                this.isLoading = false;
            }
        },

        async loadMoreReviews() {
            this.criteriaPage += 1;
            await this.fetchReviews(true);
        },

        async getReviews() {
            return this.extensionStoreDataService.getReviews(
                this.criteriaPage,
                this.criteriaLimit,
                this.extension.id,
            );
        },
    },
};
