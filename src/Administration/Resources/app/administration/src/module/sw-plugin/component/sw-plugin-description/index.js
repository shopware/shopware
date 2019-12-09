import template from './sw-plugin-description.html.twig';

Shopware.Component.register('sw-plugin-description', {
    template,

    props: {
        namespace: {
            type: String,
            required: true
        },

        description: {
            type: Object,
            required: true
        }
    }
});
