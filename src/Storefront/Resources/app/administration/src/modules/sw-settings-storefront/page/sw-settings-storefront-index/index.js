import template from './sw-settings-storefront-index.html.twig';
import './sw-settings-storefront-index.scss';

/**
 * @package services-settings
 */
Shopware.Component.register('sw-settings-storefront-index', {
    template,

    inject: ['systemConfigApiService'],

    data() {
        return {
            isLoading: true,
            isSaveSuccessful: false,
            storefrontSettings: {
                'core.storefrontSettings.iconCache': true,
                'core.storefrontSettings.asyncThemeCompilation': false
            },
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            await this.loadPageContent();
        },

        async loadPageContent() {
            await this.loadstorefrontSettings();
        },

        async loadstorefrontSettings() {
            this.isLoading = true;
            this.storefrontSettings = await this.systemConfigApiService.getValues('core.storefrontSettings');

            // Default when config is empty
            if (Object.keys(this.storefrontSettings).length === 0) {
                this.storefrontSettings = {
                    'core.storefrontSettings.iconCache': true,
                };
            }

            if (Object.keys(this.storefrontSettings).length === 0) {
                this.storefrontSettings = {
                    'core.storefrontSettings.asyncThemeCompilation': false,
                };
            }

            this.isLoading = false;
        },

        async savestorefrontSettings() {
            this.isLoading = true;

            // Inputs cannot return null
            if (this.storefrontSettings['core.storefrontSettings.iconCache'] === '') {
                this.storefrontSettings['core.storefrontSettings.iconCache'] = true;
            }

            if (this.storefrontSettings['core.storefrontSettings.asyncThemeCompilation'] === '') {
                this.storefrontSettings['core.storefrontSettings.asyncThemeCompilation'] = false;
            }

            await this.systemConfigApiService.saveValues(this.storefrontSettings);
            this.isLoading = false;
        },

        async onSaveFinish() {
            await this.loadPageContent();
        },
    },
});
