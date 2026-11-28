/**
 * @sw-package framework
 */
import type { RevisionData, ServicesRevision } from '../service/shopware-services.service';

/**
 * @private
 */
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
};

/**
 * @private
 *
 */
export const useShopwareServicesStore = Shopware.Store.register('shopwareServices', {
    state: (): ShopwareServicesState => ({
        config: null,
        revisions: null,
    }),

    getters: {
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
