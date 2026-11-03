import template from './sw-settings-tax-rule-type-zip-code-cell.html.twig';

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
};
