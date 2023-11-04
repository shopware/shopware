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
        ruleCriteria() {
            return (new Criteria(1, 25))
                .addSorting(Criteria.sort('name', 'ASC', false));
        },
    },
};
