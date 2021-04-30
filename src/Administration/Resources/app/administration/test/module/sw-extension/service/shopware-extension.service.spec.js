import createHttpClient from 'src/core/factory/http.factory';
import createLoginService from 'src/core/service/login.service';
import 'src/module/sw-extension/service';
import 'src/module/sw-extension/';
import 'src/module/sw-extension/store';
import appModulesFixtures from '../../../app/service/_mocks/testApps.json';

const httpClient = createHttpClient(Shopware.Context.api);
Shopware.Application.getContainer('init').httpClient = httpClient;
Shopware.Service().register('loginService', () => {
    return createLoginService(httpClient, Shopware.Context.api);
});

describe('shopware-extension.service', () => {
    let shopwareExtensionService;

    beforeAll(() => {
        shopwareExtensionService = Shopware.Service('shopwareExtensionService');

        Shopware.State.registerModule('extensionEntryRoutes', {
            namespaced: true,
            state: {
                routes: {
                    ExamplePlugin: {
                        route: 'test.foo'
                    }
                }
            }
        });
    });

    describe('canBeOpened', () => {
        it('cant always open themes', async () => {
            const responses = global.repositoryFactoryMock.responses;
            responses.addResponse({
                method: 'Post',
                url: '/search-ids/theme',
                status: 200,
                response: {
                    data: ['random-id']
                }
            });

            expect(await shopwareExtensionService.canBeOpened({
                isTheme: true
            })).toBe(true);
        });

        it('cant open theme when it has not been activated once', async () => {
            const responses = global.repositoryFactoryMock.responses;
            responses.addResponse({
                method: 'Post',
                url: '/search-ids/theme',
                status: 200,
                response: {
                    data: []
                }
            });

            const canBeOpened = await shopwareExtensionService.canBeOpened({
                isTheme: true
            });

            expect(canBeOpened).toBe(false);
        });

        it('can not open plugins right now', async () => {
            expect(await shopwareExtensionService.canBeOpened({
                isTheme: false,
                type: shopwareExtensionService.EXTENSION_TYPES.PLUGIN
            })).toBe(false);
        });

        it('can open apps with main module', async () => {
            Shopware.State.commit(
                'shopwareApps/setApps',
                appModulesFixtures
            );

            expect(await shopwareExtensionService.canBeOpened({
                isTheme: false,
                type: shopwareExtensionService.EXTENSION_TYPES.APP,
                name: 'testAppA'
            })).toBe(true);
        });

        it('cant not open apps without main modules', async () => {
            Shopware.State.commit(
                'shopwareApps/setApps',
                appModulesFixtures
            );

            expect(await shopwareExtensionService.canBeOpened({
                isTheme: false,
                type: shopwareExtensionService.EXTENSION_TYPES.APP,
                name: 'testAppB'
            })).toBe(false);
        });
    });

    describe('getOpenLink', () => {
        it('returns always a open link for theme', async () => {
            const themeId = Shopware.Utils.createId();

            const responses = global.repositoryFactoryMock.responses;
            responses.addResponse({
                method: 'Post',
                url: '/search-ids/theme',
                status: 200,
                response: {
                    data: [themeId]
                }
            });

            const openLink = await shopwareExtensionService.getOpenLink({
                isTheme: true,
                type: shopwareExtensionService.EXTENSION_TYPES.APP,
                name: 'SwagExampleApp'
            });

            expect(openLink).toEqual({
                name: 'sw.theme.manager.detail',
                params: { id: themeId }
            });
        });

        it('returns valid open link for app with main module', async () => {
            Shopware.State.commit(
                'shopwareApps/setApps',
                appModulesFixtures
            );

            expect(await shopwareExtensionService.getOpenLink({
                isTheme: false,
                type: shopwareExtensionService.EXTENSION_TYPES.APP,
                name: 'testAppA'
            })).toEqual({
                name: 'sw.my.apps.index',
                params: {
                    appName: 'testAppA'
                }
            });
        });

        test('returns no open link for app without main module', async () => {
            Shopware.State.commit(
                'shopwareApps/setApps',
                appModulesFixtures
            );

            expect(await shopwareExtensionService.getOpenLink({
                isTheme: false,
                type: shopwareExtensionService.EXTENSION_TYPES.APP,
                name: 'testAppB'
            })).toBeNull();
        });

        it('returns no open link if app can not be found', async () => {
            Shopware.State.commit(
                'shopwareApps/setApps',
                appModulesFixtures
            );

            expect(await shopwareExtensionService.getOpenLink({
                isTheme: false,
                type: shopwareExtensionService.EXTENSION_TYPES.APP,
                name: 'ThisAppDoesNotExist'
            })).toBeNull();
        });

        it('returns no open link for plugins not registered', async () => {
            expect(await shopwareExtensionService.getOpenLink({
                isTheme: false,
                type: shopwareExtensionService.EXTENSION_TYPES.PLUGIN,
                name: 'SwagNoModule'
            })).toBeNull();
        });

        it('returns route for plugins registered', async () => {
            expect(await shopwareExtensionService.getOpenLink({
                isTheme: false,
                type: shopwareExtensionService.EXTENSION_TYPES.PLUGIN,
                name: 'ExamplePlugin',
                active: true
            })).toEqual({
                label: null,
                name: 'test.foo'
            });
        });
    });
});
