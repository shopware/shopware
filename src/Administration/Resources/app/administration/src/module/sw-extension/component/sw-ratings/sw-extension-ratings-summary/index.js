import template from './sw-extension-ratings-summary.html.twig';
import './sw-extension-ratings-summary.scss';

/**
 * @package checkout
 * @private
 */
export default {
    template,

    compatConfig: Shopware.compatConfig,

    props: {
        summary: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            maxRating: 5,
        };
    },

    computed: {
        maxProgressValue() {
            return this.summary.numberOfRatings === 0 ? 1 : this.summary.numberOfRatings;
        },
    },
};
