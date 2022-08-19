import template from './sw-promotion-v2-settings-rule-selection.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-promotion-v2-settings-rule-selection', {
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
});
