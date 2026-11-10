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
        languageId() {
            return Shopware.Store.get('context').api.languageId;
        },

        systemLanguageId() {
            return Shopware.Store.get('context').api.systemLanguageId;
        },

        isDefaultLanguage(): boolean {
            return this.languageId === this.systemLanguageId;
        },

        languageCode(): string {
            const language = Shopware.Store.get('swProductDetail').documentLanguage;
            if (language?.id !== this.languageId) {
                return '';
            }

            return language?.locale?.code.split('-')[0].toUpperCase() || '';
        },
    },
});
