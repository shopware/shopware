/**
 * @sw-package framework:fundamentals
 */
import { dispatchConsentEvent } from './events';

/**
 * @private
 */
export type ConsentDTO = {
    readonly name: string;
    readonly identifier: string;
    readonly scopeName: 'system' | 'admin_user';
    readonly actor: string | null;
    readonly status: 'unset' | 'declined' | 'accepted' | 'revoked';
    readonly updatedAt: string | null;
    readonly acceptedRevision: string | null;
    readonly latestRevision: string | null;
};

type ConsentRequestInfo = {
    consentRequest: {
        consent: string;
        requestId: string;
        requestMessage?: string;
        privacyLink?: string;
    };
    requester: {
        extensionName: string;
        origin: string;
        window: Window;
    };
};

type ConsentStoreState = {
    consents: Record<string, ConsentDTO>;
    consentRequestInfo: ConsentRequestInfo[];
};

function isConsentAccepted(consent: ConsentDTO): boolean {
    if (consent.status !== 'accepted') {
        return false;
    }

    if (consent.latestRevision == null) {
        return true;
    }

    return consent.acceptedRevision === consent.latestRevision;
}

function isConsentStale(consent: ConsentDTO): boolean {
    if (consent.status !== 'accepted' || consent.latestRevision == null) {
        return false;
    }

    return consent.acceptedRevision !== consent.latestRevision;
}

/**
 * @private
 */
export default Shopware.Store.register('consent', {
    state: (): ConsentStoreState => ({
        consents: {},
        consentRequestInfo: [], // acts as a FIFO queue
    }),

    actions: {
        async update(): Promise<void> {
            const { data } = await Shopware.Service('consentApiService').list();

            this.consents = data;
        },

        async accept(name: string): Promise<void> {
            if (!this.consents[name]) {
                throw new Error(`Consent with name "${name}" not found in store.`);
            }

            if (this.isAccepted(name)) {
                return;
            }

            const { data: updatedConsent } = await Shopware.Service('consentApiService').accept(name);

            this.consents[name] = updatedConsent;

            dispatchConsentEvent('consent_status_change', updatedConsent);
        },

        async revoke(name: string): Promise<void> {
            if (!this.consents[name]) {
                throw new Error(`Consent with name "${name}" not found in store.`);
            }

            if (this.consents[name].status === 'revoked' || this.consents[name].status === 'declined') {
                return;
            }

            const { data: updatedConsent } = await Shopware.Service('consentApiService').revoke(name);

            this.consents[name] = updatedConsent;

            dispatchConsentEvent('consent_status_change', updatedConsent);
        },

        isAccepted(name: string): boolean {
            if (!this.consents[name]) {
                throw new Error(`Consent with name "${name}" not found in store.`);
            }

            return isConsentAccepted(this.consents[name]);
        },

        isStale(name: string): boolean {
            if (!this.consents[name]) {
                throw new Error(`Consent with name "${name}" not found in store.`);
            }

            return isConsentStale(this.consents[name]);
        },

        addConsentRequest(
            consentRequest: ConsentRequestInfo['consentRequest'],
            requester: ConsentRequestInfo['requester'],
        ): void {
            this.consentRequestInfo.push({
                consentRequest,
                requester,
            });
        },

        removeConsentRequest(): void {
            this.consentRequestInfo.shift();
        },
    },
});
