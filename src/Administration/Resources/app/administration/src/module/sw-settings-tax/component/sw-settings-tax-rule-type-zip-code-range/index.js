import template from './sw-settings-tax-rule-type-zip-code-range.html.twig';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-settings-tax-rule-type-zip-code-range', {
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
});
