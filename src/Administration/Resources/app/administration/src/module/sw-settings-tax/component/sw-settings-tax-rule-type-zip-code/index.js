import template from './sw-settings-tax-rule-type-zip-code.html.twig';

const { Component } = Shopware;

Component.register('sw-settings-tax-rule-type-zip-code', {
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
                    zipCode: '',
                };
            }
        },
    },
});
