/**
 * @sw-package framework:fundamentals
 */
import useConsentStore from 'src/core/consent/consent.store';
import ConsentApiService from '../../core/consent/consent.api.service';

type ConsentChangedMessage = {
    consent: string,
    state: 'accepted' | 'revoked',
}

function isConsentChangedMessage(message: unknown): message is ConsentChangedMessage {
    if (typeof message !== 'object' || message === null) {
        return false;
    }

    return 'consent' in message && 'state' in message;
}

/**
 * @private
 */
export default async function initConsentStore(): Promise<void> {
    /**
     * @private
     */
    Shopware.Service().register('consentApiService',(serviceContainer) => {
        return new ConsentApiService(
            Shopware.Application.getContainer('init').httpClient,
            serviceContainer.loginService,
        );
    });


    const consentStore = useConsentStore();

    await consentStore.update();

    setInterval(() => {
        void consentStore.update();
    }, 300000); // every 5 minutes

    const bc = new BroadcastChannel('shopware-consent-channel');

    bc.onmessage  = ({ data }) => {
        if (!isConsentChangedMessage(data)) {
            return;
        }

        const { consent, state } = data;

        if (consentStore.consents[consent]) {
            consentStore.consents[consent].status = state;
        }
    }

    consentStore.$onAction(({ name, args, after}) => {
        if (name !== 'accept' && name !== 'revoke') {
            return;
        }

        after(() => {
            bc.postMessage({ consent: args[0], state: name === 'accept' ? 'accepted' : 'revoked' })
        });
    })
}
