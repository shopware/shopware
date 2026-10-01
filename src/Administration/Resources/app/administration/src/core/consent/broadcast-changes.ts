/**
 * @sw-package framework:fundamentals
 */
import useConsentStore, { type ConsentDTO } from './consent.store';
import { isConsentEvent, isConsentEventType } from './events';

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

    const eventHandler = (consentEvent: unknown) => {
        if (!isConsentEvent(consentEvent) || !isConsentEventType(consentEvent, 'consent_status_change')) {
            return;
        }

        bc.postMessage({ type: 'consent-changed', updatedConsent: { ...consentEvent.eventProperties } });
    };

    // eslint-disable-next-line listeners/no-missing-remove-event-listener
    Shopware.Utils.EventBus.on('consent', eventHandler);

    return bc;
}
