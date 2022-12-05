/**
 * @package system-settings
 */
import template from './sw-bulk-edit-product-media-form.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    data() {
        return {
            columnCount: 4,
            showCoverLabel: false,
        };
    },
};
