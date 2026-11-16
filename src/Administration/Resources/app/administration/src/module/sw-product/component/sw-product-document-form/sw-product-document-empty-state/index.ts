/**
 * @sw-package inventory
 */
import useDocumentDefaultLanguage from '../use-document-default-language';
import template from './sw-product-document-empty-state.html.twig';
import './sw-product-document-empty-state.scss';

/** @private */
export default Shopware.Component.wrapComponentConfig({
    template,

    emits: ['documents-add', 'language-change'],

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
});
