/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-create-base.html.twig';
import { Utils } from '../dependencies';

const utils = Utils;

/**
 * @public
 */
export default {
    template,

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.onGenerateKeys();
            if (this.isProductComparison || this.isAgenticCommerce) {
                this.onGenerateProductExportKey(false);
            }
            if (this.isAgenticCommerce) {
                this.prefillAgenticCommerceDefaults();
            }
        },

        /** @deprecated tag:v6.8.0 - Will be removed */
        prefillAgenticCommerceDefaults() {
            this.productExport.fileName = `agentic-commerce-${utils.createId()}.jsonl`;
        },
    },
};
