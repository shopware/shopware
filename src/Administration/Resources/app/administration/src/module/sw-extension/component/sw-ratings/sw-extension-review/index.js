import template from './sw-extension-review.html.twig';
import './sw-extension-review.scss';

const { date } = Shopware.Utils.format;

/**
 * @package merchant-services
 * @private
 */
export default {
    template,

    props: {
        review: {
            type: Object,
            required: true,
        },

        producerName: {
            type: String,
            required: true,
        },
    },

    computed: {
        lastChangeDate() {
            return this.review.lastChangeDate !== null ? date(this.review.lastChangeDate) : null;
        },

        reviewHasReplies() {
            return this.review.replies && this.review.replies.length > 0;
        },
    },
};
