/**
 * @package services-settings
 */
import template from './sw-bulk-edit-product-media-form.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    data() {
        return {
            columnCount: 4,
            showCoverLabel: false,
        };
    },
};
