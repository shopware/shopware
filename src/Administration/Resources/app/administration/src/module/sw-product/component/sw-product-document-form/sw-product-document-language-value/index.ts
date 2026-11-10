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
            default: false,
        },
    },

    computed: {
        isDefaultLanguage: () => Shopware.Context.api.languageId === Shopware.Context.api.systemLanguageId,

        languageCode(): string {
            const language = Shopware.Store.get('swProductDetail').documentLanguage;
            if (language?.id !== Shopware.Context.api.languageId) {
                return '';
            }

            return language?.locale?.code.split('-')[0].toUpperCase() || '';
        },
    },
});
