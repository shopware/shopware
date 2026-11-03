import template from './sw-order-nested-line-items-row.html.twig';
import './sw-order-nested-line-items-row.scss';
import { getCurrencyFilter } from '../dependencies';

/**
 * @sw-package checkout
 *
 * @private
 */
export default {
    template,

    props: {
        lineItem: {
            type: Object,
            required: true,
        },

        currency: {
            type: Object,
            required: true,
        },

        renderParent: {
            type: Boolean,
            required: false,
            default() {
                return false;
            },
        },
    },

    computed: {
        currencyFilter() {
            return getCurrencyFilter();
        },
    },

    methods: {
        getNestingClasses(nestingLevel) {
            return [`nesting-level-${nestingLevel}`];
        },
    },
};
