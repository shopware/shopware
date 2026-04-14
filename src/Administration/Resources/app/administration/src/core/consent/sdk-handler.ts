/**
 * @sw-package framework
 */
import { send, type HandleMethod } from '@shopware-ag/meteor-admin-sdk/es/channel';
import useExtensionsStore from 'src/app/store/extensions.store';
import useConsentStore, { type ConsentDTO } from 'src/core/consent/consent.store';

/**
 * @private
 */
export const handleConsentStatus: HandleMethod<'consentStatus'> = async (message) => {
    const consentStore = useConsentStore();
    const consent = consentStore.consents[message.consent];

    if (!consent) {
        throw new Error(`Could not find consent with name: "${message.consent}"`);
    }

    return {
        ...consent,
    };
};

/**
 * @private
 */
export const handleConsentRequest: HandleMethod<'consentRequest'> = async (message, { _event_ }) => {
    const extensionsStore = useExtensionsStore();
    const consentStore = useConsentStore();

    const extension = Object.entries(extensionsStore.extensionsState).find(
        ([
            ,
            ext,
        ]) => {
            return new URL(_event_.origin).origin === new URL(ext.baseUrl).origin;
        },
    );

    if (!extension) {
        throw new Error(`No extension found for origin: ${_event_.origin}`);
    }

    if (!isWindow(_event_.source)) {
        throw new Error('The source of the ConsentRequest is not a window.');
    }

    if (!consentStore.consents[message.consent]) {
        throw new Error(`Consent with name "${message.consent}" does not exist.`);
    }

    consentStore.addConsentRequest(
        {
            consent: message.consent,
            privacyLink: message.privacyLink,
            requestMessage: message.requestMessage,
        },
        {
            extensionName: extension[0],
            origin: _event_.origin,
            window: _event_.source,
        },
    );
};

/**
 * @private
 */
export const sendConsentRequestResponse = (receiver: Window, consent: ConsentDTO) => {
    send(
        'consentRequestResponse',
        {
            name: consent.name,
            consent: {
                ...consent,
            },
        },
        receiver,
    ).catch(() => {
        // ignore timeouts if request is aborted
    });
};

function isWindow(source: MessageEventSource | null): source is Window {
    // eslint-disable-next-line @typescript-eslint/no-base-to-string
    return source !== null && source.toString() === '[object Window]';
}
