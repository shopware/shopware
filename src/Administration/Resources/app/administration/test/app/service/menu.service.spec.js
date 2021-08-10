import createMenuService from 'src/app/service/menu.service';

/** fixtures */
import adminModules from './_mocks/adminModules.json';
import testApps from './_mocks/testApps.json';

describe('src/app/service/menu.service', () => {
    const menuService = createMenuService(Shopware.Module);

    function clearModules() {
        Shopware.Module.getModuleRegistry().clear();
    }

    beforeEach(() => {
        clearModules();
    });

    describe('adminModuleNavigation', () => {
        it('returns an unordered list of all navigation entries', () => {
            adminModules.forEach((module) => {
                Shopware.Module.register(module.name, module);
            });

            const navigationEntries = menuService.getNavigationFromAdminModules();

            expect(navigationEntries).toHaveLength(9);
            expect(navigationEntries).toEqual(expect.arrayContaining([
                expect.objectContaining({ id: 'sw.second.top.level' }),
                expect.objectContaining({ id: 'sw.second.level.last' }),
                expect.objectContaining({ id: 'sw.second.level.first' }),
                expect.objectContaining({ id: 'sw.second.level.second' }),
                expect.objectContaining({ id: 'sw.first.top.level' }),
                expect.objectContaining({ id: 'sw-my-apps' }),
                expect.objectContaining({ id: 'children.with.privilege' }),
                expect.objectContaining({ id: 'children.with.privilege.first' }),
                expect.objectContaining({ id: 'children.with.privilege.second' })
            ]));
        });

        it('ignores modules with empty navigation', () => {
            Shopware.Module.register('empty-navigation', {
                name: 'empty-navigation',
                routes: { index: { path: '/', component: 'sw-index' } },
                navigation: []
            });

            expect(menuService.getNavigationFromAdminModules()).toHaveLength(0);
        });

        it('ignores modules if navigation is null', () => {
            Shopware.Module.register('null-navigation', {
                name: 'null-navigation',
                routes: { index: { path: '/', component: 'sw-index' } },
                navigation: null
            });

            expect(menuService.getNavigationFromAdminModules()).toHaveLength(0);
        });
    });

    describe('appModuleNavigation', () => {
        it('returns modules from apps', () => {
            const navigation = menuService.getNavigationFromApps(testApps);

            expect(navigation).toHaveLength(5);
            expect(navigation).toEqual([
                expect.objectContaining({
                    id: 'app-testAppA-standardModule',
                    path: 'sw.my.apps.index',
                    parent: 'sw-catalogue',
                    params: {
                        appName: 'testAppA',
                        moduleName: 'standardModule'
                    }
                }),
                expect.objectContaining({
                    id: 'app-testAppA-noPosition',
                    path: 'sw.my.apps.index',
                    parent: 'sw.second.top.level',
                    params: {
                        appName: 'testAppA',
                        moduleName: 'noPosition'
                    }
                }), expect.objectContaining({
                    id: 'app-testAppA-noParent',
                    path: 'sw.my.apps.index',
                    parent: 'sw-my-apps',
                    params: {
                        appName: 'testAppA',
                        moduleName: 'noParent'
                    },
                    position: 50
                }), expect.objectContaining({
                    id: 'app-testAppB-default',
                    path: 'sw.my.apps.index',
                    parent: 'app-testAppB-structure',
                    params: {
                        appName: 'testAppB',
                        moduleName: 'default'
                    },
                    position: 50
                }), expect.objectContaining({
                    id: 'app-testAppB-structure',
                    parent: 'sw.first.top.level',
                    position: 50
                })
            ]);
        });

        it('respects the current locale for apps', () => {
            Shopware.Context.app.fallbackLocale = 'en-GB';
            Shopware.State.get('session').currentLocale = 'de-DE';

            const navigation = menuService.getNavigationFromApps(testApps);
            expect(navigation).toEqual([
                expect.objectContaining({
                    id: 'app-testAppA-standardModule',
                    label: {
                        translated: true,
                        label: 'test App A deutsch - Standardmodul'
                    }
                }), expect.objectContaining({
                    id: 'app-testAppA-noPosition',
                    label: {
                        translated: true,
                        label: 'test App A deutsch - Modul ohne Position'
                    }
                }), expect.objectContaining({
                    id: 'app-testAppA-noParent',
                    label: {
                        translated: true,
                        label: 'test App A deutsch - Modul ohne Parent'
                    }
                }), expect.objectContaining({
                    id: 'app-testAppB-default',
                    label: {
                        translated: true,
                        label: 'test App B deutsch - Standard Modul'
                    }
                }), expect.objectContaining({
                    id: 'app-testAppB-structure',
                    label: {
                        translated: true,
                        label: 'test App B deutsch - Sruktur Modul'
                    }
                })
            ]);
        });

        it('uses fallback locale for apps if current locale is not translated', () => {
            Shopware.Context.app.fallbackLocale = 'en-GB';
            Shopware.State.get('session').currentLocale = 'ru-RU';

            const navigation = menuService.getNavigationFromApps(testApps);
            expect(navigation).toEqual([
                expect.objectContaining({
                    id: 'app-testAppA-standardModule',
                    label: {
                        translated: true,
                        label: 'test App A english - Standard module'
                    }
                }), expect.objectContaining({
                    id: 'app-testAppA-noPosition',
                    label: {
                        translated: true,
                        label: 'test App A english - Module without position'
                    }
                }), expect.objectContaining({
                    id: 'app-testAppA-noParent',
                    label: {
                        translated: true,
                        label: 'test App A english - Module without parent'
                    }
                }), expect.objectContaining({
                    id: 'app-testAppB-default',
                    label: {
                        translated: true,
                        label: 'test App B english - Default module'
                    }
                }), expect.objectContaining({
                    id: 'app-testAppB-structure',
                    label: {
                        translated: true,
                        label: 'test App B english - Structure module'
                    }
                })
            ]);
        });
    });

    describe('deprecated functionality', () => {
        it('returns sorted tree when getMainMenu is called', () => {
            adminModules.forEach((module) => {
                Shopware.Module.register(module.name, module);
            });

            const navigationTree = menuService.getMainMenu();


            expect(navigationTree).toEqual([
                expect.objectContaining({
                    id: 'sw.first.top.level',
                    position: 1,
                    children: []
                }),
                expect.objectContaining({
                    id: 'sw.second.top.level',
                    position: 20,
                    children: [
                        expect.objectContaining({
                            id: 'sw.second.level.first',
                            position: 10
                        }),
                        expect.objectContaining({
                            id: 'sw.second.level.second',
                            position: 20
                        }),
                        expect.objectContaining({
                            id: 'sw.second.level.last',
                            position: 40
                        })
                    ]
                }),
                expect.objectContaining({
                    id: 'sw-my-apps',
                    position: 100
                }),
                expect.objectContaining({
                    id: 'children.with.privilege',
                    position: 150
                })
            ]);
        });
    });
});
