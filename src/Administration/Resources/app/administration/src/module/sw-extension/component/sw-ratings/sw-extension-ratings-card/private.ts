import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-extension-ratings-card',
    load: () => import('./src/sw-extension-ratings-card'),
    public: {
        props: [
            'extension',
            'producerName',
            'isInstalledAndLicensed',
        ],
        emits: [
            'update-extension',
        ],
        blocks: [
            'sw_extension_ratings_card',
            'sw_extension_ratings_card_has_reviews',
            'sw_extension_ratings_card_has_reviews_summary',
            'sw_extension_ratings_card_has_reviews_wrapper',
            'sw_extension_ratings_card_has_reviews_wrapper_review',
            'sw_extension_ratings_card_has_reviews_wrapper_more_button',
            'sw_extension_ratings_card_empty_state',
            'sw_extension_ratings_card_empty_state_content',
            'sw_extension_ratings_card_installed_and_licensed_headline',
            'sw_extension_ratings_card_footer',
            'sw_extension_ratings_card_footer_review_creation',
        ],
        computed: [
            'canShowMore',
            'numberOfRatingsHasChanged',
            'extensionStoreDataService',
            'hasReviews',
        ],
        methods: [
            'createdComponent',
            'fetchReviews',
            'loadMoreReviews',
            'getReviews',
        ],
    },
});
