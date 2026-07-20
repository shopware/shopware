import useConsentStore from 'src/core/consent/consent.store';
import broadcastConsentChanges from 'src/core/consent/broadcast-changes';
import ConsentApiService from 'src/core/consent/consent.api.service';
import initConsent from './consent.init';
import { handleConsentRequest, handleConsentStatus } from 'src/core/consent/sdk-handler';

jest.mock('src/core/consent/broadcast-changes');
jest.mock('src/core/consent/consent.api.service');
describe('src/app/init/consent.init.js', () => {
    it('initializes consent store and sdk-handlers', async () => {
        ConsentApiService.mockImplementationOnce(() => {
            const defaultConsent = {
                name: 'test_consent',
                identifier: 'user-id',
                scopeName: 'user_id',
                status: 'unset',
                actor: null,
                updatedAt: null,
            };

            return {
                list: () => Promise.resolve({ data: { test_consent: defaultConsent } }),
            };
        });

        const consentStore = useConsentStore();

        expect(consentStore.consents).toEqual({});
        const extensionAPISpy = jest.spyOn(Shopware.ExtensionAPI, 'handle');

        await initConsent();

        expect(consentStore.consents).toEqual({
            test_consent: {
                name: 'test_consent',
                identifier: 'user-id',
                scopeName: 'user_id',
                status: 'unset',
                actor: null,
                updatedAt: null,
            },
        });

        expect(broadcastConsentChanges).toHaveBeenCalled();

        expect(extensionAPISpy).toHaveBeenCalledTimes(2);
        expect(extensionAPISpy.mock.calls).toEqual([
            [
                'consentStatus',
                handleConsentStatus,
            ],
            [
                'consentRequest',
                handleConsentRequest,
            ],
        ]);
    });
});
