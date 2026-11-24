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
            if (this.isProductComparison || this.isAgenticCommerceSalesChannel) {
                this.onGenerateProductExportKey(false);
            }
            if (this.isAgenticCommerceSalesChannel) {
                this._prefillAgenticCommerceDefaults();
            }
        },

        /** @deprecated tag:v6.8.0 - Will be removed */
        prefillAgenticCommerceDefaults() {
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                'sw-sales-channel-create-base.prefillAgenticCommerceDefaults() is deprecated. Will be removed.',
            );

            this._prefillAgenticCommerceDefaults();
        },

        /**
         * @private
         * @deprecated tag:v6.8.0 - Will be removed
         *
         * Unguarded implementation of prefillAgenticCommerceDefaults(),
         * so core's own uses do not report the deprecation.
         */
        _prefillAgenticCommerceDefaults() {
            this.productExport.fileName = `agentic-commerce-${utils.createId()}.jsonl`;
        },
    },
};
