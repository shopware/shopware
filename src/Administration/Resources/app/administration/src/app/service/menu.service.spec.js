/**
 * @package admin
 */

import createMenuService from 'src/app/service/menu.service';

/** fixtures */
import adminModules from './_mocks/adminModules.json';
import testApps from './_mocks/testApps.json';

describe('src/app/service/menu.service', () => {
    const menuService = createMenuService(Shopware.Module);

    function clearModules() {
        Shopware.Module.getModuleRegistry().clear();
    }

    beforeEach(async () => {
        clearModules();
    });

    describe('adminModuleNavigation', () => {
        it('returns an unordered list of all navigation entries', async () => {
            adminModules.forEach((module) => {
                Shopware.Module.register(module.name, module);
            });

            const navigationEntries = menuService.getNavigationFromAdminModules();

            expect(navigationEntries).toHaveLength(11);
            expect(navigationEntries).toEqual(expect.arrayContaining([
                expect.objectContaining({ id: 'sw.second.top.level' }),
                expect.objectContaining({ id: 'sw.second.level.last' }),
                expect.objectContaining({ id: 'sw.second.level.first' }),
                expect.objectContaining({ id: 'sw.second.level.second' }),
                expect.objectContaining({ id: 'sw.first.top.level' }),
                expect.objectContaining({ id: 'children.with.privilege' }),
                expect.objectContaining({ id: 'children.with.privilege.first' }),
                expect.objectContaining({ id: 'children.with.privilege.second' })
            ]));
        });

        it('ignores modules with empty navigation', async () => {
            Shopware.Module.register('empty-navigation', {
                name: 'empty-navigation',
                routes: { index: { path: '/', component: 'sw-index' } },
                navigation: []
            });

            expect(menuService.getNavigationFromAdminModules()).toHaveLength(0);
        });

        it('ignores modules if navigation is null', async () => {
            Shopware.Module.register('null-navigation', {
                name: 'null-navigation',
                routes: { index: { path: '/', component: 'sw-index' } },
                navigation: null
            });

            expect(menuService.getNavigationFromAdminModules()).toHaveLength(0);
        });
    });

    describe('appModuleNavigation', () => {
        it('returns modules from apps', async () => {
            const navigation = menuService.getNavigationFromApps(testApps);

            expect(navigation).toHaveLength(4);
            expect(navigation).toEqual([
                expect.objectContaining({
                    id: 'app-testAppA-standardModule',
                    path: 'sw.extension.module',
                    parent: 'sw-catalogue',
                    params: {
                        appName: 'testAppA',
                        moduleName: 'standardModule'
                    }
                }),
                expect.objectContaining({
                    id: 'app-testAppA-noPosition',
                    path: 'sw.extension.module',
                    parent: 'sw.second.top.level',
                    params: {
                        appName: 'testAppA',
                        moduleName: 'noPosition'
                    }
                }), expect.objectContaining({
                    id: 'app-testAppB-default',
                    path: 'sw.extension.module',
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

        it('respects the current locale for apps', async () => {
            Shopware.Context.app.fallbackLocale = 'en-GB';
            Shopware.State.get('session').currentLocale = 'de-DE';

            const navigation = menuService.getNavigationFromApps(testApps);
            expect(navigation).toEqual([
                expect.objectContaining({
                    id: 'app-testAppA-standardModule',
                    label: {
                        translated: true,
                        label: 'Standardmodul'
                    }
                }), expect.objectContaining({
                    id: 'app-testAppA-noPosition',
                    label: {
                        translated: true,
                        label: 'Modul ohne Position'
                    }
                }), expect.objectContaining({
                    id: 'app-testAppB-default',
                    label: {
                        translated: true,
                        label: 'Standard Modul'
                    }
                }), expect.objectContaining({
                    id: 'app-testAppB-structure',
                    label: {
                        translated: true,
                        label: 'Struktur Modul'
                    }
                })
            ]);
        });

        it('uses fallback locale for apps if current locale is not translated', async () => {
            Shopware.Context.app.fallbackLocale = 'en-GB';
            Shopware.State.get('session').currentLocale = 'ru-RU';

            const navigation = menuService.getNavigationFromApps(testApps);
            expect(navigation).toEqual([
                expect.objectContaining({
                    id: 'app-testAppA-standardModule',
                    label: {
                        translated: true,
                        label: 'Standard module'
                    }
                }), expect.objectContaining({
                    id: 'app-testAppA-noPosition',
                    label: {
                        translated: true,
                        label: 'Module without position'
                    }
                }), expect.objectContaining({
                    id: 'app-testAppB-default',
                    label: {
                        translated: true,
                        label: 'Default module'
                    }
                }), expect.objectContaining({
                    id: 'app-testAppB-structure',
                    label: {
                        translated: true,
                        label: 'Structure module'
                    }
                })
            ]);
        });
    });
});
