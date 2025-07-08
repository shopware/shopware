/**
 * @sw-package framework
 */
import type { RevisionData, ServicesRevision } from '../service/service-registry-client';

/**
 * @private
 */
export type PermissionsConsent = {
    identifier: string;
    revision: string;
    consentingUserId: string;
    grantedAt: string;
};

/**
 * @private
 */
export type ServiceConfiguration = {
    permissionsConsent?: PermissionsConsent;
    disabled?: boolean;
};

type ShopwareServicesState = {
    config: ServiceConfiguration | null;
    revisions: RevisionData | null;
    showGrantPermissionsModal: boolean;
};

/* eslint-disable import/prefer-default-export */
/**
 * @private
 *
 */
export const useShopwareServicesStore = Shopware.Store.register('shopwareServices', {
    state: (): ShopwareServicesState => ({
        config: null,
        revisions: null,
        showGrantPermissionsModal: false,
    }),

    getters: {
        consentGiven(): boolean {
            const permissionsConsent = this.config?.permissionsConsent ?? false;

            if (permissionsConsent === false) {
                return false;
            }

            const currentRevision = this.revisions?.['latest-revision'] ?? false;

            if (currentRevision === false) {
                return false;
            }

            return currentRevision === permissionsConsent.revision;
        },
        currentRevision(): ServicesRevision | null {
            if (!this.revisions) {
                return null;
            }

            return (
                this.revisions['available-revisions'].find((revision) => {
                    return revision.revision === this.revisions!['latest-revision'];
                }) ?? null
            );
        },
    },
});
/* eslint-enable import/prefer-default-export */
