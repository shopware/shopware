/**
 * @sw-package inventory
 */
import template from './sw-product-document-empty-state.html.twig';
import './sw-product-document-empty-state.scss';

/** @private */
export default Shopware.Component.wrapComponentConfig({
    template,

    emits: ['add-documents', 'change-language'],

    props: {
        canAddDocuments: {
            type: Boolean,
            default: false,
        },
    },

    computed: {
        isDefaultLanguage: () => Shopware.Context.api.languageId === Shopware.Context.api.systemLanguageId,
    },
});
