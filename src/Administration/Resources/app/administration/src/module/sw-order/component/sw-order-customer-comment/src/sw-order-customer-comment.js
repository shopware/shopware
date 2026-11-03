import template from './sw-order-customer-comment.html.twig';

/**
 * @sw-package checkout
 */

/**
 * @public
 */
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
