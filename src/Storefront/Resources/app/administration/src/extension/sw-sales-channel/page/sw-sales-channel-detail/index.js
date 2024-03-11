import template from './sw-sales-channel-detail.html.twig';

const { Component } = Shopware;

/**
 * @package buyers-experience
 */
Component.override('sw-sales-channel-detail', {
    template,

    inject: [
        'themeService',
    ],

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
                    message: this.$tc('sw-theme-manager.general.messageSaveError')
                });
            }
        },
    },
});
