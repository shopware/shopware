import template from './named-component.html.twig';

Shopware.Component.register('sw-named-card', {
    template,

    // Repeats the registered name on purpose: the generated SFC must omit it,
    // because native setup infers the component name from the `.vue` filename.
    name: 'sw-named-card',

    data() {
        return {
            title: 'Named Card',
        };
    },
});
