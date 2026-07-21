import template from './instance-api-component.html.twig';

Shopware.Component.register('sw-instance-api', {
    template,

    data() {
        return {
            value: '',
        };
    },

    methods: {
        focusItem() {
            this.$el.querySelector('.item').focus();
        },
    },
});
