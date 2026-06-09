Shopware.Component.extend('sw-extended-button', 'sw-button', {
    data() {
        return {
            extraLabel: 'Extended',
        };
    },

    methods: {
        getLabel() {
            return this.extraLabel;
        },
    },
});
