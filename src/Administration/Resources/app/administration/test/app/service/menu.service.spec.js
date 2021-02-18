import createMenuService from 'src/app/service/menu.service';

// we are not interested in testing routes here but it is required to register modules
const dummyRoute = { path: '/', component: 'sw-index' };

describe('src/app/service/menu.service', () => {
    const menuService = createMenuService(Shopware.Module);

    function clearModules() {
        Shopware.Module.getModuleRegistry().clear();
    }

    beforeEach(() => {
        clearModules();
    });

    it('returns an unordered list of all navigation entries', () => {
        getTestModules().forEach((module) => {
            Shopware.Module.register(module.name, module);
        });

        const navigationEntries = menuService.getNavigationFromModules();

        expect(navigationEntries).toHaveLength(5);
        expect(navigationEntries).toEqual(expect.arrayContaining([
            expect.objectContaining({ id: 'sw.second.top.level' }),
            expect.objectContaining({ id: 'sw.second.level.last' }),
            expect.objectContaining({ id: 'sw.second.level.first' }),
            expect.objectContaining({ id: 'sw.second.level.second' }),
            expect.objectContaining({ id: 'sw.first.top.level' })
        ]));
    });

    it('ignores modules with empty navigation', () => {
        Shopware.Module.register('empty-navigation', {
            name: 'empty-navigation',
            routes: { dummyRoute },
            navigation: []
        });

        expect(menuService.getNavigationFromModules()).toHaveLength(0);
    });

    it('ignores modules if navigation is null', () => {
        Shopware.Module.register('null-navigation', {
            name: 'null-navigation',
            routes: { dummyRoute },
            navigation: null
        });

        expect(menuService.getNavigationFromModules()).toHaveLength(0);
    });

    describe('deprecated functionality', () => {
        it('returns sorted tree when getMainMenu is called', () => {
            getTestModules().forEach((module) => {
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
                })
            ]);
        });
    });
});


function getTestModules() {
    return [{
        name: 'first-module',
        routes: { dummyRoute },
        navigation: [{
            id: 'sw.second.top.level',
            label: 'top level entry',
            position: 20
        }, {
            id: 'sw.second.level.last',
            label: 'second top level entry',
            position: 40,
            parent: 'sw.second.top.level'
        }, {
            id: 'sw.second.level.first',
            label: 'second top level entry',
            position: 10,
            parent: 'sw.second.top.level'
        }]
    }, {
        name: 'second-module',
        routes: { dummyRoute },
        navigation: [{
            id: 'sw.first.top.level',
            label: 'second top level entry'
        }, {
            id: 'sw.second.level.second',
            label: 'second top level entry',
            position: 20,
            parent: 'sw.second.top.level'
        }]
    }, {
        name: 'no-navigation',
        routes: { dummyRoute }
    }];
}
