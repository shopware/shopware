/**
 * @sw-package inventory
 */
import escape from 'lodash-es/escape';
import useDocumentDefaultLanguage from '../use-document-default-language';
import './sw-product-document-add-button.scss';
import template from './sw-product-document-add-button.html.twig';

/** @private */
export default Shopware.Component.wrapComponentConfig({
    template,

    emits: ['documents-add'],

    props: {
        isInherited: {
            type: Boolean,
            required: false,
            default: false,
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    setup() {
        return useDocumentDefaultLanguage();
    },

    computed: {
        isSystemDefaultLanguage(): boolean {
            return Shopware.Store.get('context').isSystemDefaultLanguage;
        },
        tooltipContent(): string {
            if (this.isInherited) {
                return this.$t('sw-product.documentForm.unlinkToAdd');
            }

            const language = escape(this.defaultLanguageName || this.$t('sw-product.documentForm.defaultLanguage'));
            return this.$t('sw-product.documentForm.emptyDescriptionOtherLanguage', {
                language: `<a href="#" class="sw-product-document-add-button__language-link">'${language}'</a>`,
            });
        },
    },

    methods: {
        onTooltipClick(event: MouseEvent): void {
            if (
                !(event.target instanceof Element) ||
                !event.target.closest('.sw-product-document-add-button__language-link')
            ) {
                return;
            }

            event.preventDefault();
            Shopware.Utils.EventBus.emit('on-change-language-clicked', Shopware.Store.get('context').api.systemLanguageId);
        },
    },
});
