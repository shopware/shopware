/**
 * @sw-package inventory
 */
import { useId } from 'vue';
import useDocumentDefaultLanguage from '../use-document-default-language';
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

    setup(props) {
        return {
            hintId: useId(),
            ...useDocumentDefaultLanguage(() => !props.isInherited),
        };
    },

    data() {
        return {
            isHintRequested: false,
            isHintFocusRequested: false,
            hintTimer: null as ReturnType<typeof setTimeout> | null,
        };
    },

    computed: {
        triggerLabel(): string {
            return `${this.$t('sw-product.documentForm.buttonAdd')}. ${this.hintLabel}`;
        },

        isHintOpen(): boolean {
            return this.isHintRequested && (this.isInherited || this.defaultLanguageName !== null);
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
            this.isHintFocusRequested = true;
            this.openHint();
            await this.$nextTick();
            this.focusHintContent();
        },

        focusHintContent(): void {
            if (!this.isHintFocusRequested) {
                return;
            }
            const hint = this.$refs.languageHint as { $el: HTMLElement } | undefined;
            const target = this.isInherited ? hint?.$el : hint?.$el.querySelector('button');
            if (target) {
                target.focus();
                this.isHintFocusRequested = false;
            }
        },

        openHint(): void {
            if (this.isDefaultLanguage && !this.isInherited) {
                return;
            }
            if (this.hintTimer) {
                clearTimeout(this.hintTimer);
            }
            this.isHintRequested = true;
        },

        scheduleOpen(): void {
            if (this.hintTimer) {
                clearTimeout(this.hintTimer);
            }
            this.hintTimer = setTimeout(() => this.openHint(), 100);
        },

        scheduleClose(): void {
            if (this.hintTimer) {
                clearTimeout(this.hintTimer);
            }
            this.hintTimer = setTimeout(() => {
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
            if (this.hintTimer) {
                clearTimeout(this.hintTimer);
            }
            this.hintTimer = null;
            this.isHintFocusRequested = false;
            this.isHintRequested = false;
        },
    },
});
