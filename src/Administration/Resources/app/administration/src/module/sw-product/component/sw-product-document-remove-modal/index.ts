/**
 * @sw-package inventory
 */
import template from './sw-product-document-remove-modal.html.twig';

/** @private */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        documentTitle: {
            type: String,
            required: true,
        },
        canEdit: {
            type: Boolean,
            default: false,
        },
        isRemovingDocument: {
            type: Boolean,
            default: false,
        },
    },

    emits: ['close', 'remove'],
});
