/**
 * @sw-package inventory
 */
import template from './sw-product-document-language-hint.html.twig';

/** @private */
export default Shopware.Component.wrapComponentConfig({
    template,

    emits: ['close'],

    props: {
        defaultLanguageName: { type: String, required: false, default: '' },
    },

    computed: {
        languageLabel(): string {
            return this.defaultLanguageName || this.$t('sw-product.documentForm.defaultLanguage');
        },

        systemLanguageId() {
            return Shopware.Store.get('context').api.systemLanguageId;
        },
    },

    methods: {
        switchToDefaultLanguage(): void {
            Shopware.Utils.EventBus.emit('on-change-language-clicked', this.systemLanguageId);
            this.$emit('close');
        },
    },
});
