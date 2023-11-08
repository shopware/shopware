import createLoginService from './login.service';
import addUsageDataConsentListener from './usage-data-consent-listener.service';

describe('src/core/service/usage-data-consent-listener.service.ts', () => {
    let isConsentRequestSuccessful = true;
    let loginService = null;
    let serviceContainer = null;

    beforeEach(() => {
        window.localStorage.setItem('redirectFromLogin', 'true');

        loginService = createLoginService({}, Shopware.Context.api);
        serviceContainer = {
            usageDataService: {
                getConsent: () => {
                    if (isConsentRequestSuccessful) {
                        return Promise.resolve({
                            isConsentGiven: true,
                            isBannerHidden: false,
                        });
                    }

                    return Promise.reject();
                },
            },
        };

        Shopware.State.commit('usageData/updateConsent', {
            isConsentGiven: undefined,
            isBannerHidden: undefined,
        });
    });

    it('should add the login and logout listeners', async () => {
        const addLoginListenerSpy = jest.spyOn(loginService, 'addOnLoginListener');
        const addLogoutListenerSpy = jest.spyOn(loginService, 'addOnLogoutListener');

        addUsageDataConsentListener(loginService, serviceContainer);

        expect(addLoginListenerSpy).toHaveBeenCalled();
        expect(addLogoutListenerSpy).toHaveBeenCalled();
    });

    it('should update consent on login', async () => {
        addUsageDataConsentListener(loginService, serviceContainer);

        loginService.notifyOnLoginListener();

        await flushPromises();

        expect(Shopware.State.get('usageData').isConsentGiven).toBe(true);
        expect(Shopware.State.get('usageData').isBannerHidden).toBe(false);
    });

    it('should reset the consent if the request fails', async () => {
        isConsentRequestSuccessful = false;

        addUsageDataConsentListener(loginService, serviceContainer);

        loginService.notifyOnLoginListener();

        await flushPromises();

        expect(Shopware.State.get('usageData').isConsentGiven).toBe(false);
        expect(Shopware.State.get('usageData').isBannerHidden).toBe(true);
    });

    it('should reset the consent on logout', async () => {
        addUsageDataConsentListener(loginService, serviceContainer);

        loginService.forwardLogout();

        await flushPromises();

        expect(Shopware.State.get('usageData').isConsentGiven).toBe(false);
        expect(Shopware.State.get('usageData').isBannerHidden).toBe(true);
    });
});
