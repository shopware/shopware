/**
 * @sw-package checkout
 */
import template from './sw-promotion-v2-settings-rule-selection.html.twig';

const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'acl',
    ],

    props: {
        discount: {
            type: Object,
            required: true,
        },
    },

    computed: {
        /**
         * @deprecated tag:v6.8.0 - will be removed, does not offer additional filtering compared to default ruleFilter
         */
        ruleCriteria() {
            return new Criteria(1, 25).addSorting(Criteria.sort('name', 'ASC', false));
        },
    },
};
