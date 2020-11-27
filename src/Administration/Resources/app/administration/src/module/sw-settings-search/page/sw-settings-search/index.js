import template from './sw-settings-search.html.twig';

const { Component } = Shopware;

Component.register('sw-settings-search', {
    template,

    data: () => {
        return {
            productSearchConfigs: {
                andLogic: true,
                minSearchLength: 2
            },
            isLoading: false
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getProductSearchConfigs();
        },

        getProductSearchConfigs() {
            // TODO: NEXT-12994 Implement new "Search" settings module with API integration.
        },

        onChangeLanguage() {
            // TODO: NEXT-12994 Implement new "Search" settings module with API integration.
        },

        onSaveSearchSettings() {
            // TODO: NEXT-12994 Implement new "Search" settings module with API integration.
        }
    }
});
