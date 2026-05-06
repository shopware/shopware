/**
 * @sw-package framework
 */
import useConsentStore, { type ConsentDTO } from 'src/core/consent/consent.store';
import type { RevisionData, ServicesRevision } from '../service/shopware-services.service';

export const SERVICE_CONSENT_NAME = 'service_consent';

/**
 * @private
 */
export type ServiceConfiguration = {
    disabled?: boolean;
};

type ShopwareServicesState = {
    config: ServiceConfiguration | null;
    revisions: RevisionData | null;
    showGrantPermissionsModal: boolean;
};

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
        serviceConsent(): ConsentDTO | null {
            return useConsentStore().consents[SERVICE_CONSENT_NAME] ?? null;
        },

        consentGiven(): boolean {
            const serviceConsent = this.serviceConsent;
            if (serviceConsent === null || serviceConsent.status !== 'accepted') {
                return false;
            }

            const currentRevision = this.revisions?.['latest-revision'] ?? false;

            if (currentRevision === false) {
                return false;
            }

            return currentRevision === serviceConsent.acceptedRevision;
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
