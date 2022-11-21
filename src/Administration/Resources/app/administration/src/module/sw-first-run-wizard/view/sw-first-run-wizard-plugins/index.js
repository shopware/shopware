import template from './sw-first-run-wizard-plugins.html.twig';
import './sw-first-run-wizard-plugins.scss';

/**
 * @package merchant-services
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['recommendationsService'],

    data() {
        return {
            plugins: [],
            regions: [],
            categories: [],
            selectedRegion: null,
            selectedCategory: null,
            isLoading: false,
        };
    },

    computed: {
        categoryLead() {
            return this.plugins.filter(plugin => {
                return plugin.isCategoryLead;
            });
        },

        notCategoryLead() {
            return this.plugins.filter(plugin => {
                return !plugin.isCategoryLead;
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
        },

    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.updateButtons();
            this.setTitle();
            this.getRecommendationRegions();
        },

        setTitle() {
            this.$emit('frw-set-title', this.$tc('sw-first-run-wizard.recommendedPlugins.modalTitle'));
        },

        updateButtons() {
            const buttonConfig = [
                {
                    key: 'back',
                    label: this.$tc('sw-first-run-wizard.general.buttonBack'),
                    position: 'left',
                    variant: null,
                    action: 'sw.first.run.wizard.index.paypal.info',
                    disabled: false,
                },
                {
                    key: 'next',
                    label: this.$tc('sw-first-run-wizard.general.buttonNext'),
                    position: 'right',
                    variant: 'primary',
                    action: 'sw.first.run.wizard.index.shopware.account',
                    disabled: false,
                },
            ];

            this.$emit('buttons-update', buttonConfig);
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
            const region = this.selectedRegion.name;
            const category = this.selectedCategory.name;

            this.isLoading = true;

            this.recommendationsService.getRecommendations({
                region,
                category,
            }).then((response) => {
                this.plugins = response.items;
            }).finally(() => {
                this.isLoading = false;
            });
        },

        getRecommendationRegions() {
            this.isLoading = true;

            this.recommendationsService.getRecommendationRegions().then((response) => {
                this.regions = response.items;
            }).finally(() => {
                this.isLoading = false;
                this.$emit('loading-finished');
            });
        },

        reloadRecommendations() {
            this.getRecommendations();
        },
    },
};
