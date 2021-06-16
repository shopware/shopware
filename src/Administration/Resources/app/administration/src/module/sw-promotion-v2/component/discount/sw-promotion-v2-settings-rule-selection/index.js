import template from './sw-promotion-v2-settings-rule-selection.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

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
            return (new Criteria())
                .addSorting(Criteria.sort('name', 'ASC', false));
        },
    },
});
