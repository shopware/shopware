import template
    from './sw-settings-tax-area-rule-type-zip-code-range.html.twig';

const { Component } = Shopware;

Component.register('sw-settings-tax-area-rule-type-zip-code-range', {
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
                    fromZipCode: '',
                    toZipCode: ''
                };
            }
        }
    }
});
