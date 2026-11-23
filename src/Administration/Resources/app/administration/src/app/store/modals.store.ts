/**
 * @sw-package framework
 */

import type { ModalCloseResult, uiModalOpen } from '@shopware-ag/meteor-admin-sdk/es/ui/modal';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type ModalItemEntry = Omit<uiModalOpen, 'responseType' | 'variant' | 'zIndex'> & {
    baseUrl: string;
    // Keep these fields local so Admin can support newer Meteor messages while
    // still compiling against older Meteor SDK versions.
    variant?: 'default' | 'small' | 'large' | 'x-large' | 'full';
    zIndex?: number;
};

const originOf = (url: string): string | null => {
    try {
        return new URL(url).origin;
    } catch {
        return null;
    }
};

/**
 * The opener lives in another iframe that may be gone by now, so the call is neither awaited nor allowed to fail.
 */
function notifyOpener(modal: ModalItemEntry, result: ModalCloseResult): void {
    if (!modal.onResult) {
        return;
    }

    try {
        void Promise.resolve(modal.onResult(result)).catch(() => {
            // Safe to ignore: the opener iframe is gone, so nobody is left to notify.
        });
    } catch {
        // Safe to ignore: the opener iframe is gone, so nobody is left to notify.
    }
}

const modalsStore = Shopware.Store.register({
    id: 'modals',

    state: () => ({
        modals: [] as ModalItemEntry[],
    }),

    actions: {
        openModal({
            locationId,
            title,
            closable,
            showHeader,
            showFooter,
            variant,
            baseUrl,
            buttons,
            textContent,
            zIndex,
            props,
            onResult,
        }: ModalItemEntry) {
            if (locationId) {
                this.dismissModals((modal) => modal.locationId === locationId);
            }

            this.modals.push({
                title,
                closable,
                showHeader,
                showFooter,
                variant,
                locationId,
                buttons: buttons ?? [],
                baseUrl,
                textContent,
                ...(zIndex !== undefined ? { zIndex } : {}),
                ...(props !== undefined ? { props } : {}),
                ...(onResult !== undefined ? { onResult } : {}),
            });
        },

        /**
         * Closes the modal from the extension side; its opener receives `data` as the result.
         */
        closeModal(locationId: string, data?: unknown): void {
            this.removeModals((modal) => modal.locationId === locationId, {
                reason: 'closed',
                ...(data !== undefined ? { data } : {}),
            });
        },

        closeLastModalWithoutLocationId(data?: unknown): void {
            const lastModalWithoutLocationId = this.modals.filter((modal) => !modal.locationId).at(-1);

            if (lastModalWithoutLocationId) {
                this.removeModals((modal) => modal === lastModalWithoutLocationId, {
                    reason: 'closed',
                    ...(data !== undefined ? { data } : {}),
                });
            }
        },

        /**
         * Closes the modal because the user closed it (close button, overlay or Escape).
         */
        dismissModal(modalToDismiss: ModalItemEntry): void {
            this.dismissModals((modal) => modal === modalToDismiss);
        },

        dismissModals(matches: (modal: ModalItemEntry) => boolean): void {
            this.removeModals(matches, { reason: 'dismissed' });
        },

        removeModals(matches: (modal: ModalItemEntry) => boolean, result: ModalCloseResult): void {
            const removed = this.modals.filter(matches);
            if (removed.length === 0) {
                return;
            }

            this.modals = this.modals.filter((modal) => !matches(modal));
            removed.forEach((modal) => notifyOpener(modal, result));
        },

        getProps(locationId: string, origin: string): Record<string, unknown> | null {
            const modal = this.modals.find((entry) => {
                return entry.locationId === locationId && originOf(entry.baseUrl) === origin;
            });

            return modal?.props ?? null;
        },
    },
});

/**
 * @private
 */
export type ModalsStore = ReturnType<typeof modalsStore>;

/**
 * @private
 */
export default modalsStore;
