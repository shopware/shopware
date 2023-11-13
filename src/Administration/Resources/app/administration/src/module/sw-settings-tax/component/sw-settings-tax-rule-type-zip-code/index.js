import template from './sw-settings-tax-rule-type-zip-code.html.twig';

/**
 * @package checkout
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

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
            if (!this.taxRule.data) {
                this.taxRule.data = {
                    zipCode: '',
                };
            }
        },
    },
};
