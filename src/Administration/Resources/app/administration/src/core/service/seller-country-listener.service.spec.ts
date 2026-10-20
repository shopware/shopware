/**
 * @sw-package checkout
 */
import addSellerCountryListener from 'src/core/service/seller-country-listener.service';
import type SystemConfigApiService from './api/system-config.api.service';
import type { LoginService } from './login.service';

const getValues = jest.fn();
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Service().register('systemConfigApiService', () => ({ getValues }) as unknown as SystemConfigApiService);

describe('src/core/service/seller-country-listener.service.ts', () => {
    let login: () => unknown;
    let can: jest.SpyInstance;
    let createNotification: jest.SpyInstance;

    beforeEach(() => {
        getValues.mockReset();

        can = jest.spyOn(Shopware.Service('acl'), 'can').mockReturnValue(true);
        createNotification = jest.spyOn(Shopware.Store.get('notification'), 'createNotification').mockReturnValue(null);

        addSellerCountryListener({
            addOnLoginListener: (listener: () => unknown) => {
                login = listener;
            },
        } as unknown as LoginService);
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('reminds to set the shop owner country while it is empty', async () => {
        getValues.mockResolvedValue({ 'core.basicInformation.sellerCountryId': null });

        login();
        await flushPromises();

        expect(can).toHaveBeenCalledWith('system.system_config');
        expect(getValues).toHaveBeenCalledWith('core.basicInformation');
        expect(createNotification).toHaveBeenCalledTimes(1);
        expect(createNotification).toHaveBeenCalledWith(
            expect.objectContaining({
                variant: 'attention',
                message: 'sw-settings-basic-information.sellerCountryNotification.message',
                actions: [
                    {
                        label: 'sw-settings-basic-information.sellerCountryNotification.openSettings',
                        route: { name: 'sw.settings.basic.information.index' },
                    },
                ],
            }),
        );
    });

    it('does not remind once the shop owner country is set', async () => {
        getValues.mockResolvedValue({ 'core.basicInformation.sellerCountryId': 'de-country-id' });

        login();
        await flushPromises();

        expect(createNotification).not.toHaveBeenCalled();
    });

    it('does not read the setting without the privilege to change it', async () => {
        can.mockReturnValue(false);

        login();
        await flushPromises();

        expect(getValues).not.toHaveBeenCalled();
        expect(createNotification).not.toHaveBeenCalled();
    });

    it('does not remind when the setting cannot be read', async () => {
        getValues.mockRejectedValue(new Error('Forbidden'));

        login();
        await flushPromises();

        expect(createNotification).not.toHaveBeenCalled();
    });
});
