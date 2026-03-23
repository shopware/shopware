/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-create-base.html.twig';

const utils = Shopware.Utils;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.onGenerateKeys();
            if (this.isProductComparison || this.isAgenticAi) {
                this.onGenerateProductExportKey(false);
            }
            if (this.isAgenticAi) {
                this.prefillAgenticAiDefaults();
            }
        },

        prefillAgenticAiDefaults() {
            this.productExport.fileName = `agentic-ai-${utils.createId()}.jsonl`;
        },
    },
};
