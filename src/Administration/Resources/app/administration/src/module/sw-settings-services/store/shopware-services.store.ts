/**
 * @sw-package framework
 */
import { defineStore } from 'pinia';

type ShopwareServicesState = {
    consent: {
        consentVersion: Date,
        consentGivenAt: Date | null,
    } | null,
    legalDocuments: {
        documentationLink: string,
        feedbackLink: string,
        tosLink: string,
    } | null,
}

/* eslint-disable import/prefer-default-export */
/**
 * @private
 *
 */
export const useShopwareServicesStore = defineStore('shopwareServices', {
    state: (): ShopwareServicesState => ({
        consent: null,
        legalDocuments: null,
    }),

    getters: {
        consentGiven(): boolean {
            return this.consent !== null &&
                this.consent.consentGivenAt !== null &&
                this.consent.consentVersion <= this.consent.consentGivenAt;
        },
    },
});
/* eslint-enable import/prefer-default-export */