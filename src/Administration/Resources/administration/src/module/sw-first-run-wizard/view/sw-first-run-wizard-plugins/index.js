import { Component } from 'src/core/shopware';
import template from './sw-first-run-wizard-plugins.html.twig';
import './sw-first-run-wizard-plugins.scss';

Component.register('sw-first-run-wizard-plugins', {
    template,

    inject: ['recommendationsService'],

    data() {
        return {
            plugins: [],
            regions: [],
            categories: [],
            selectedRegion: null,
            selectedCategory: null
        };
    },

    computed: {
        categoryLead() {
            return this.plugins.filter((p) => {
                return p.isCategoryLead;
            });
        },

        notCategoryLead() {
            return this.plugins.filter((p) => {
                return !p.isCategoryLead;
            });
        },

        showSpacer() {
            return this.categoryLead.length > 0
                && this.notCategoryLead.length > 0;
        }

    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getRecommendationRegions();
        },

        regionVariant({ name }) {
            return this.selectedRegion && this.selectedRegion.name === name
                ? 'info'
                : 'neutral';
        },

        categoryVariant({ name }) {
            return this.selectedCategory && this.selectedCategory.name === name
                ? 'info'
                : 'neutral';
        },

        onSelectRegion(region) {
            this.selectedRegion = region;
            this.categories = region.categories;

            this.selectedCategory = null;
            this.plugins = [];
        },

        onSelectCategory(category) {
            this.selectedCategory = category;

            this.getRecommendations();
        },

        getRecommendations() {
            // ToDo: (mve) add param of current language
            const language = 'de-DE';
            const region = this.selectedRegion.name;
            const category = this.selectedCategory.name;

            this.recommendationsService.getRecommendations({
                language,
                region,
                category
            }).then((response) => {
                const { items } = response;
                this.plugins = [...items, ...items, ...items];
                // ToDo: (mve) fix me!
                // this.plugins = response.items;
            });
        },

        getRecommendationRegions() {
            // ToDo: (mve) add param of current language
            const language = 'de-DE';

            this.recommendationsService.getRecommendationRegions({
                language
            }).then((response) => {
                this.regions = response.items;
            });
        }
    }
});
