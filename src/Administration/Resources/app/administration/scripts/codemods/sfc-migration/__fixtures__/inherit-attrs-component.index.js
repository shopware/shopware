import template from './inherit-attrs-component.html.twig';

Shopware.Component.register('sw-inherit-attrs', {
    template,

    inheritAttrs: false,

    data() {
        return {
            count: 0,
        };
    },

    methods: {
        increment() {
            this.count += 1;
        },
    },
});
