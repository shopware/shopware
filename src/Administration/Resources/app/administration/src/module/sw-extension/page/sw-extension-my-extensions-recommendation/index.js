import template from './sw-extension-store-recommendation.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
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
