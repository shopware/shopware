import type { buttonProps } from '@shopware-ag/meteor-admin-sdk/es/ui/modal';
import type { ModalItemEntry } from 'src/app/store/modals.store';
import DOMPurify from 'dompurify';
import template from './sw-modals-renderer.html.twig';

/**
 * @sw-package framework
 *
 * @private
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Shopware.Component.wrapComponentConfig({
    template,

    computed: {
        modals(): ModalItemEntry[] {
            return Shopware.Store.get('modals').modals;
        },
    },

    methods: {
        closeModal(locationId: string) {
            Shopware.Store.get('modals').closeModal(locationId);
        },

        buttonProps(button: buttonProps) {
            // eslint-disable-next-line max-len
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
            const isGhost = [
                'ghost',
                'ghost-danger',
            ].includes(originalVariant);

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
                    'i',
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
