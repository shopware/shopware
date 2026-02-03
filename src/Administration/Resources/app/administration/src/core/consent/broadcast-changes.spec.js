import { BroadcastChannel } from 'worker_threads';
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
        const testChannel = new BroadcastChannel('shopware-consents');
        testChannel.onmessage = jest.fn(({ data }) =>
            expect(data).toEqual({ type: 'consent-changed', updatedConsent: { name: 'test_consent', status: 'accepted' } }),
        );

        const store = useConsentStore();
        store.consents = {
            test_consent: {
                name: 'test_consent',
                status: 'revoked',
            },
        };

        const bc = broadcastConsentChanges();

        await store.accept('test_consent');
        await flushPromises();

        expect(testChannel.onmessage).toHaveBeenCalled();

        testChannel.close();
        bc.close();
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

        testChannel.postMessage({
            type: 'consent-changed',
            updatedConsent: { name: 'test_consent', status: 'accepted' },
        });

        await flushPromises();

        expect(store.consents).toEqual({
            test_consent: { name: 'test_consent', status: 'accepted' },
        });

        bc.close();
        testChannel.close();
    });
});
