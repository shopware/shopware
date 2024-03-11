import createHttpClient from 'src/core/factory/http.factory';
import createLoginService from 'src/core/service/login.service';
import StoreApiService from 'src/core/service/api/store.api.service';
import ShopwareExtensionService from 'src/module/sw-extension/service/shopware-extension.service';
import ExtensionStoreActionService from 'src/module/sw-extension/service/extension-store-action.service';
import AppModulesService from 'src/core/service/api/app-modules.service';
import 'src/module/sw-extension/service';
import initState from 'src/module/sw-extension/store';
import appModulesFixtures from '../../../app/service/_mocks/testApps.json';

jest.mock('src/module/sw-extension/service/extension-store-action.service');
jest.mock('src/core/service/api/app-modules.service');

const httpClient = createHttpClient(Shopware.Context.api);
Shopware.Application.getContainer('init').httpClient = httpClient;
Shopware.Service().register('loginService', () => {
    return createLoginService(httpClient, Shopware.Context.api);
});

Shopware.Service().register('storeService', () => {
    return new StoreApiService(httpClient, Shopware.Service('loginService'));
});

Shopware.Service().register('shopwareDiscountCampaignService', () => {
    return { isDiscountCampaignActive: jest.fn(() => true) };
});

/**
 * @package services-settings
 */
