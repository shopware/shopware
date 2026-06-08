import template from './sw-settings-storefront-index.html.twig';
import './sw-settings-storefront-index.scss';

/**
 * @deprecated tag:v6.8.0 - Will be @private
 * @sw-package framework
 */
export default {
    template,

    inject: ['systemConfigApiService'],

    data() {
        return {
            isLoading: true,
            isSaveSuccessful: false,
            selectedSalesChannelId: null,
            storefrontSettings: {
                'core.storefrontSettings.iconCache': true,
                'core.storefrontSettings.asyncThemeCompilation': false,
                'core.storefrontSettings.speculationRules': false,
            },
            salesChannelStorefrontSettings: {
                'core.storefrontSettings.iconCache': null,
                'core.storefrontSettings.speculationRules': null,
            },
        };
    },

    computed: {
        isGlobalConfig() {
            return this.selectedSalesChannelId === null;
        },

        currentSalesChannelStorefrontSettings() {
            return this.isGlobalConfig ? this.storefrontSettings : this.salesChannelStorefrontSettings;
        },
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

        /**
         * @deprecated tag:v6.8.0 - Will be removed.
         */
        async loadstorefrontSettings() {
            this.isLoading = true;

            try {
                const values = await this.systemConfigApiService.getValues('core.storefrontSettings');

                this.storefrontSettings = {
                    'core.storefrontSettings.iconCache': true,
                    'core.storefrontSettings.asyncThemeCompilation': false,
                    'core.storefrontSettings.speculationRules': false,
                    ...values,
                };

                if (!this.isGlobalConfig) {
                    await this.loadSalesChannelStorefrontSettings();
                }
            } finally {
                this.isLoading = false;
            }
        },

        async loadSalesChannelStorefrontSettings() {
            const values = await this.systemConfigApiService.getValues(
                'core.storefrontSettings',
                this.selectedSalesChannelId,
            );

            this.salesChannelStorefrontSettings = {
                'core.storefrontSettings.iconCache': values['core.storefrontSettings.iconCache'] ?? null,
                'core.storefrontSettings.speculationRules': values['core.storefrontSettings.speculationRules'] ?? null,
            };
        },

        async saveStorefrontSettings() {
            await this.savestorefrontSettings();
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed.
         */
        async savestorefrontSettings() {
            this.isLoading = true;
            this.isSaveSuccessful = false;

            if (this.storefrontSettings['core.storefrontSettings.asyncThemeCompilation'] === '') {
                this.storefrontSettings['core.storefrontSettings.asyncThemeCompilation'] = false;
            }

            if (this.currentSalesChannelStorefrontSettings['core.storefrontSettings.iconCache'] === '') {
                this.currentSalesChannelStorefrontSettings['core.storefrontSettings.iconCache'] = this.isGlobalConfig ? true : null;
            }

            if (this.currentSalesChannelStorefrontSettings['core.storefrontSettings.speculationRules'] === '') {
                this.currentSalesChannelStorefrontSettings['core.storefrontSettings.speculationRules'] = this.isGlobalConfig ? false : null;
            }

            try {
                await Promise.all([
                    this.systemConfigApiService.saveValues({
                        'core.storefrontSettings.asyncThemeCompilation': this.storefrontSettings['core.storefrontSettings.asyncThemeCompilation'],
                    }),
                    this.systemConfigApiService.saveValues({
                        'core.storefrontSettings.iconCache': this.currentSalesChannelStorefrontSettings['core.storefrontSettings.iconCache'],
                        'core.storefrontSettings.speculationRules': this.currentSalesChannelStorefrontSettings['core.storefrontSettings.speculationRules'],
                    }, this.selectedSalesChannelId),
                ]);

                this.isSaveSuccessful = true;
            } finally {
                this.isLoading = false;
            }
        },

        async onSalesChannelChanged(salesChannelId) {
            this.selectedSalesChannelId = salesChannelId || null;

            if (this.isGlobalConfig) {
                return;
            }

            this.isLoading = true;

            try {
                await this.loadSalesChannelStorefrontSettings();
            } finally {
                this.isLoading = false;
            }
        },
    },
};
