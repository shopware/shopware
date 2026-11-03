import template from './sw-modals-renderer.html.twig';
import './sw-modals-renderer.scss';
import { type buttonProps, DOMPurify, type ModalItemEntry, useModalsStore, wrapComponentConfig } from '../dependencies';

/**
 * @sw-package framework
 *
 * @private
 */
export default wrapComponentConfig({
    template,

    computed: {
        modals(): ModalItemEntry[] {
            return useModalsStore().modals;
        },
    },

    methods: {
        closeModal(locationId: string) {
            useModalsStore().closeModal(locationId);
        },

        buttonProps(button: buttonProps) {
            type buttonVariantsWithFallback =
                | 'ghost'
                | 'primary'
                | 'secondary'
                | 'critical'
                | 'action'
                | 'ghost-danger'
                | 'danger'
                | 'contrast'
                | 'context';

            // Convert deprecated button variants to new ones
            const variantMap: Record<string, buttonVariantsWithFallback> = {
                ghost: 'secondary',
                danger: 'critical',
                'ghost-danger': 'critical',
                contrast: 'secondary',
                context: 'action',
            };

            const originalVariant = button.variant ?? 'primary';
            const mappedVariant = variantMap[originalVariant] ?? originalVariant;
            const isGhost = ['ghost', 'ghost-danger'].includes(originalVariant);

            return {
                method: button.method ?? ((): undefined => undefined),
                label: button.label ?? '',
                size: button.size ?? 'small',
                variant: mappedVariant,
                ghost: isGhost,
                square: button.square ?? false,
            };
        },

        sanitizeTextContent(textContent: string): string {
            return DOMPurify.sanitize(this.$t(textContent), {
                ALLOWED_TAGS: [
                    'a',
                    'b',
                    'strong',
                    'i',
                    'em',
                    'u',
                    's',
                    'li',
                    'ul',
                    'img',
                    'svg',
                ],
            });
        },
    },
});
