import template from './sw-settings-tax-rule-type-zip-code-cell.html.twig';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-settings-tax-rule-type-zip-code-cell', {
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
        },
    },
});