describe('src/module/sw-extension/service/shopware-extension.service', () => {
    let shopwareExtensionService;

    beforeAll(() => {
        shopwareExtensionService = Shopware.Service('shopwareExtensionService');

        initState(Shopware);

        if (Shopware.State.get('extensionEntryRoutes')) {
            Shopware.State.unregisterModule('extensionEntryRoutes');
        }
        Shopware.State.registerModule('extensionEntryRoutes', {
            namespaced: true,
            state: {
                routes: {
                    ExamplePlugin: {
                        route: 'test.foo',
                    },
                },
            },
        });
    });

    describe('it delegates lifecycle methods', () => {
        const mockedExtensionStoreActionService = new ExtensionStoreActionService(httpClient, Shopware.Service('loginService'));
        mockedExtensionStoreActionService.getMyExtensions.mockImplementation(() => {
            return ['new extensions'];
        });

        const mockedModuleService = new AppModulesService(httpClient, Shopware.Service('loginService'));
        mockedModuleService.fetchAppModules.mockImplementation(() => {
            return ['new app modules'];
        });

        const mockedShopwareExtensionService = new ShopwareExtensionService(
            mockedModuleService,
            mockedExtensionStoreActionService,
            Shopware.Service('shopwareDiscountCampaignService'),
            Shopware.Service('storeService'),
        );

        function expectUpdateExtensionDataCalled() {
            expect(mockedExtensionStoreActionService.refresh).toHaveBeenCalledTimes(1);
            expect(mockedExtensionStoreActionService.getMyExtensions).toHaveBeenCalledTimes(1);

            expect(Shopware.State.get('shopwareExtensions').myExtensions.data)
                .toEqual(['new extensions']);
            expect(Shopware.State.get('shopwareExtensions').myExtensions.loading)
                .toBe(false);

            expectUpdateModulesCalled();
        }

        function expectUpdateModulesCalled() {
            expect(mockedModuleService.fetchAppModules).toHaveBeenCalledTimes(1);

            expect(Shopware.State.get('shopwareApps').apps).toEqual(['new app modules']);
        }

        beforeEach(() => {
            Shopware.State.commit('shopwareExtensions/myExtensions', []);
            Shopware.State.commit('shopwareApps/setApps', []);
        });

        it.each([
            ['installExtension', ['someExtension', 'app']],
            ['updateExtension', ['someExtension', 'app', true]],
            ['uninstallExtension', ['someExtension', 'app', true]],
            ['removeExtension', ['someExtension', 'app']],
        ])('delegates %s correctly', async (lifecycleMethod, parameters) => {
            await mockedShopwareExtensionService[lifecycleMethod](...parameters);

            expect(mockedExtensionStoreActionService[lifecycleMethod]).toHaveBeenCalledTimes(1);
            expect(mockedExtensionStoreActionService[lifecycleMethod]).toHaveBeenCalledWith(...parameters);

            expectUpdateExtensionDataCalled();
        });

        it('delegates cancelLicense correctly', async () => {
            await mockedShopwareExtensionService.cancelLicense(5);

            expect(mockedExtensionStoreActionService.cancelLicense).toHaveBeenCalledTimes(1);
            expect(mockedExtensionStoreActionService.cancelLicense).toHaveBeenCalledWith(5);
        });

        it.each([
            ['activateExtension'],
            ['deactivateExtension'],
        ])('delegates %s correctly', async (lifecycleMethod) => {
            await mockedShopwareExtensionService[lifecycleMethod]('someExtension', 'app');

            expect(mockedExtensionStoreActionService[lifecycleMethod]).toHaveBeenCalledTimes(1);
            expect(mockedExtensionStoreActionService[lifecycleMethod]).toHaveBeenCalledWith('someExtension', 'app');

            expectUpdateModulesCalled();
        });
    });

    describe('checkLogin', () => {
        const checkLoginSpy = jest.spyOn(Shopware.Service('storeService'), 'checkLogin');

        beforeEach(() => {
            Shopware.State.commit('shopwareExtensions/setUserInfo', true);
        });

        it.each([
            [{ userInfo: { email: 'user@shopware.com' } }],
            [{ userInfo: null }],
        ])('sets login status depending on checkLogin response', async (loginResponse) => {
            checkLoginSpy.mockImplementationOnce(() => loginResponse);

            await shopwareExtensionService.checkLogin();

            expect(Shopware.State.get('shopwareExtensions').userInfo).toStrictEqual(loginResponse.userInfo);
        });

        it('sets login status to false if checkLogin request fails', async () => {
            checkLoginSpy.mockImplementationOnce(() => {
                throw new Error('something went wrong');
            });

            await shopwareExtensionService.checkLogin();

            expect(Shopware.State.get('shopwareExtensions').loginStatus).toBe(false);
            expect(Shopware.State.get('shopwareExtensions').userInfo).toBeNull();
        });
    });

    describe('isVariantDiscounted', () => {
        it('returns true if price is discounted and campaign is active', async () => {
            const variant = {
                netPrice: 100,
                discountCampaign: {
                    discountedPrice: 80,
                },
            };

            expect(shopwareExtensionService.isVariantDiscounted(variant)).toBe(true);
        });

        it('returns false if price is discounted but campaign is not active', async () => {
            const variant = {
                netPrice: 100,
                discountCampaign: {
                    discountedPrice: 80,
                },
            };

            Shopware.Service('shopwareDiscountCampaignService')
                .isDiscountCampaignActive
                .mockImplementationOnce(() => false);

            expect(shopwareExtensionService.isVariantDiscounted(variant)).toBe(false);
        });

        it('returns false if variant is falsy', async () => {
            expect(shopwareExtensionService.isVariantDiscounted(null)).toBe(false);
        });

        it('returns false if variant has no discountCampaign', async () => {
            expect(shopwareExtensionService.isVariantDiscounted({})).toBe(false);
        });

        it('returns false if discounted price is net price', async () => {
            expect(shopwareExtensionService.isVariantDiscounted({
                netPrice: 100,
                discountCampaign: {
                    discountedPrice: 100,
                },
            })).toBe(false);
        });
    });

    describe('orderVariantsByRecommendation', () => {
        it('orders variants by recommendation and discounting', async () => {
            const variants = [
                { netPrice: 100, discountCampaign: { netPrice: 100 }, type: 'rent' },
                { netPrice: 100, discountCampaign: { netPrice: 80 }, type: 'test' },
                { netPrice: 100, discountCampaign: { netPrice: 100 }, type: 'test' },
                { netPrice: 100, discountCampaign: { netPrice: 100 }, type: 'buy' },
                { netPrice: 100, discountCampaign: { netPrice: 10 }, type: 'rent' },
            ];

            shopwareExtensionService.orderVariantsByRecommendation(variants)
                .forEach((current, currentIndex, orderedVariants) => {
                    const isCurrentDiscounted = shopwareExtensionService.isVariantDiscounted(current);
                    const currentRecommendation = shopwareExtensionService.mapVariantToRecommendation(current);

                    orderedVariants.forEach((comparator, comparatorIndex) => {
                        const isComparatorDiscounted = shopwareExtensionService.isVariantDiscounted(comparator);
                        const comparatorRecommendation = shopwareExtensionService.mapVariantToRecommendation(comparator);

                        if (isCurrentDiscounted !== !isComparatorDiscounted) {
                            // discounted index is always smaller than undiscounted
                            if (isCurrentDiscounted && !isComparatorDiscounted) {
                                // eslint-disable-next-line jest/no-conditional-expect
                                expect(currentIndex).toBeLessThan(comparatorIndex);
                            }

                            if (!isCurrentDiscounted && isComparatorDiscounted) {
                                // eslint-disable-next-line jest/no-conditional-expect
                                expect(currentIndex).toBeGreaterThan(comparatorIndex);
                            }
                        } else {
                            // variants are ordered by recommendation
                            if (currentRecommendation < comparatorRecommendation) {
                                // eslint-disable-next-line jest/no-conditional-expect
                                expect(currentIndex).toBeLessThan(comparatorIndex);
                            }

                            if (currentIndex > comparatorRecommendation) {
                                // eslint-disable-next-line jest/no-conditional-expect
                                expect(currentIndex).toBeGreaterThan(comparatorIndex);
                            }
                        }
                    });
                });
        });
    });

    describe('getPriceFromVariant', () => {
        it('returns discounted price if variant is discounted', async () => {
            expect(shopwareExtensionService.getPriceFromVariant({
                netPrice: 100,
                discountCampaign: {
                    discountedPrice: 80,
                },
            })).toBe(80);
        });

        it('returns net price if variant is not discounted', async () => {
            Shopware.Service('shopwareDiscountCampaignService').isDiscountCampaignActive
                .mockImplementationOnce(() => false);

            expect(shopwareExtensionService.getPriceFromVariant({
                netPrice: 100,
                discountCampaign: {
                    discountedPrice: 80,
                },
            })).toBe(100);
        });
    });

    describe('mapVariantToRecommendation', () => {
        it.each([
            ['free', 0],
            ['rent', 1],
            ['buy', 2],
            ['test', 3],
        ])('maps variant %s to position %d', (type, expectedRecommendation) => {
            expect(shopwareExtensionService.mapVariantToRecommendation({ type })).toBe(expectedRecommendation);
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
                    data: [themeId],
                },
            });

            const openLink = await shopwareExtensionService.getOpenLink({
                isTheme: true,
                type: shopwareExtensionService.EXTENSION_TYPES.APP,
                name: 'SwagExampleApp',
            });

            expect(openLink).toEqual({
                name: 'sw.theme.manager.detail',
                params: { id: themeId },
            });
        });

        it('returns valid open link for app with main module', async () => {
            Shopware.State.commit(
                'shopwareApps/setApps',
                appModulesFixtures,
            );

            expect(await shopwareExtensionService.getOpenLink({
                isTheme: false,
                type: shopwareExtensionService.EXTENSION_TYPES.APP,
                name: 'testAppA',
            })).toEqual({
                name: 'sw.extension.module',
                params: {
                    appName: 'testAppA',
                },
            });
        });

        it('returns no open link for app without main module', async () => {
            Shopware.State.commit(
                'shopwareApps/setApps',
                appModulesFixtures,
            );

            expect(await shopwareExtensionService.getOpenLink({
                isTheme: false,
                type: shopwareExtensionService.EXTENSION_TYPES.APP,
                name: 'testAppB',
            })).toBeNull();
        });

        it('returns no open link if app can not be found', async () => {
            Shopware.State.commit(
                'shopwareApps/setApps',
                appModulesFixtures,
            );

            expect(await shopwareExtensionService.getOpenLink({
                isTheme: false,
                type: shopwareExtensionService.EXTENSION_TYPES.APP,
                name: 'ThisAppDoesNotExist',
            })).toBeNull();
        });

        it('returns no open link for plugins not registered', async () => {
            expect(await shopwareExtensionService.getOpenLink({
                isTheme: false,
                type: shopwareExtensionService.EXTENSION_TYPES.PLUGIN,
                name: 'SwagNoModule',
            })).toBeNull();
        });

        it('returns route for plugins registered', async () => {
            expect(await shopwareExtensionService.getOpenLink({
                isTheme: false,
                type: shopwareExtensionService.EXTENSION_TYPES.PLUGIN,
                name: 'ExamplePlugin',
                active: true,
            })).toEqual({
                label: null,
                name: 'test.foo',
            });
        });
    });
});
