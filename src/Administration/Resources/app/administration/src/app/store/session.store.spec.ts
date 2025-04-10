/**
 * @sw-package framework
 */

import { createPinia, setActivePinia } from 'pinia';
import useSystem from '../composables/use-system';

describe('src/app/state/session.store.js', () => {
    const sessionStore = Shopware.Store.get('session');

    beforeEach(() => {
        setActivePinia(createPinia());
    });

    afterEach(() => {
        sessionStore.removeCurrentUser();
    });

    it('returns all user privileges', () => {
        sessionStore.setCurrentUser({
            aclRoles: [
                {
                    privileges: [
                        'system.core_update',
                        'system:core:update',
                        'system.clear_cache',
                        'system:clear:cache',
                    ],
                },
                {
                    privileges: [
                        'system.plugin_maintain',
                        'system:plugin:maintain',
                        'orders.create_discounts',
                        'order:create:discount',
                    ],
                },
            ],
        } as EntitySchema.user);

        expect(sessionStore.userPrivileges).toContain('system.core_update');
        expect(sessionStore.userPrivileges).toContain('system:core:update');
        expect(sessionStore.userPrivileges).toContain('system.clear_cache');
        expect(sessionStore.userPrivileges).toContain('system:clear:cache');
        expect(sessionStore.userPrivileges).toContain('system.plugin_maintain');
        expect(sessionStore.userPrivileges).toContain('system:plugin:maintain');
        expect(sessionStore.userPrivileges).toContain('orders.create_discounts');
        expect(sessionStore.userPrivileges).toContain('order:create:discount');
    });

    it('returns an empty array if no user is set', () => {
        expect(sessionStore.userPrivileges).toEqual([]);
    });

    it('returns the admin locale language', () => {
        sessionStore.currentLocale = 'en-GB';

        expect(sessionStore.adminLocaleLanguage).toBe('en');
    });

    it('returns the admin locale region', () => {
        sessionStore.currentLocale = 'en-GB';

        expect(sessionStore.adminLocaleRegion).toBe('GB');
    });

    it('clears the languageId if it is not logged in', async () => {
        Shopware.Service = jest.fn().mockImplementation(() => ({
            isLoggedIn: jest.fn().mockReturnValue(false),
        }));
        useSystem().locales.value = [
            'en-GB',
            'de-DE',
        ];

        sessionStore.languageId = '123';
        sessionStore.currentLocale = 'en-GB';

        await sessionStore.setAdminLocale('de-DE');

        expect(sessionStore.languageId).toBe('');
    });
});
