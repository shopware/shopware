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

    methods: {
        switchToDefaultLanguage(): void {
            Shopware.Utils.EventBus.emit('on-change-language-clicked', Shopware.Context.api.systemLanguageId);
            this.$emit('close');
        },
    },
});
