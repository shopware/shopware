/* global Shopware */
const ModuleFactory = Shopware.Module;
const register = ModuleFactory.register;

// We're clearing the modules registry to register the same module multiple times throughout the test suite
beforeEach(() => {
    const modules = ModuleFactory.getModuleRegistry();
    modules.clear();
});

describe('core/factory/module.factory.js', () => {
    it('should not register a module when no unique identifier is specified', () => {
        const module = register('', {});

        expect(module).is.equal(false);
    });

    it('should not register a module with same name twice', () => {
        const moduleDefinition = {
            routes: {
                index: {
                    component: 'sw-foo-bar',
                    path: 'index'
                }
            }
        };

        const moduleOne = register('sw-foo', moduleDefinition);
        const moduleTwo = register('sw-foo', moduleDefinition);

        expect(moduleOne).to.be.an('object');
        expect(moduleTwo).is.equal(false);
    });

    it('should not register a module when the unique identifier does not have a namespace', () => {
        const module = register('foo', {
            routes: {
                index: {
                    component: 'sw-foo-bar',
                    path: 'index'
                }
            }
        });

        expect(module).is.equal(false);
    });

    it('should not register a module without a route definition', () => {
        const module = register('sw-foo', {
            name: 'Test'
        });

        expect(module).is.equal(false);
    });

    it('should not register a module without a component in the route definition', () => {
        const module = register('sw-foo', {
            routes: {
                index: {
                    path: 'index'
                }
            }
        });

        expect(module).is.equal(false);
    });

    it('should be possible to register a module with a valid route definition', () => {
        const module = register('sw-foo', {
            routes: {
                index: {
                    path: 'index',
                    component: 'sw-foo-bar-index'
                }
            }
        });

        expect(module).to.be.an('object');
        expect(module.routes).to.be.a('map');
        expect(module.manifest).to.be.an('object');
        expect(module.type).to.be.a('string');
        expect(module.type).is.equal('plugin');
        expect(module.navigation).is.equal(undefined);
    });

    it('should be possible to register a module with two components per route', () => {
        const module = register('sw-foo', {
            routes: {
                index: {
                    path: 'index',
                    components: {
                        default: 'sw-foo-bar-index',
                        test: 'sw-foo-test'
                    }
                }
            }
        });

        const route = module.routes.get('sw.foo.index');

        expect(module.routes.has('sw.foo.index')).is.equal(true);
        expect(module).to.be.an('object');
        expect(module.routes).to.be.a('map');
        expect(module.manifest).to.be.an('object');
        expect(module.type).to.be.a('string');
        expect(module.type).is.equal('plugin');
        expect(module.navigation).is.equal(undefined);

        expect(route.components.test).to.be.a('string');
        expect(route.components.test).is.equal('sw-foo-test');
        expect(route.components.default).to.be.a('string');
        expect(route.components.default).is.equal('sw-foo-bar-index');
    });

    it('should be possible to register a module with a navigation entry', () => {
        const module = register('sw-foo', {
            routes: {
                index: {
                    path: 'index',
                    component: 'sw-foo-bar-index'
                }
            },
            navigation: [{
                icon: 'box',
                color: '#f00',
                label: 'FooIndex',
                path: 'sw.foo.index'
            }]
        });

        expect(module.navigation).to.be.an('array');
        const navigationEntry = module.navigation[0];
        expect(navigationEntry).to.be.an('object');
    });

    it('should be possible to register a module with multiple navigation entries', () => {
        const module = register('sw-foo', {
            routes: {
                index: {
                    path: 'index',
                    component: 'sw-foo-bar-index'
                }
            },
            navigation: [{
                id: 'sw.foo.index',
                icon: 'box',
                color: '#f00',
                label: 'FooIndex'
            }, {
                link: 'http://de.shopware.com',
                label: 'ExternalLink',
                parent: 'sw.foo.index'
            }, {
                label: 'InvalidEntry'
            }]
        });

        expect(module.navigation).to.be.an('array');
        expect(module.navigation.length).is.equal(2);
        const routerNavigationEntry = module.navigation[0];
        const externalLinkNavigation = module.navigation[1];
        expect(routerNavigationEntry).to.be.an('object');
        expect(routerNavigationEntry.label).is.equal('FooIndex');

        expect(externalLinkNavigation).to.be.an('object');
        expect(externalLinkNavigation.link).is.equal('http://de.shopware.com');
    });

    it('should be possible to get all registered modules', () => {
        register('sw-foo', {
            routes: {
                index: {
                    path: 'index',
                    component: 'sw-foo-bar-index'
                }
            }
        });

        const modules = ModuleFactory.getModuleRegistry();

        expect(modules.size).is.equal(1);
        expect(modules.has('sw-foo')).is.equal(true);
    });

    it('should be possible to get all registered module routes', () => {
        register('sw-foo', {
            routes: {
                index: {
                    path: 'index',
                    component: 'sw-foo-bar-index'
                }
            }
        });

        const routes = ModuleFactory.getModuleRoutes();

        expect(routes).to.be.an('array');
        expect(routes[0]).to.be.an('object');
        expect(routes[0].name).equals('sw.foo.index');
        expect(routes[0].type).equals('plugin');
    });

    it('should be possible to get an module by its entity name', () => {
        const registeredModule = register('sw-foo', {
            entity: 'foo',

            routes: {
                index: {
                    path: 'index',
                    component: 'sw-foo-bar-index'
                }
            }
        });

        const module = ModuleFactory.getModuleByEntityName('foo');

        expect(module).to.be.an('object');
        expect(module).equals(registeredModule);
        expect(module.manifest.entity).equals('foo');
    });

    it('should return first module when entity isn`t unique', () => {
        const registeredModule = register('sw-foo', {
            entity: 'foo',

            routes: {
                index: {
                    path: 'index',
                    component: 'sw-foo-bar-index'
                }
            }
        });

        register('sw-another-foo', {
            entity: 'foo',

            routes: {
                index: {
                    path: 'index',
                    component: 'sw-another-foo-bar-index'
                }
            }
        });

        const module = ModuleFactory.getModuleByEntityName('foo');

        expect(module).to.be.an('object');
        expect(module).equals(registeredModule);
        expect(module.manifest.entity).equals('foo');
    });

    it('should return undefined if module with that entity is not found', () => {
        register('sw-foo', {
            entity: 'foo',

            routes: {
                index: {
                    path: 'index',
                    component: 'sw-foo-bar-index'
                }
            }
        });
        const module = ModuleFactory.getModuleByEntityName('bar');

        expect(module).to.be.an('undefined');
    });
});
