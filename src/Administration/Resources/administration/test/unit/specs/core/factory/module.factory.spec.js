/* global Shopware */
const ModuleFactory = Shopware.Module;
const register = ModuleFactory.register;

// We're clearing the modules registry to register the same module multiple times throughout the test suite
beforeEach(() => {
    const modules = ModuleFactory.getRegistry();
    modules.clear();
});

describe('core/factory/module.factory.js', () => {
    it('should not register a module when no unique identifier is specified', () => {
        const module = register({
            name: 'foobar'
        });

        expect(module).is.equal(false);
    });

    it('should not register a module when the unique identifier does not have a namespace', () => {
        const module = register({
            id: 'foobar'
        });

        expect(module).is.equal(false);
    });

    it('should not register a module without a route definition', () => {
        const module = register({
            id: 'foo.bar'
        });

        expect(module).is.equal(false);
    });

    it('should not register a module without a component in the route definition', () => {
        const module = register({
            id: 'foo.bar',
            routes: {
                index: {
                    path: 'index'
                }
            }
        });

        expect(module).is.equal(false);
    });

    it('should not be possible to register a module with a component with does not have a name', () => {
        const module = register({
            id: 'foo.bar',
            routes: {
                index: {
                    path: 'index',
                    component: {
                        template: '<div class="test"></div>'
                    }
                }
            }
        });

        expect(module).is.equal(false);
    });

    it('should be possible to register a module with a valid route definition', () => {
        const module = register({
            id: 'foo.bar',
            routes: {
                index: {
                    path: 'index',
                    component: {
                        name: 'foo-bar-index',
                        template: '<div class="test"></div>'
                    }
                }
            }
        });

        expect(module).to.be.an('object');
        expect(module.routes).to.be.a('map');
        expect(module.manifest).to.be.an('object');
        expect(module.type).to.be.a('string');
        expect(module.type).is.equals('plugin');
        expect(module.navigation).is.equals(undefined);
    });

    it('should be possible to register a module with two components', () => {
        const module = register({
            id: 'foo.bar',
            routes: {
                index: {
                    path: 'index',
                    components: {
                        default: {
                            name: 'foo-bar-index',
                            template: '<div class="test"></div>'
                        },
                        test: {
                            template: '<div class="test"></div>'
                        }
                    }
                }
            }
        });

        const route = module.routes.get('foo.bar.index');

        expect(module.routes.has('foo.bar.index')).is.equals(true);
        expect(module).to.be.an('object');
        expect(module.routes).to.be.a('map');
        expect(module.manifest).to.be.an('object');
        expect(module.type).to.be.a('string');
        expect(module.type).is.equals('plugin');
        expect(module.navigation).is.equals(undefined);

        expect(route.components.test).is.equals(undefined);
        expect(route.components.default).to.be.a('string');
        expect(route.components.default).is.equals('foo-bar-index');
    });

    it('should be possible to register a module with a navigation entry', () => {
        const module = register({
            id: 'foo.bar',
            routes: {
                index: {
                    path: 'index',
                    component: {
                        name: 'foo-bar-index',
                        template: '<div class="test"></div>'
                    }
                }
            },
            navigation: {
                root: {
                    'foo.bar.index': {
                        icon: 'box',
                        color: '#f00',
                        name: 'FooBar'
                    }
                }
            }
        });

        expect(module.navigation).to.be.an('object');
        expect(module.navigation.root).to.be.an('object');
        expect(module.navigation.root['foo.bar.index']).to.be.an('object');
        expect(module.navigation.root['foo.bar.index'].name).is.equals('FooBar');
    });

    it('should be possible to get all registered modules', () => {
        register({
            id: 'foo.bar',
            routes: {
                index: {
                    path: 'index',
                    component: {
                        name: 'foo-bar-index',
                        template: '<div class="test"></div>'
                    }
                }
            }
        });

        const modules = ModuleFactory.getRegistry();

        expect(modules.size).to.equal(1);
        expect(modules.has('foo.bar')).to.equal(true);
    });

    it('should be possible to get all registerd module routes', () => {
        register({
            id: 'foo.bar',
            routes: {
                index: {
                    path: 'index',
                    component: {
                        name: 'foo-bar-index',
                        template: '<div class="test"></div>'
                    }
                }
            }
        });

        const routes = ModuleFactory.getRoutes();

        expect(routes).to.be.an('array');
        expect(routes[0]).to.be.an('object');
        expect(routes[0].name).equals('foo.bar.index');
        expect(routes[0].type).equals('plugin');
        expect(routes[0].type).equals('plugin');
    });
});
