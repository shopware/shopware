/**
 * @sw-package inventory
 */

import template from './sw-settings-seo.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    data() {
        return {
            isLoading: false,
            salesChannelIsHeadless: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    methods: {
        onClickSave() {
            this.$refs.seoUrlTemplateCard.onClickSave();

            // The system config (e.g. canonical redirect) is hidden and not rendered for headless sales channels.
            if (this.$refs.systemConfig) {
                this.$refs.systemConfig.saveAll();
            }
        },

        onLoadingChanged(loading) {
            this.isLoading = loading;
        },

        onSalesChannelChanged(salesChannelIsHeadless) {
            this.salesChannelIsHeadless = salesChannelIsHeadless;
        },
    },
};
