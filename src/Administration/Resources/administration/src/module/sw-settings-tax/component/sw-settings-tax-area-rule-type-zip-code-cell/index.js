import template from './sw-settings-tax-area-rule-type-zip-code-cell.html.twig';

const { Component } = Shopware;

Component.register('sw-settings-tax-area-rule-type-zip-code-cell', {
    template,

    props: {
        taxAreaRule: {
            type: Object,
            required: true
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
        }
    }
});
