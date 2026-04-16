import useConsentStore from './consent.store';
import ConsentApiService from './consent.api.service';
import { ConsentEvent } from './events';

const defaultConsents = {
    test_consent: {
        name: 'test_consent',
        identifier: 'user-id',
        scopeName: 'admin_user',
        status: 'unset',
        actor: null,
        updatedAt: null,
        acceptedRevision: null,
        latestRevision: null,
    },
};

describe('/core/consent/consent.store', () => {
    beforeAll(() => {
        Shopware.Service().register('consentApiService', () => {
            return new ConsentApiService(null, null);
        });
    });

    beforeEach(() => {
        useConsentStore().$reset();
        jest.useFakeTimers();
    });

    afterEach(() => {
        jest.useRealTimers();
    });

    it('updates consent states', async () => {
        const service = Shopware.Service('consentApiService');
        const updateSpy = jest.spyOn(service, 'list');
        updateSpy.mockResolvedValueOnce({
            data: { ...defaultConsents },
        });

        const store = useConsentStore();

        expect(store.consents).toEqual({});

        await store.update();

        expect(updateSpy).toHaveBeenCalled();
        expect(store.consents).toEqual(defaultConsents);
    });

    describe('accept', () => {
        it('updates consent state to the response of the service', async () => {
            const service = Shopware.Service('consentApiService');
            const acceptSpy = jest.spyOn(service, 'accept');
            acceptSpy.mockResolvedValueOnce({
                data: {
                    ...defaultConsents.test_consent,
                    status: 'accepted',
                    actor: 'user-id',
                    updatedAt: '2026-02-02 16:04:21.006',
                    acceptedRevision: '2026-02-02',
                    latestRevision: '2026-02-02',
                },
            });

            const store = useConsentStore();
            store.consents = { ...defaultConsents };

            const consentEventHandler = jest.fn();
            Shopware.Utils.EventBus.on('consent', consentEventHandler);

            await store.accept('test_consent');

            Shopware.Utils.EventBus.off('consent', consentEventHandler);

            const expectedUpdatedValue = {
                ...defaultConsents.test_consent,
                status: 'accepted',
                actor: 'user-id',
                updatedAt: '2026-02-02 16:04:21.006',
                acceptedRevision: '2026-02-02',
                latestRevision: '2026-02-02',
            };

            expect(acceptSpy).toHaveBeenCalledWith('test_consent');
            expect(store.consents.test_consent).toEqual(expectedUpdatedValue);

            expect(consentEventHandler).toHaveBeenCalledWith(
                new ConsentEvent('consent_status_change', expectedUpdatedValue, new Date()),
            );
        });

        it('throws error if consent to accept does not exist', async () => {
            const store = useConsentStore();

            await expect(() => store.accept('non_existing_consent')).rejects.toThrow(
                new Error('Consent with name "non_existing_consent" not found in store.'),
            );
        });

        it('does nothing if consent is already accepted for the latest revision', async () => {
            const service = Shopware.Service('consentApiService');
            const acceptSpy = jest.spyOn(service, 'accept');

            const store = useConsentStore();
            store.consents = {
                test_consent: {
                    ...defaultConsents.test_consent,
                    status: 'accepted',
                    acceptedRevision: '2026-02-02',
                    latestRevision: '2026-02-02',
                },
            };

            const consentEventHandler = jest.fn();
            Shopware.Utils.EventBus.on('consent', consentEventHandler);

            await store.accept('test_consent');

            Shopware.Utils.EventBus.off('consent', consentEventHandler);

            expect(acceptSpy).not.toHaveBeenCalled();
            expect(consentEventHandler).not.toHaveBeenCalled();
        });

        it('re-accepts stale consent without sending the cached revision', async () => {
            const service = Shopware.Service('consentApiService');
            const acceptSpy = jest.spyOn(service, 'accept');
            acceptSpy.mockResolvedValueOnce({
                data: {
                    ...defaultConsents.test_consent,
                    status: 'accepted',
                    actor: 'user-id',
                    updatedAt: '2026-02-02 16:04:21.006',
                    acceptedRevision: '2026-02-02',
                    latestRevision: '2026-02-02',
                },
            });

            const store = useConsentStore();
            store.consents = {
                test_consent: {
                    ...defaultConsents.test_consent,
                    status: 'accepted',
                    acceptedRevision: '2026-02-01',
                    latestRevision: '2026-02-02',
                },
            };

            await store.accept('test_consent');

            expect(acceptSpy).toHaveBeenCalledWith('test_consent');
            expect(store.consents.test_consent.acceptedRevision).toBe('2026-02-02');
        });

        describe('revoke', () => {
            it('updates consent state to the response of the service', async () => {
                const service = Shopware.Service('consentApiService');
                const revokeSpy = jest.spyOn(service, 'revoke');
                revokeSpy.mockResolvedValueOnce({
                    data: {
                        ...defaultConsents.test_consent,
                        status: 'revoked',
                        actor: 'user-id',
                        updatedAt: '2026-02-02 16:04:21.006',
                    },
                });

                const store = useConsentStore();
                store.consents = { ...defaultConsents };

                const consentEventHandler = jest.fn();
                Shopware.Utils.EventBus.on('consent', consentEventHandler);

                await store.revoke('test_consent');

                Shopware.Utils.EventBus.off('consent', consentEventHandler);

                const expectedUpdatedValue = {
                    ...defaultConsents.test_consent,
                    status: 'revoked',
                    actor: 'user-id',
                    updatedAt: '2026-02-02 16:04:21.006',
                };

                expect(revokeSpy).toHaveBeenCalledWith('test_consent');
                expect(store.consents.test_consent).toEqual(expectedUpdatedValue);

                expect(consentEventHandler).toHaveBeenCalledWith(
                    new ConsentEvent('consent_status_change', expectedUpdatedValue, new Date()),
                );
            });

            it('throws error if consent to accept does not exist', async () => {
                const store = useConsentStore();

                await expect(() => store.revoke('non_existing_consent')).rejects.toThrow(
                    new Error('Consent with name "non_existing_consent" not found in store.'),
                );
            });

            it('does nothing if consent is already revoked', async () => {
                const service = Shopware.Service('consentApiService');
                const revokeSpy = jest.spyOn(service, 'revoke');

                const store = useConsentStore();
                store.consents = {
                    test_consent: {
                        ...defaultConsents.test_consent,
                        status: 'revoked',
                    },
                };

                const consentEventHandler = jest.fn();
                Shopware.Utils.EventBus.on('consent', consentEventHandler);

                await store.revoke('test_consent');

                Shopware.Utils.EventBus.off('consent', consentEventHandler);

                expect(revokeSpy).not.toHaveBeenCalled();
                expect(consentEventHandler).not.toHaveBeenCalled();
            });
        });
    });

    describe('isAccepted', () => {
        it('throws error if consent does not exist', () => {
            const store = useConsentStore();

            expect(() => store.isAccepted('non_existing_consent')).toThrow(
                new Error('Consent with name "non_existing_consent" not found in store.'),
            );
        });

        it('returns true only if consent is accepted for the latest revision', () => {
            const store = useConsentStore();
            store.consents = {
                test_consent: {
                    ...defaultConsents.test_consent,
                    status: 'accepted',
                    acceptedRevision: '2026-02-02',
                    latestRevision: '2026-02-02',
                },
            };

            expect(store.isAccepted('test_consent')).toBe(true);

            store.consents.test_consent.acceptedRevision = '2026-02-01';
            expect(store.isAccepted('test_consent')).toBe(false);

            store.consents.test_consent.status = 'revoked';
            expect(store.isAccepted('test_consent')).toBe(false);

            store.consents.test_consent.status = 'unset';
            expect(store.isAccepted('test_consent')).toBe(false);
        });

        it('returns true when an accepted consent has no latest revision', () => {
            const store = useConsentStore();
            store.consents = {
                test_consent: {
                    ...defaultConsents.test_consent,
                    status: 'accepted',
                    acceptedRevision: '2026-02-02',
                    latestRevision: null,
                },
            };

            expect(store.isAccepted('test_consent')).toBe(true);
        });
    });

    describe('isStale', () => {
        it('throws error if consent does not exist', () => {
            const store = useConsentStore();

            expect(() => store.isStale('non_existing_consent')).toThrow(
                new Error('Consent with name "non_existing_consent" not found in store.'),
            );
        });

        it('returns true only for accepted consents on an outdated revision', () => {
            const store = useConsentStore();
            store.consents = {
                test_consent: {
                    ...defaultConsents.test_consent,
                    status: 'accepted',
                    acceptedRevision: '2026-02-01',
                    latestRevision: '2026-02-02',
                },
            };

            expect(store.isStale('test_consent')).toBe(true);

            store.consents.test_consent.acceptedRevision = '2026-02-02';
            expect(store.isStale('test_consent')).toBe(false);

            store.consents.test_consent.latestRevision = null;
            expect(store.isStale('test_consent')).toBe(false);

            store.consents.test_consent.latestRevision = '2026-02-03';
            store.consents.test_consent.status = 'revoked';
            expect(store.isStale('test_consent')).toBe(false);
        });
    });

    describe('addConsentRequest', () => {
        it('appends a consent request with requester information', () => {
            const store = useConsentStore();

            store.addConsentRequest(
                {
                    consent: 'test_consent',
                    requestId: 'request-id',
                    requestMessage: 'Please allow analytics',
                    privacyLink: 'https://example.com/privacy',
                },
                {
                    extensionName: 'test-app',
                    origin: 'https://example.com',
                    window,
                },
            );

            expect(store.consentRequestInfo).toEqual([
                {
                    consentRequest: {
                        consent: 'test_consent',
                        requestId: 'request-id',
                        requestMessage: 'Please allow analytics',
                        privacyLink: 'https://example.com/privacy',
                    },
                    requester: {
                        extensionName: 'test-app',
                        origin: 'https://example.com',
                        window,
                    },
                },
            ]);
        });
    });

    describe('removeConsentRequest', () => {
        it('pops first entry', () => {
            const store = useConsentStore();

            store.addConsentRequest(
                {
                    consent: 'first_consent',
                },
                {
                    extensionName: 'first-app',
                    origin: 'https://first.example.com',
                    window,
                },
            );

            store.addConsentRequest(
                {
                    consent: 'second_consent',
                },
                {
                    extensionName: 'second-app',
                    origin: 'https://second.example.com',
                    window,
                },
            );

            store.removeConsentRequest();

            expect(store.consentRequestInfo).toEqual([
                {
                    consentRequest: {
                        consent: 'second_consent',
                    },
                    requester: {
                        extensionName: 'second-app',
                        origin: 'https://second.example.com',
                        window,
                    },
                },
            ]);
        });
    });
});
