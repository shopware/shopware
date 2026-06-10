Shopware.Component.extend('sw-extended-mixin', 'sw-base', {
    mixins: [Shopware.Mixin.getByName('notification')],

    data() {
        return {
            label: 'Extended',
        };
    },
});
