/**
 * @sw-package framework
 */
import { send } from '@shopware-ag/meteor-admin-sdk/es/channel';
import useExtensionsStore from 'src/app/store/extensions.store';
import useConsentStore from 'src/core/consent/consent.store';
import { handleConsentRequest, handleConsentStatus, sendConsentRequestResponse } from 'src/core/consent/sdk-handler';

jest.mock('@shopware-ag/meteor-admin-sdk/es/channel', () => ({
    send: jest.fn(() => Promise.resolve()),
}));

describe('src/core/consent/sdk-handler.ts', () => {
    const consent = {
        name: 'product_analytics',
        identifier: 'user-id',
        scopeName: 'admin_user',
        actor: null,
        status: 'unset',
        updatedAt: null,
        acceptedRevision: null,
        latestRevision: null,
    };

    beforeEach(() => {
        useConsentStore().$reset();
        Shopware.Store.get('extensions').$reset();
        jest.clearAllMocks();
    });

    describe('handleConsentStatus', () => {
        it('returns a copy of the requested consent', async () => {
            const consentStore = useConsentStore();
            consentStore.consents = {
                [consent.name]: consent,
            };

            const result = await handleConsentStatus({
                consent: consent.name,
            });

            expect(result).toEqual(consent);
            expect(result).not.toBe(consentStore.consents[consent.name]);
        });

        it('throws when the consent does not exist', async () => {
            await expect(
                handleConsentStatus({
                    consent: 'missing_consent',
                }),
            ).rejects.toThrow(new Error('Could not find consent with name: "missing_consent"'));
        });
    });

    describe('handleConsentRequest', () => {
        const sourceWindow = window;

        beforeEach(() => {
            const extensionsStore = useExtensionsStore();
            const consentStore = useConsentStore();

            extensionsStore.addExtension({
                name: 'test-app',
                baseUrl: 'https://app.example.com/extension/index.html',
                permissions: {},
                version: '1.0.0',
                type: 'app',
                integrationId: 'integration-id',
                active: true,
            });

            consentStore.consents = {
                [consent.name]: consent,
            };
        });

        it('stores the consent request for the matching extension origin', async () => {
            const consentStore = useConsentStore();

            await handleConsentRequest(
                {
                    consent: consent.name,
                    privacyLink: 'https://app.example.com/privacy',
                    requestMessage: 'Please allow analytics',
                },
                {
                    _event_: {
                        origin: 'https://app.example.com',
                        source: sourceWindow,
                    },
                },
            );

            expect(consentStore.consentRequestInfo).toEqual([
                {
                    consentRequest: {
                        consent: consent.name,
                        privacyLink: 'https://app.example.com/privacy',
                        requestMessage: 'Please allow analytics',
                    },
                    requester: {
                        extensionName: 'test-app',
                        origin: 'https://app.example.com',
                        window: sourceWindow,
                    },
                },
            ]);
        });

        it('throws when no extension matches the event origin', async () => {
            await expect(
                handleConsentRequest(
                    {
                        consent: consent.name,
                    },
                    {
                        _event_: {
                            origin: 'https://unknown.example.com',
                            source: sourceWindow,
                        },
                    },
                ),
            ).rejects.toThrow(new Error('No extension found for origin: https://unknown.example.com'));
        });

        it('throws when the event source is not a window', async () => {
            await expect(
                handleConsentRequest(
                    {
                        consent: consent.name,
                    },
                    {
                        _event_: {
                            origin: 'https://app.example.com',
                            source: {
                                toString: () => '[object MessagePort]',
                            },
                        },
                    },
                ),
            ).rejects.toThrow(new Error('The source of the ConsentRequest is not a window.'));
        });

        it('throws when the requested consent does not exist', async () => {
            const consentStore = useConsentStore();
            consentStore.consents = {};

            await expect(
                handleConsentRequest(
                    {
                        consent: 'missing_consent',
                    },
                    {
                        _event_: {
                            origin: 'https://app.example.com',
                            source: sourceWindow,
                        },
                    },
                ),
            ).rejects.toThrow(new Error('Consent with name "missing_consent" does not exist.'));
        });
    });

    describe('sendConsentRequestResponse', () => {
        it('sends the consent response to the receiver window', async () => {
            await sendConsentRequestResponse(window, 'request-id', consent);

            expect(send).toHaveBeenCalledWith(
                'consentRequestResponse',
                {
                    name: consent.name,
                    requestId: 'request-id',
                    consent: {
                        ...consent,
                    },
                },
                window,
            );
        });
    });
});
