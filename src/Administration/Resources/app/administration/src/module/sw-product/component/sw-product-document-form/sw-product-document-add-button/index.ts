/**
 * @sw-package inventory
 */
import { useId } from 'vue';
import template from './sw-product-document-add-button.html.twig';
import './sw-product-document-add-button.scss';

/** @private */
export default Shopware.Component.wrapComponentConfig({
    template,

    emits: ['documents-add', 'language-change'],

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
        return { hintId: useId() };
    },

    data() {
        return {
            isHintOpen: false,
            focusRequested: false,
            hideTimer: null as ReturnType<typeof setTimeout> | null,
        };
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

        hintLabel(): string {
            return this.$t(
                this.isInherited ? 'sw-product.documentForm.unlinkToAdd' : 'sw-product.documentForm.switchContentLanguage',
            );
        },
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
            this.focusHintContent();
        },

        focusHintContent(): void {
            if (!this.focusRequested) {
                return;
            }
            const hint = this.$refs.languageHint as { $el: HTMLElement } | undefined;
            const target = this.isInherited ? hint?.$el : hint?.$el.querySelector('button');
            if (target) {
                target.focus();
                this.focusRequested = false;
            }
        },

        openHint(): void {
            if (this.isDefaultLanguage && !this.isInherited) {
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
            this.hideTimer = setTimeout(() => {
                const trigger = this.$refs.trigger as HTMLElement | undefined;
                const hint = this.$refs.languageHint as { $el: HTMLElement } | undefined;
                const isInteracting = [trigger, hint?.$el].some(
                    (element) => element?.matches(':hover') || element?.contains(document.activeElement),
                );
                if (!isInteracting) {
                    this.closeHint();
                }
            }, 150);
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
