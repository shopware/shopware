/**
 * @sw-package framework
 */
import initializeWhatsNew from 'src/app/init/whats-new.init';

describe('src/app/init/whats-new.init.ts', () => {
    it('forgets the what is new state of the previous user on logout', () => {
        const logoutListeners = [];

        Shopware.Service().register('loginService', () => ({
            addOnLogoutListener: (listener) => logoutListeners.push(listener),
        }));

        initializeWhatsNew();

        const whatsNewStore = Shopware.Store.get('whatsNew');
        whatsNewStore.isLoaded = true;
        whatsNewStore.entries = {
            'settings.foo': {
                since: '6.7.17.0',
                seenAt: Date.now(),
            },
        };

        logoutListeners.forEach((listener) => listener());

        expect(whatsNewStore.isLoaded).toBe(false);
        expect(whatsNewStore.entries).toStrictEqual({});
    });
});
