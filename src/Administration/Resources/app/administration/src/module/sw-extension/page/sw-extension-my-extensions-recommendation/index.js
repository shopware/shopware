import template from './sw-extension-store-recommendation.html.twig';

Shopware.Component.register('sw-extension-my-extensions-recommendation', {
    template,

    data() {
        return {
            isLoading: true,
        };
    },

    methods: {
        finishLoading() {
            this.isLoading = false;
        },
    },
});
