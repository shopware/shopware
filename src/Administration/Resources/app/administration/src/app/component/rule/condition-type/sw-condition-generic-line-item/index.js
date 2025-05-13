import template from './../sw-condition-generic/sw-condition-generic.html.twig';

const { Mixin } = Shopware;
const { getPlaceholderSnippet } = Shopware.Utils.genericRuleCondition;

/**
 * @public
 * @sw-package fundamentals@after-sales
 * @description Condition for generic line item rules. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-generic-line-item :condition="condition" :level="0"></sw-condition-generic-line-item>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,
    inheritAttrs: false,

    mixins: [
        Mixin.getByName('generic-condition'),
    ],

    methods: {
        getPlaceholder(fieldType) {
            return this.$tc(getPlaceholderSnippet(fieldType));
        },
    },
};
