import template from './sw-order-nested-line-items-row.html.twig';
import './sw-order-nested-line-items-row.scss';

/**
 * @package checkout
 *
 * @private
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
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
            // eslint-disable-next-line vue/no-boolean-default
            default() {
                return false;
            },
        },
    },

    computed: {
        currencyFilter() {
            return Shopware.Filter.getByName('currency');
        },
    },

    methods: {
        getNestingClasses(nestingLevel) {
            return [
                `nesting-level-${nestingLevel}`,
            ];
        },
    },
};
