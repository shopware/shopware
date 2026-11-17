/**
 * @sw-package inventory
 */
import template from './sw-product-document-language-hint.html.twig';

/** @private */
export default Shopware.Component.wrapComponentConfig({
    template,

    emits: ['language-change'],

    props: {
        defaultLanguageName: { type: String, required: false, default: '' },
    },

    computed: {
        defaultLanguageId() {
            return Shopware.Store.get('context').api.systemLanguageId;
        },
    },
});
