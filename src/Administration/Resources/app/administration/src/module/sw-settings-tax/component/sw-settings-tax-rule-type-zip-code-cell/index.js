import template from './sw-settings-tax-rule-type-zip-code-cell.html.twig';

/**
 * @package checkout
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    props: {
        taxRule: {
            type: Object,
            required: true,
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
        },
    },
};
