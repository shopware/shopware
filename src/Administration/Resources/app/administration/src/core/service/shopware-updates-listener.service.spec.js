/**
 * @sw-package framework
 */
import addShopwareUpdatesListener from 'src/core/service/shopware-updates-listener.service';

describe('src/core/service/shopware-updates-listener.service', () => {
    let createNotificationSpy;

    async function loginWithUpdateResponse(response) {
        let loginCallback;
        const loginService = {
            addOnLoginListener: (callback) => {
                loginCallback = callback;
            },
        };
        const serviceContainer = {
            updateService: {
                checkForUpdates: () => Promise.resolve(response),
            },
        };

        addShopwareUpdatesListener(loginService, serviceContainer);
        loginCallback();
        await flushPromises();
    }

    beforeEach(() => {
        jest.spyOn(Shopware.Application, 'getApplicationRoot').mockImplementation(() => ({
            $t: (key) => key,
        }));
        jest.spyOn(Shopware, 'Service').mockImplementation(() => ({
            can: () => true,
        }));
        Shopware.Context.app.hideUpdateModule = false;
        createNotificationSpy = jest
            .spyOn(Shopware.Store.get('notification'), 'createNotification')
            .mockImplementation(() => Promise.resolve());
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('notifies about an available update', async () => {
        await loginWithUpdateResponse({
            version: '6.4.18.0',
            autoUpdateEnabled: true,
        });

        expect(createNotificationSpy).toHaveBeenCalled();
    });

    it('does not notify when auto updates are disabled', async () => {
        await loginWithUpdateResponse({
            version: '6.4.18.0',
            autoUpdateEnabled: false,
        });

        expect(createNotificationSpy).not.toHaveBeenCalled();
    });

    it('does not notify when the shop is up to date', async () => {
        await loginWithUpdateResponse({});

        expect(createNotificationSpy).not.toHaveBeenCalled();
    });

    it('does not notify when the update module is hidden', async () => {
        Shopware.Context.app.hideUpdateModule = true;

        await loginWithUpdateResponse({
            version: '6.4.18.0',
            autoUpdateEnabled: true,
        });

        expect(createNotificationSpy).not.toHaveBeenCalled();
    });
});
