import template from './sw-ratings-card.html.twig';
import './sw-ratings-card.scss';

const extensionStoreDataService = Shopware.Service('extensionStoreDataService');
const startValuePage = 1;
const startValueLimit = 4;

export default {
    name: 'sw-ratings-card',
    template,

    mixins: [
        // Shopware.Mixin.getByName('saasError'),
    ],

    props: {
        extension: {
            type: Object,
            required: true
        },
        producerName: {
            type: String,
            required: true
        },
        isInstalledAndLicensed: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            reviews: [],
            isLoading: false,
            criteriaPage: startValuePage,
            criteriaLimit: startValueLimit,
            summary: null,
            numberOfRatings: 0
        };
    },

    computed: {
        canShowMore() {
            return this.summary.numberOfRatings > this.reviews.length;
        },

        numberOfRatingsHasChanged() {
            return this.numberOfRatings !== this.summary.numberOfRatings;
        }
    },

    watch: {
        extension() {
            this.fetchReviews();
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            await this.fetchReviews();
        },

        async fetchReviews(isLoadingMore = false) {
            this.isLoading = true;

            try {
                const { reviews, summary } = await this.getReviews();
                this.summary = summary;

                if (isLoadingMore && this.numberOfRatingsHasChanged) {
                    this.criteriaPage = startValuePage;
                    this.criteriaLimit = startValueLimit;
                    this.reviews = [];
                    await this.fetchReviews();

                    return;
                }

                this.numberOfRatings = this.summary.numberOfRatings;
                this.reviews = this.reviews.concat(reviews);
            } catch (e) {
                this.showSaasErrors(e);
            } finally {
                this.isLoading = false;
            }
        },

        async loadMoreReviews() {
            this.criteriaPage += 1;
            await this.fetchReviews(true);
        },

        async getReviews() {
            return extensionStoreDataService.getReviews(
                this.criteriaPage,
                this.criteriaLimit,
                this.extension.id
            );
        }
    }
};
