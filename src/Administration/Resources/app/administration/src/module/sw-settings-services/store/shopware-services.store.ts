/**
 * @sw-package framework
 */
import { defineStore } from 'pinia';
import type { RevisionData, ServicesRevision } from '../service/service-registry-client';

/**
 * @private
 */
export type ServiceConfiguration = {
    'permissionsGrantedAt'?: string,
    'disabled'?: boolean,
}

type ShopwareServicesState = {
    config: ServiceConfiguration | null,
    revisions: RevisionData | null,
    showGrantPermissionsModal: boolean,
}

/* eslint-disable import/prefer-default-export */
/**
 * @private
 *
 */
export const useShopwareServicesStore = defineStore('shopwareServices', {
    state: (): ShopwareServicesState => ({
        config: null,
        revisions: null,
        showGrantPermissionsModal: false,
    }),

    getters: {
        consentGiven(): boolean {
            const isConsentGiven = this.config?.permissionsGrantedAt ?? false;

            if (isConsentGiven === false) {
                return false;
            }

            const currentRevision = this.revisions?.['latest-revision'] ?? false;

            if (currentRevision === false) {
                return false;
            }

            return new Date(currentRevision) < new Date(isConsentGiven);
        },
        currentRevision(): ServicesRevision | null {
            if (!this.revisions) {
                return null;
            }

            return this.revisions['available-revisions'].find((revision) => {
                return revision.revision === this.revisions!['latest-revision'];
            }) ?? null;
        },
    },
});
/* eslint-enable import/prefer-default-export */