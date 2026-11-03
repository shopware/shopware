/**
 * @sw-package inventory
 */
import template from './sw-bulk-edit-product-media-form.html.twig';

/**
 * @public
 */
export default {
    template,

    data() {
        return {
            columnCount: 4,
            showCoverLabel: false,
        };
    },
};
