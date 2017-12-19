import template from './sw-sidebar-item.html.twig';

Shopware.Component.register('sw-sidebar-item', {
    props: ['entry'],
    template,
    methods: {
        getIconName(name) {
            return `icon-${name}`;
        }
    }
});
