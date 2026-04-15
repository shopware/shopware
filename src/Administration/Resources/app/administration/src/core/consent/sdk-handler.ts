/**
 * @sw-package framework
 */
import { send, type HandleMethod } from '@shopware-ag/meteor-admin-sdk/es/channel';
import useExtensionsStore from 'src/app/store/extensions.store';
import useConsentStore, { type ConsentDTO } from 'src/core/consent/consent.store';

/**
 * @private
 */
export const handleConsentStatus: HandleMethod<'consentStatus'> = (message) => {
    const consentStore = useConsentStore();
    const consent = consentStore.consents[message.consent];

    if (!consent) {
        return Promise.reject(new Error(`Could not find consent with name: "${message.consent}"`));
    }

    return Promise.resolve({
        ...consent,
    });
};

/**
 * @private
 */
export const handleConsentRequest: HandleMethod<'consentRequest'> = (message, { _event_ }) => {
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
        return Promise.reject(new Error(`No extension found for origin: ${_event_.origin}`));
    }

    if (!isWindow(_event_.source)) {
        return Promise.reject(new Error('The source of the ConsentRequest is not a window.'));
    }

    if (!consentStore.consents[message.consent]) {
        return Promise.reject(new Error(`Consent with name "${message.consent}" does not exist.`));
    }

    consentStore.addConsentRequest(
        {
            consent: message.consent,
            requestId: message.requestId,
            privacyLink: message.privacyLink,
            requestMessage: message.requestMessage,
        },
        {
            extensionName: extension[0],
            origin: _event_.origin,
            window: _event_.source,
        },
    );

    return Promise.resolve();
};

/**
 * @private
 */
export const sendConsentRequestResponse = (receiver: Window, requestId: string, consent: ConsentDTO) => {
    send(
        'consentRequestResponse',
        {
            name: consent.name,
            requestId: requestId,
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
