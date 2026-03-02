import useConsentStore from 'src/core/consent/consent.store';
import broadcastConsentChanges from 'src/core/consent/broadcast-changes';
import ConsentApiService from 'src/core/consent/consent.api.service';
import initConsent from './consent.init';

jest.mock('src/core/consent/broadcast-changes');
jest.mock('src/core/consent/consent.api.service');
describe('src/app/init/consent.init.js', () => {
    it('initializes consent store', async () => {
        ConsentApiService.mockImplementationOnce(() => {
            const defaultConsent = {
                name: 'test_consent',
                identifier: 'user-id',
                scopeName: 'user_id',
                status: 'unset',
                actor: null,
                updated_at: null,
            };

            return {
                list: () => Promise.resolve({ data: { test_consent: defaultConsent } }),
            };
        });

        const consentStore = useConsentStore();

        expect(consentStore.consents).toEqual({});

        await initConsent();

        expect(consentStore.consents).toEqual({
            test_consent: {
                name: 'test_consent',
                identifier: 'user-id',
                scopeName: 'user_id',
                status: 'unset',
                actor: null,
                updated_at: null,
            },
        });

        expect(broadcastConsentChanges).toHaveBeenCalled();
    });
});
