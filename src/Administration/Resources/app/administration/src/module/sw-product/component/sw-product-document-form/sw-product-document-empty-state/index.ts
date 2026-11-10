/**
 * @sw-package inventory
 */
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

    computed: {
        languageId() {
            return Shopware.Store.get('context').api.languageId;
        },

        systemLanguageId() {
            return Shopware.Store.get('context').api.systemLanguageId;
        },

        isDefaultLanguage(): boolean {
            return this.languageId === this.systemLanguageId;
        },
    },
});
