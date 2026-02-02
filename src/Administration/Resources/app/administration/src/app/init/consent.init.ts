/**
 * @sw-package framework:fundamentals
 */
import useConsentStore, { type ConsentDTO } from 'src/core/consent/consent.store';
import ConsentApiService from 'src/core/consent/consent.api.service';

type ConsentChangedMessage = {
    updatedConsent: ConsentDTO;
};

function isConsentChangedMessage(message: unknown): message is ConsentChangedMessage {
    if (typeof message !== 'object' || message === null) {
        return false;
    }

    return 'updatedConsent' in message;
}

/**
 * @private
 */
export default async function initConsentStore(): Promise<void> {
    /**
     * @private
     */
    Shopware.Service().register('consentApiService', (serviceContainer) => {
        return new ConsentApiService(Shopware.Application.getContainer('init').httpClient, serviceContainer.loginService);
    });

    const consentStore = useConsentStore();

    await consentStore.update();

    setInterval(() => {
        void consentStore.update();
    }, 300000); // every 5 minutes

    const bc = new BroadcastChannel('shopware-consent-channel');

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

            bc.postMessage({ updatedConsent: { ...consent } });
        });
    });
}
