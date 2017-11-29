/* global Shopware */
import ModuleFactory from 'src/core/factory/module.factory';

const register = ModuleFactory.registerModule;

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

    it('should be possible to register a module with two components', () => {
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
            navigation: {
                root: {
                    'sw.foo.index': {
                        icon: 'box',
                        color: '#f00',
                        name: 'FooIndex'
                    }
                }
            }
        });

        expect(module.navigation).to.be.an('object');
        expect(module.navigation.root).to.be.an('object');
        expect(module.navigation.root['sw.foo.index']).to.be.an('object');
        expect(module.navigation.root['sw.foo.index'].name).is.equals('FooIndex');
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
});
