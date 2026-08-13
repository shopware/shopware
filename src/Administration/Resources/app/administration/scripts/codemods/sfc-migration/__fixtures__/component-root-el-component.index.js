import template from './component-root-el-component.html.twig';

Shopware.Component.register('sw-component-root-el', {
    template,

    methods: {
        focusItem() {
            this.$el.querySelector('.item').focus();
        },
    },
});
