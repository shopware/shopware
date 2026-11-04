/**
 * @sw-package inventory
 */
import template from './sw-product-document-add-button.html.twig';
import './sw-product-document-add-button.scss';

/** @private */
export default Shopware.Component.wrapComponentConfig({
    template,

    emits: ['add-documents', 'change-language'],

    props: {
        disabled: {
            type: Boolean,
            default: false,
        },
    },

    data() {
        return {
            isHintOpen: false,
            focusRequested: false,
            hideTimer: null as ReturnType<typeof setTimeout> | null,
        };
    },

    computed: {
        isDefaultLanguage: () => Shopware.Context.api.languageId === Shopware.Context.api.systemLanguageId,
    },

    beforeUnmount() {
        this.closeHint();
    },

    methods: {
        async focusHint(event: KeyboardEvent): Promise<void> {
            event.preventDefault();
            this.focusRequested = true;
            this.openHint();
            await this.$nextTick();
            this.focusLanguageLink();
        },

        focusLanguageLink(): void {
            if (!this.focusRequested) {
                return;
            }
            const hint = this.$refs.languageHint as { $el: HTMLElement } | undefined;
            const link = hint?.$el.querySelector('button');
            if (link) {
                link.focus();
                this.focusRequested = false;
            }
        },

        openHint(): void {
            if (this.isDefaultLanguage) {
                return;
            }
            if (this.hideTimer) {
                clearTimeout(this.hideTimer);
            }
            this.isHintOpen = true;
        },

        scheduleClose(): void {
            if (this.hideTimer) {
                clearTimeout(this.hideTimer);
            }
            this.hideTimer = setTimeout(() => this.closeHint(), 150);
        },

        closeHint(): void {
            if (this.hideTimer) {
                clearTimeout(this.hideTimer);
            }
            this.hideTimer = null;
            this.focusRequested = false;
            this.isHintOpen = false;
        },
    },
});
