/**
 * @sw-package inventory
 */
import useDocumentDefaultLanguage from '../use-document-default-language';
import template from './sw-product-document-empty-state.html.twig';
import './sw-product-document-empty-state.scss';

/** @private */
export default Shopware.Component.wrapComponentConfig({
    template,

    emits: ['documents-add'],

    props: {
        canAddDocuments: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    setup() {
        return useDocumentDefaultLanguage();
    },

    computed: {
        description(): string | undefined {
            return this.isDefaultLanguage ? this.$t('sw-product.documentForm.emptyDescription') : undefined;
        },
    },
});
