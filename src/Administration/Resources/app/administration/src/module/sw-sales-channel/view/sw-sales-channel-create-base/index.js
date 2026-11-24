/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-create-base.html.twig';

const utils = Shopware.Utils;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    computed: {
        // A theme can only be assigned to a saved sales channel.
        activationNeedsTheme() {
            return !this.isProductExportChannel && !this.isHeadlessSalesChannel;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.onGenerateKeys();
            if (this.isProductComparison || this.isAgenticCommerceDeprecated) {
                this.onGenerateProductExportKey(false);
            }
            if (this.isAgenticCommerceDeprecated) {
                this.prefillAgenticCommerceDefaultsDeprecated();
            }
        },

        /**
         * @private
         * @deprecated tag:v6.8.0 - Will be removed with `prefillAgenticCommerceDefaults`
         *
         * Calls `prefillAgenticCommerceDefaults` for core with its deprecation silenced,
         * so an override of it keeps working.
         * Does nothing with the 6.8 flag, as if the member were removed.
         */
        prefillAgenticCommerceDefaultsDeprecated() {
            if (Shopware.Feature.isActive('V6_8_0_0')) {
                return;
            }

            Shopware.Feature.silence('V6_8_0_0', () => this.prefillAgenticCommerceDefaults());
        },

        /** @deprecated tag:v6.8.0 - Will be removed */
        prefillAgenticCommerceDefaults() {
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                'sw-sales-channel-create-base.prefillAgenticCommerceDefaults() is deprecated. Will be removed.',
            );

            this.productExport.fileName = `agentic-commerce-${utils.createId()}.jsonl`;
        },
    },
};
