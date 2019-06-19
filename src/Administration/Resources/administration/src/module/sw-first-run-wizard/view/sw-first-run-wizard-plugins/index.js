import { Component } from 'src/core/shopware';
import template from './sw-first-run-wizard-plugins.html.twig';

Component.register('sw-first-run-wizard-plugins', {
    template,

    inject: ['recommendationsService'],

    data() {
        return {
            plugins: [],
            regions: [],
            categories: []
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getRecommendations();
            this.getRecommendationOptions();
        },

        getRecommendations() {
            // ToDo: (mve) add param of current language
            this.recommendationsService.getRecommendations().then((response) => {
                this.plugins = response.items;
            });
        },

        getRecommendationOptions() {
            // ToDo: (mve) add param of current language
            this.recommendationsService.getRecommendationOptions().then((response) => {
                const { regions, categories } = response;
                const categoryAll = {
                    name: 'all',
                    label: 'All', // ToDo: (mve) i18n
                    extensions: []
                };

                this.regions = regions;
                this.categories = [categoryAll, ...categories];
            });
        }
    }
});
