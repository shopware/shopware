import template from './sw-order-customer-comment.html.twig';

/**
 * @package checkout
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    props: {
        customerComment: {
            type: String,
            required: true,
            default: '',
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },
};
