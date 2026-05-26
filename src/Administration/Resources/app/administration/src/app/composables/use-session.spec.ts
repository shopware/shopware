/**
 * @sw-package framework
 */

import { createPinia, setActivePinia } from 'pinia';
import useSession from './use-session';
import useSystem from './use-system';

describe('src/app/state/session.store.js', () => {
    const session = useSession();

    beforeEach(() => {
        setActivePinia(createPinia());
    });

    afterEach(() => {
        session.removeCurrentUser();
    });

    it('returns all user privileges', () => {
        session.setCurrentUser({
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

        expect(session.userPrivileges.value).toContain('system.core_update');
        expect(session.userPrivileges.value).toContain('system:core:update');
        expect(session.userPrivileges.value).toContain('system.clear_cache');
        expect(session.userPrivileges.value).toContain('system:clear:cache');
        expect(session.userPrivileges.value).toContain('system.plugin_maintain');
        expect(session.userPrivileges.value).toContain('system:plugin:maintain');
        expect(session.userPrivileges.value).toContain('orders.create_discounts');
        expect(session.userPrivileges.value).toContain('order:create:discount');
    });

    it('returns an empty array if no user is set', () => {
        expect(session.userPrivileges.value).toEqual([]);
    });

    it('returns the admin locale language', () => {
        session.currentLocale.value = 'en-GB';

        expect(session.adminLocaleLanguage.value).toBe('en');
    });

    it('returns the admin locale region', () => {
        session.currentLocale.value = 'en-GB';

        expect(session.adminLocaleRegion.value).toBe('GB');
    });

    it('clears the languageId if it is not logged in', async () => {
        Shopware.Service = jest.fn().mockImplementation(() => ({
            isLoggedIn: jest.fn().mockReturnValue(false),
        }));
        useSystem().locales.value = [
            'en-GB',
            'de-DE',
        ];

        session.languageId.value = '123';
        session.currentLocale.value = 'en-GB';

        await session.setAdminLocale('de-DE');

        expect(session.languageId.value).toBe('');
    });

    it('sets the admin locale without loading locale data if the user cannot read locales', async () => {
        const localeToLanguage = jest.fn();
        const storeCurrentLocale = jest.fn();
        const localeFactory = Shopware.Application.getContainer('factory').locale;
        const previousStoreCurrentLocale = localeFactory.storeCurrentLocale;

        Shopware.Service = jest.fn().mockImplementation((serviceName) => {
            if (serviceName === 'loginService') {
                return { isLoggedIn: jest.fn().mockReturnValue(true) };
            }

            if (serviceName === 'localeToLanguageService') {
                return { localeToLanguage };
            }

            return {};
        });
        localeFactory.storeCurrentLocale = storeCurrentLocale;
        Shopware.Context.api.systemLanguageId = 'system-language-id';
        useSystem().locales.value = [
            'en-GB',
            'de-DE',
        ];
        session.setCurrentUser({
            admin: false,
            aclRoles: [],
        } as EntitySchema.user);

        await session.setAdminLocale('de-DE');

        expect(localeToLanguage).not.toHaveBeenCalled();
        expect(session.languageId.value).toBe('system-language-id');
        expect(session.currentLocale.value).toBe('de-DE');
        expect(storeCurrentLocale).toHaveBeenCalledWith('de-DE');

        localeFactory.storeCurrentLocale = previousStoreCurrentLocale;
    });
});
