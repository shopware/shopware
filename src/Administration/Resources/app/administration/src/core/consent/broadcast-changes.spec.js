import { BroadcastChannel } from 'node:worker_threads';
import broadcastConsentChanges from './broadcast-changes';
import useConsentStore from './consent.store';

describe('src/core/consent/broadcast-changes', () => {
    beforeAll(() => {
        global.BroadcastChannel = BroadcastChannel;

        Shopware.Service().register('consentApiService', () => {
            return {
                accept: () => Promise.resolve({ data: { name: 'test_consent', status: 'accepted' } }),
                revoke: () => Promise.resolve({ data: { name: 'test_consent', status: 'revoked' } }),
            };
        });
    });

    it('sends broadcast message when store runs update', async () => {
        const store = useConsentStore();
        store.consents = {
            test_consent: {
                name: 'test_consent',
                status: 'revoked',
            },
        };

        const bc = broadcastConsentChanges();
        const spy = jest.spyOn(bc, 'postMessage');

        await store.accept('test_consent');
        await flushPromises();

        bc.close();

        expect(spy).toHaveBeenCalled();
        expect(spy).toHaveBeenCalledWith({
            type: 'consent-changed',
            updatedConsent: { name: 'test_consent', status: 'accepted' },
        });
    });

    it('updates the store when a message is received', async () => {
        const testChannel = new BroadcastChannel('shopware-consents');

        const store = useConsentStore();
        store.consents = {
            test_consent: {
                name: 'test_consent',
                status: 'revoked',
            },
        };

        const bc = broadcastConsentChanges();
        const originalOnMessage = bc.onmessage;
        const messageHandled = new Promise((resolve) => {
            bc.onmessage = (event) => {
                if (typeof originalOnMessage === 'function') {
                    originalOnMessage(event);
                }

                resolve(undefined);
            };
        });

        testChannel.postMessage({
            type: 'consent-changed',
            updatedConsent: { name: 'test_consent', status: 'accepted' },
        });

        await messageHandled;

        bc.close();
        testChannel.close();

        expect(store.consents).toEqual({
            test_consent: { name: 'test_consent', status: 'accepted' },
        });
    });
});
