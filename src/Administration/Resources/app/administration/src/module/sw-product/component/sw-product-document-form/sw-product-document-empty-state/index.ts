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
        defaultLanguageName: { type: String, required: false, default: '' },
        canAddDocuments: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        isSystemDefaultLanguage(): boolean {
            return Shopware.Store.get('context').isSystemDefaultLanguage;
        },
        description(): string | undefined {
            return this.isSystemDefaultLanguage ? this.$t('sw-product.documentForm.emptyDescription') : undefined;
        },
    },
});
