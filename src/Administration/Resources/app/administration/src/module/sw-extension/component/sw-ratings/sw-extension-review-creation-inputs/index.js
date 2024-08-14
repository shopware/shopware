import template from './sw-extension-review-creation-inputs.html.twig';
import './sw-extension-review-creation-inputs.scss';

/**
 * @package checkout
 * @private
 */
export default {
    template,

    compatConfig: Shopware.compatConfig,

    emits: ['changed'],

    props: {
        errors: {
            type: Object,
            required: false,
            default: () => ({
                headlineError: null,
                ratingError: null,
            }),
        },
    },

    data() {
        return {
            headline: null,
            rating: null,
            text: null,
        };
    },

    watch: {
        headline(headline) {
            this.$emit('changed', 'headline', headline);
        },

        rating(rating) {
            this.$emit('changed', 'rating', rating);
        },

        text(text) {
            this.$emit('changed', 'text', text);
        },
    },
};
