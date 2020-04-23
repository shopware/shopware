const { Module, Application } = Shopware;
const ModuleFactory = Module;
const register = ModuleFactory.register;

// Disable developer hints in jest output
jest.spyOn(global.console, 'warn').mockImplementation(() => jest.fn());

// We're clearing the modules registry to register the same module multiple times throughout the test suite
beforeEach(() => {
    const modules = ModuleFactory.getModuleRegistry();
    modules.clear();
});

describe('core/factory/module.factory.js', () => {
    test(
        'should not register a module when no unique identifier is specified',
        () => {
            const module = register('', {});

            expect(module).toBe(false);
        }
    );

    test('should not register a module with same name twice', () => {
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

        expect(typeof moduleOne).toBe('object');
        expect(moduleTwo).toBe(false);
    });

    test(
        'should not register a module when the unique identifier does not have a namespace',
        () => {
            const module = register('foo', {
                routes: {
                    index: {
                        component: 'sw-foo-bar',
                        path: 'index'
                    }
                }
            });

            expect(module).toBe(false);
        }
    );

    test('should not register a module without a route definition', () => {
        const module = register('sw-foo', {
            name: 'Test'
        });

        expect(module).toBe(false);
    });

    xit('should not register a module without a component in the route definition', () => {
        const module = register('sw-foo', {
            routes: {
                index: {
                    path: 'index'
                }
            }
        });

        expect(module).toBe(false);
    });

    test(
        'should be possible to register a module with a valid route definition',
        () => {
            const module = register('sw-foo', {
                routes: {
                    index: {
                        path: 'index',
                        component: 'sw-foo-bar-index'
                    }
                }
            });

            expect(typeof module).toBe('object');
            expect(module.routes).toBeInstanceOf(Map);
            expect(typeof module.manifest).toBe('object');
            expect(typeof module.type).toBe('string');
            expect(module.type).toBe('plugin');
            expect(module.navigation).toBe(undefined);
        }
    );

    test(
        'should be possible to register a module with two components per route',
        () => {
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

            expect(module.routes.has('sw.foo.index')).toBe(true);
            expect(typeof module).toBe('object');
            expect(module.routes).toBeInstanceOf(Map);
            expect(typeof module.manifest).toBe('object');
            expect(typeof module.type).toBe('string');
            expect(module.type).toBe('plugin');
            expect(module.navigation).toBe(undefined);

            expect(typeof route.components.test).toBe('string');
            expect(route.components.test).toBe('sw-foo-test');
            expect(typeof route.components.default).toBe('string');
            expect(route.components.default).toBe('sw-foo-bar-index');
        }
    );

    test('should be possible to register a module with a navigation entry', () => {
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

        expect(module.navigation).toBeInstanceOf(Array);
        const navigationEntry = module.navigation[0];
        expect(typeof navigationEntry).toBe('object');
    });

    test(
        'should be possible to register a module with multiple navigation entries',
        () => {
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

            expect(module.navigation).toBeInstanceOf(Array);
            expect(module.navigation.length).toBe(2);
            const routerNavigationEntry = module.navigation[0];
            const externalLinkNavigation = module.navigation[1];
            expect(typeof routerNavigationEntry).toBe('object');
            expect(routerNavigationEntry.label).toBe('FooIndex');

            expect(typeof externalLinkNavigation).toBe('object');
            expect(externalLinkNavigation.link).toBe('http://de.shopware.com');
        }
    );

    test('should be possible to get all registered modules', () => {
        register('sw-foo', {
            routes: {
                index: {
                    path: 'index',
                    component: 'sw-foo-bar-index'
                }
            }
        });

        const modules = ModuleFactory.getModuleRegistry();

        expect(modules.size).toBe(1);
        expect(modules.has('sw-foo')).toBe(true);
    });

    test('should be possible to get all registered module routes', () => {
        register('sw-foo', {
            routes: {
                index: {
                    path: 'index',
                    component: 'sw-foo-bar-index'
                }
            }
        });

        const routes = ModuleFactory.getModuleRoutes();

        expect(routes).toBeInstanceOf(Array);
        expect(typeof routes[0]).toBe('object');
        expect(routes[0].name).toEqual('sw.foo.index');
        expect(routes[0].type).toBe('plugin');
    });

    test('should be possible to get an module by its entity name', () => {
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

        expect(typeof module).toBe('object');
        expect(module).toEqual(registeredModule);
        expect(module.manifest.entity).toBe('foo');
    });

    test('should return first module when entity isn`t unique', () => {
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

        expect(typeof module).toBe('object');
        expect(module).toEqual(registeredModule);
        expect(module.manifest.entity).toBe('foo');
    });

    test('should return undefined if module with that entity is not found', () => {
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

        expect(typeof module).toBe('undefined');
    });
    test('it merges snippets from more than two modules', () => {
        register('sw-foo', {
            entity: 'foo',
            snippets: {
                'de-DE': {
                    global: {
                        snippets: {
                            foo: 'foo'
                        }
                    }
                }
            },
            routes: {
                index: {
                    path: 'index',
                    component: 'sw-foo-index'
                }
            }
        });
        register('sw-bar', {
            entity: 'bar',

            snippets: {
                'de-DE': {
                    global: {
                        snippets: {
                            bar: 'bar'
                        }
                    }
                }
            },
            routes: {
                index: {
                    path: 'index',
                    component: 'sw-foo-index'
                }
            }
        });
        register('sw-baz', {
            entity: 'bar2',

            snippets: {
                'de-DE': {
                    global: {
                        snippets: {
                            foo: 'no foo'
                        }
                    }
                }
            },

            routes: {
                index: {
                    path: 'index',
                    component: 'sw-foo-index'
                }
            }
        });

        const moduleFactory = Application.getContainer('factory').module;
        expect(moduleFactory.getModuleSnippets()).toEqual({
            'de-DE': {
                global: {
                    snippets: {
                        foo: 'no foo',
                        bar: 'bar'
                    }
                }
            }
        });
    });

    test('should add settings item if feature flag is active', () => {
        Shopware.FeatureConfig.init({ testFlag: true });
        Shopware.State.get('settingsItems').settingsGroups = {};

        register('sw-foo', {
            name: 'fooBar',
            title: 'barFoo',
            settingsItem: {
                group: 'fooGroup',
                to: 'foo.bar',
                icon: 'fooIcon'
            },
            flag: 'testFlag',
            routes: {
                index: {
                    component: 'sw-foo-bar',
                    path: 'index'
                }
            }
        });

        const expectedSettingsItem = {
            fooGroup:
                [
                    {
                        group: 'fooGroup',
                        icon: 'fooIcon',
                        id: 'sw-foo',
                        label: 'barFoo',
                        name: 'fooBar',
                        to: 'foo.bar'
                    }
                ]
        };
        expect(Shopware.State.get('settingsItems').settingsGroups).toEqual(expectedSettingsItem);
    });

    test('should not add settings item if feature flag is deactivated', () => {
        Shopware.FeatureConfig.init({ testFlag: false });
        Shopware.State.get('settingsItems').settingsGroups = {};

        register('sw-foo', {
            name: 'fooBar',
            title: 'barFoo',
            settingsItem: {
                group: 'fooGroup',
                to: 'foo.bar',
                icon: 'fooIcon'
            },
            flag: 'testFlag',
            routes: {
                index: {
                    component: 'sw-foo-bar',
                    path: 'index'
                }
            }
        });

        expect(Shopware.State.get('settingsItems').settingsGroups).toEqual({});
    });
});
