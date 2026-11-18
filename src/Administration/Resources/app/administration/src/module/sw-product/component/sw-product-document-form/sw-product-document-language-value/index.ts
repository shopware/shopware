/**
 * @sw-package inventory
 */
import template from './sw-product-document-language-value.html.twig';
import './sw-product-document-language-value.scss';

/** @private */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        value: {
            type: String,
            required: true,
        },
        isTranslated: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        isDefaultLanguage(): boolean {
            return Shopware.Store.get('context').isSystemDefaultLanguage;
        },

        languageCode(): string {
            const { languageId } = Shopware.Store.get('context').api;
            const language = Shopware.Store.get('context').api.language as Entity<'language'> | null;
            return language?.id === languageId ? language.locale?.code.split('-')[0].toUpperCase() || '' : '';
        },
    },
});
