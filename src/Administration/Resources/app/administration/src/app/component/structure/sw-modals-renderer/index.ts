import { MODAL_PROPS_QUERY_KEY, type buttonProps } from '@shopware-ag/meteor-admin-sdk/es/ui/modal';
import type { ModalItemEntry } from 'src/app/store/modals.store';
import DOMPurify from 'dompurify';
import template from './sw-modals-renderer.html.twig';
import './sw-modals-renderer.scss';

/**
 * @sw-package framework
 *
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    computed: {
        modals(): ModalItemEntry[] {
            return Shopware.Store.get('modals').modals;
        },
    },

    methods: {
        /**
         * Kept for overrides of this component; the template dismisses the closed modal itself via `dismissModal`.
         */
        closeModal(locationId: string) {
            Shopware.Store.get('modals').dismissModals((modal) => modal.locationId === locationId);
        },

        dismissModal(modal: ModalItemEntry) {
            Shopware.Store.get('modals').dismissModal(modal);
        },

        /**
         * Marks a location opened with props, so `ui.modal.getProps()` in the extension only asks when there is
         * something to get. The marker is added before the URL is signed.
         */
        locationSrc(modal: ModalItemEntry): string {
            if (modal.props === undefined) {
                return modal.baseUrl;
            }

            const url = new URL(modal.baseUrl, window.location.origin);
            url.searchParams.set(MODAL_PROPS_QUERY_KEY, '1');

            return url.toString();
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
