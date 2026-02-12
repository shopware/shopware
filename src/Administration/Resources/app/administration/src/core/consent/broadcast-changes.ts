/**
 * @sw-package framework:fundamentals
 */
import useConsentStore, { type ConsentDTO } from './consent.store';

type ConsentChangedMessage = {
    type: 'consent-changed';
    updatedConsent: ConsentDTO;
};

function isConsentChangedMessage(message: unknown): message is ConsentChangedMessage {
    if (typeof message !== 'object' || message === null) {
        return false;
    }

    return 'type' in message && message.type === 'consent-changed' && 'updatedConsent' in message;
}

/**
 * @private
 */
export default function broadcastConsentChanges(): BroadcastChannel {
    const consentStore = useConsentStore();
    const bc = new BroadcastChannel('shopware-consents');

    bc.onmessage = ({ data }) => {
        if (!isConsentChangedMessage(data)) {
            return;
        }

        const { updatedConsent } = data;

        if (consentStore.consents[updatedConsent.name]) {
            consentStore.consents[updatedConsent.name] = updatedConsent;
        }
    };

    consentStore.$onAction(({ store, name, args, after }) => {
        if (name !== 'accept' && name !== 'revoke') {
            return;
        }

        after(() => {
            const consent = store.consents[args[0]];

            bc.postMessage({ type: 'consent-changed', updatedConsent: { ...consent } });
        });
    });

    return bc;
}
