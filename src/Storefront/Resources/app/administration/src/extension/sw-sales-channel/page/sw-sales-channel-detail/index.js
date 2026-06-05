import template from './sw-sales-channel-detail.html.twig';

const { Component } = Shopware;

/**
 * @sw-package discovery
 */
Component.override('sw-sales-channel-detail', {
    template,

    inject: [
        'themeService',
    ],

    computed: {
        tabs() {
            const tabs = this.$super('tabs');

            if (this.isProductExportChannel) {
                return tabs;
            }

            const route = {
                name: 'sw.sales.channel.detail.theme',
                params: { id: this.$route.params.id },
            };
            const themeTab = {
                label: this.$t('sw-sales-channel.detail.tabTheme'),
                name: route.name,
                disabled: this.isLoading,
                onClick: () => {
                    if (this.isLoading) {
                        return;
                    }

                    void this.$router.push(route);
                },
            };
            const productsIndex = tabs.findIndex((tab) => tab.name === 'sw.sales.channel.detail.products');

            tabs.splice(productsIndex >= 0 ? productsIndex + 1 : 1, 0, themeTab);

            return tabs;
        },
    },

    methods: {
        getLoadSalesChannelCriteria() {
            const criteria = this.$super('getLoadSalesChannelCriteria');

            criteria.addAssociation('themes');

            return criteria;
        },

        async onSave() {
            this.isLoading = true;
            await this.assignSalesChannelTheme();
            await this.$super('onSave');
        },

        async assignSalesChannelTheme() {
            const originThemeId = this.salesChannel.getOrigin().extensions?.themes?.[0]?.id;
            const newThemeId = this.salesChannel.extensions?.themes?.[0]?.id;

            if (originThemeId === newThemeId) {
                return;
            }

            try {
                await this.themeService.assignTheme(newThemeId, this.salesChannel.id);
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-theme-manager.general.messageSaveError')
                });
            }
        },
    },
});
