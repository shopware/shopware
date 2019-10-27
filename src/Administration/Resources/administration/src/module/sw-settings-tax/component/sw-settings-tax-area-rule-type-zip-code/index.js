import template from './sw-settings-tax-area-rule-type-zip-code.html.twig';

const { Component } = Shopware;

Component.register('sw-settings-tax-area-rule-type-zip-code', {
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
            if (!this.taxAreaRule.data) {
                this.taxAreaRule.data = {
                    zipCode: ''
                };
            }
        }
    }
});
