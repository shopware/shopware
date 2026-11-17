import { onKeyStroke, useEventListener } from '@vueuse/core';
import { nextTick, ref } from 'vue';
import type { ComponentPublicInstance } from 'vue';
import template from './sw-experience-studio-frame-menu.html.twig';
import './sw-experience-studio-frame-menu.scss';

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        label: {
            type: String,
            required: true,
        },
    },

    emits: ['open-another'],

    setup(_props, { emit }) {
        const isOpen = ref(false);
        const trigger = ref<ComponentPublicInstance | null>(null);
        const panel = ref<HTMLElement | null>(null);
        const panelId = `experience-studio-frame-menu-${Shopware.Utils.createId()}`;
        const popoverClass = `${panelId}-entity-results`;

        function close(restoreFocus = false): void {
            if (!isOpen.value) {
                return;
            }
            isOpen.value = false;
            if (restoreFocus) {
                const button = trigger.value?.$el as HTMLButtonElement | undefined;
                button?.focus();
            }
        }

        // Dismiss before canvas drag and zoom gestures; keep clicks for keyboard activation.
        useEventListener(
            window,
            ['pointerdown', 'click', 'wheel'],
            (event) => {
                if (!isOpen.value) {
                    return;
                }

                const button = trigger.value?.$el as HTMLButtonElement | undefined;
                const isInside = event.composedPath().some(
                    (target) =>
                        target === panel.value ||
                        target === button ||
                        // Entity search results are teleported outside the flyout.
                        (target instanceof Element && target.classList.contains(popoverClass)),
                );
                if (!isInside) {
                    close();
                }
            },
            { capture: true, passive: true },
        );
        onKeyStroke(
            'Escape',
            (event) => {
                event.stopPropagation();
                event.preventDefault();
                close(true);
            },
            { target: panel },
        );

        async function toggle(): Promise<void> {
            if (isOpen.value) {
                close();
                return;
            }
            isOpen.value = true;
            await nextTick();
            panel.value?.focus();
        }

        function openAnother(): void {
            close(true);
            emit('open-another');
        }

        return { isOpen, trigger, panel, panelId, popoverClass, toggle, close, openAnother };
    },
});
