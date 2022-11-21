import template from './sw-extension-ratings-summary.html.twig';
import './sw-extension-ratings-summary.scss';

/**
 * @package merchant-services
 * @private
 */
export default {
    template,

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
