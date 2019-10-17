import template from './sw-first-run-wizard-plugins.html.twig';
import './sw-first-run-wizard-plugins.scss';

const { Component } = Shopware;

Component.register('sw-first-run-wizard-plugins', {
    template,

    inject: ['recommendationsService'],

    data() {
        return {
            plugins: [],
            regions: [],
            categories: [],
            selectedRegion: null,
            selectedCategory: null,
            isLoading: false
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
        },

        showCategoryLead() {
            return this.categoryLead.length > 0;
        },

        showNotCategoryLead() {
            return this.notCategoryLead.length > 0;
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
            const language = window.localStorage.getItem('sw-admin-locale');
            const region = this.selectedRegion.name;
            const category = this.selectedCategory.name;

            this.isLoading = true;

            this.recommendationsService.getRecommendations({
                language,
                region,
                category
            }).then((response) => {
                this.plugins = response.items;
                this.isLoading = false;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        getRecommendationRegions() {
            const language = window.localStorage.getItem('sw-admin-locale');

            this.isLoading = true;

            this.recommendationsService.getRecommendationRegions({
                language
            }).then((response) => {
                this.regions = response.items;
                this.isLoading = false;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        reloadRecommendations() {
            this.getRecommendations();
        }
    }
});
