/**
 * @sw-package inventory
 */
import template from './sw-product-document-remove-modal.html.twig';
import './sw-product-document-remove-modal.scss';

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
            required: false,
            default: false,
        },

        isRemovingDocument: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    emits: ['close', 'remove'],

    computed: {
        isDisabled(): boolean {
            return !this.canEdit || this.isRemovingDocument;
        },
    },
});
