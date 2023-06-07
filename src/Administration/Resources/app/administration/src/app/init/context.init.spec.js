import initContext from 'src/app/init/context.init';
import { getCurrency, getEnvironment, getLocale, getShopwareVersion, getModuleInformation, getAppInformation, getUserInformation } from '@shopware-ag/admin-extension-sdk/es/context';
import extensionsStore from '../state/extensions.store';

describe('src/app/init/context.init.ts', () => {
    beforeAll(() => {
        initContext();
    });

    beforeEach(() => {
        if (Shopware.State.get('extensions')) {
            Shopware.State.unregisterModule('extensions');
        }

        Shopware.State.registerModule('extensions', extensionsStore);
    });

    afterEach(() => {
        Shopware.State.unregisterModule('extensions');
    });

    it('should handle currency', async () => {
        await getCurrency().then((currency) => {
            expect(currency).toEqual(expect.objectContaining({
                systemCurrencyId: expect.any(String),
                systemCurrencyISOCode: expect.any(String),
            }));
        });
    });

    it('should handle environment', async () => {
        await getEnvironment().then((environment) => {
            expect(environment).toEqual(expect.any(String));
        });
    });

    it('should handle locale', async () => {
        await getLocale().then((locale) => {
            expect(locale).toEqual(expect.objectContaining({
                fallbackLocale: expect.any(String),
                locale: expect.any(String),
            }));
        });
    });

    it('should handle shopware version', async () => {
        await getShopwareVersion().then((version) => {
            expect(version).toEqual(expect.any(String));
        });
    });

    it('should handle module information', async () => {
        await getModuleInformation().then((moduleInformation) => {
            expect(moduleInformation).toEqual(expect.objectContaining({
                modules: expect.any(Array),
            }));
        });
    });

    it('should return placeholder app information', async () => {
        await getAppInformation().then((appInformation) => {
            expect(appInformation).toEqual(expect.objectContaining({
                name: 'unknown',
                version: '0.0.0',
                type: 'app',
            }));
        });
    });

    it('should return app information', async () => {
        Shopware.State.commit('extensions/addExtension', {
            name: 'jestapp',
            baseUrl: '',
            permissions: [],
            version: '1.0.0',
            type: 'app',
            integrationId: '123',
            active: true,
        });

        await getAppInformation().then((appInformation) => {
            expect(appInformation).toEqual(expect.objectContaining({
                name: 'jestapp',
                version: '1.0.0',
                type: 'app',
            }));
        });
    });

    it.skip('should return user information', async () => {
        Shopware.State.commit('extensions/addExtension', {
            name: 'jestapp',
            baseUrl: '',
            permissions: {
                read: [
                    'user',
                ],
            },
            version: '1.0.0',
            type: 'app',
            integrationId: '123',
            active: true,
        });

        Shopware.State.commit('setCurrentUser', {
            aclRoles: [],
            active: true,
            admin: true,
            email: 'john.doe@test.com',
            firstName: 'John',
            id: '123',
            lastName: 'Doe',
            localeId: 'lOcAlEiD',
            title: 'Dr.',
            type: 'user',
            username: 'john.doe',
        });

        await getUserInformation().then((userInformation) => {
            expect(userInformation).toEqual(expect.objectContaining({
                aclRoles: expect.any(Array),
                active: true,
                admin: true,
                email: 'john.doe@test.com',
                firstName: 'John',
                id: '123',
                lastName: 'Doe',
                localeId: 'lOcAlEiD',
                title: 'Dr.',
                type: 'user',
                username: 'john.doe',
            }));
        });
    });

    it.skip('should not return user information when permissions arent existing', async () => {
        Shopware.State.commit('extensions/addExtension', {
            name: 'jestapp',
            baseUrl: '',
            permissions: [],
            version: '1.0.0',
            type: 'app',
            integrationId: '123',
            active: true,
        });

        Shopware.State.commit('setCurrentUser', {
            aclRoles: [],
            active: true,
            admin: true,
            email: 'john.doe@test.com',
            firstName: 'John',
            id: '123',
            lastName: 'Doe',
            localeId: 'lOcAlEiD',
            title: 'Dr.',
            type: 'user',
            username: 'john.doe',
        });

        await expect(getUserInformation()).rejects.toThrow('Extension "jestapp" does not have the permission to read users');
    });

    it.skip('should not return user information when extension is not existing', async () => {
        Shopware.State.commit('setCurrentUser', {
            aclRoles: [],
            active: true,
            admin: true,
            email: 'john.doe@test.com',
            firstName: 'John',
            id: '123',
            lastName: 'Doe',
            localeId: 'lOcAlEiD',
            title: 'Dr.',
            type: 'user',
            username: 'john.doe',
        });

        await expect(getUserInformation()).rejects.toThrow('Could not find a extension with the given event origin ""');
    });
});
