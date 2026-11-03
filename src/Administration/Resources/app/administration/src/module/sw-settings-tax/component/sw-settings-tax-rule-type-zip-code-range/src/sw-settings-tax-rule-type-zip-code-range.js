import template from './sw-settings-tax-rule-type-zip-code-range.html.twig';

/**
 * @sw-package checkout
 */

/**
 * @public
 */
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
                    fromZipCode: '',
                    toZipCode: '',
                };
            }
        },
    },
};
