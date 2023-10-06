/**
 * @package buyers-experience
 */

import template from './sw-sales-channel-create-base.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.onGenerateKeys();
            if (this.isProductComparison) {
                this.onGenerateProductExportKey(false);
            }
        },
    },
};
