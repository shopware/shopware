/**
 * @sw-package framework
 */

describe('core/application.js', () => {
    const originalInjectJs = Shopware.Application.injectJs;
    const originalInjectIframe = Shopware.Application.injectIframe;
    const originalNodeEnv = process.env.NODE_ENV;

    beforeEach(() => {
        jest.clearAllMocks();
        Shopware.Application.injectJs = originalInjectJs;
        Shopware.Application.injectIframe = originalInjectIframe;
        process.env.NODE_ENV = originalNodeEnv;
        Shopware.Context.app.config.bundles = {};
        global.fetch = jest.fn(() => Promise.resolve());
    });

    it("should be error tolerant if loading a plugin's files fails", async () => {
        const warningSpy = jest.spyOn(console, 'warn').mockImplementation();

        Shopware.Application.injectJs = async () => {
            throw new Error('Inject js fails');
        };

        const result = await Shopware.Application.injectPlugin({
            js: ['some.js'],
        });

        expect(warningSpy).toHaveBeenCalledWith('Error while loading plugin', {
            js: ['some.js'],
        });
        expect(result).toBeNull();
    });

    it('should call swagCommercial before any other plugins', async () => {
        // mock plugins
        Shopware.Context.app.config.bundles = {
            'custom-pricing': {
                js: '/bundles/custompricing/administration/js/custom-pricing.js',
            },
            'webhook-flow-action': {
                js: '/bundles/webhookflowaction/administration/js/webhook-flow-action.js',
            },
            'swag-commercial': {
                js: '/bundles/swagcommercial/administration/js/swag-commercial.js',
            },
            'rule-builder-preview': {
                css: '/bundles/rulebuilderpreview/administration/css/rule-builder-preview.css',
                js: '/bundles/rulebuilderpreview/administration/js/rule-builder-preview.js',
            },
            storefront: {
                css: '/bundles/storefront/administration/css/storefront.css',
                js: '/bundles/storefront/administration/js/storefront.js',
            },
            'return-management': {
                js: '/bundles/returnmanagement/administration/js/return-management.js',
            },
            'text-generator': {
                css: '/bundles/textgenerator/administration/css/text-generator.css',
                js: '/bundles/textgenerator/administration/js/text-generator.js',
            },
            'content-generator': {
                js: '/bundles/contentgenerator/administration/js/content-generator.js',
            },
            'multi-warehouse': {
                css: '/bundles/multiwarehouse/administration/css/multi-warehouse.css',
                js: '/bundles/multiwarehouse/administration/js/multi-warehouse.js',
            },
            'flow-sharing': {
                js: '/bundles/flowsharing/administration/js/flow-sharing.js',
            },
            'delayed-flow-action': {
                js: '/bundles/delayedflowaction/administration/js/delayed-flow-action.js',
            },
        };

        // save called plugins in call order
        const callOrder = {
            js: [],
            css: [],
        };

        // mock the plugin injection
        Shopware.Application.injectPlugin = async (plugin) => {
            callOrder.js.push(plugin.js);
            callOrder.css.push(plugin.css);
        };

        // load all plugins
        await Shopware.Application.loadPlugins();

        // check if swagCommercial was called first before the other plugins are loaded
        expect(callOrder.js[0]).toBe('/bundles/swagcommercial/administration/js/swag-commercial.js');
    });

    it('should load plugins correctly in prod', async () => {
        // Mock injectIframe method
        Shopware.Application.injectIframe = jest.fn();

        // Mock plugins
        Shopware.Context.app.config.bundles = {
            'swag-commercial': {
                js: '/bundles/swagcommercial/administration/js/swag-commercial.js',
            },
            storefront: {
                css: '/bundles/storefront/administration/css/storefront.css',
                js: '/bundles/storefront/administration/js/storefront.js',
            },
            'test-plugin': {
                baseUrl: 'http://localhost:8000/bundles/testplugin/administration/',
            },
        };

        // Load plugins
        await Shopware.Application.loadPlugins();

        // Check if injectIframe was called with correct parameters
        expect(Shopware.Application.injectIframe).toHaveBeenCalledWith({
            bundleName: 'test-plugin',
            iframeSrc: 'http://localhost:8000/bundles/testplugin/administration/',
        });
    });

    it('should load plugins correctly in watch', async () => {
        process.env.NODE_ENV = 'development';

        global.fetch = jest.fn(() =>
            Promise.resolve({
                json: () => ({
                    'test-plugin': {
                        html: 'http://localhost:8000/bundles/testplugin/administration/',
                    },
                }),
            }),
        );

        // Mock plugins
        Shopware.Context.app.config.bundles = {
            'test-plugin': {
                baseUrl: 'http://localhost:8000/bundles/testplugin/administration/',
            },
        };

        // Mock injectIframe method
        Shopware.Application.injectIframe = jest.fn();

        // Load plugins
        await Shopware.Application.loadPlugins();

        // Check if injectIframe was called with correct parameters
        expect(Shopware.Application.injectIframe).toHaveBeenCalledWith({
            bundleName: 'test-plugin',
            iframeSrc: 'http://localhost:8000/bundles/testplugin/administration/',
        });
    });

    describe('createApplicationRoot first run wizard handling', () => {
        const originalEnvironment = Shopware.Context.app.environment;
        const originalFirstRunWizard = Shopware.Context.app.firstRunWizard;
        const originalView = Shopware.Application.view;

        function mockContainers({ isLoggedIn, router }) {
            const originalGetContainer = Shopware.Application.getContainer.bind(Shopware.Application);

            return jest.spyOn(Shopware.Application, 'getContainer').mockImplementation((name) => {
                if (name === 'init') {
                    return { router: { getRouterInstance: () => router } };
                }

                if (name === 'service') {
                    return { loginService: { isLoggedIn: () => isLoggedIn } };
                }

                return originalGetContainer(name);
            });
        }

        afterEach(() => {
            Shopware.Context.app.environment = originalEnvironment;
            Shopware.Context.app.firstRunWizard = originalFirstRunWizard;
            Shopware.Application.view = originalView;
        });

        it('should wait for the router to be ready before deciding on a first run wizard redirect', async () => {
            Shopware.Context.app.environment = 'production';
            Shopware.Context.app.firstRunWizard = true;
            Shopware.Application.view = { init: jest.fn() };

            const events = [];
            const router = {
                isReady: jest.fn(() => Promise.resolve().then(() => events.push('ready'))),
                currentRoute: { value: { name: 'sw.first.run.wizard.index.paypal.credentials' } },
                push: jest.fn(() => events.push('push')),
            };

            const getContainerSpy = mockContainers({ isLoggedIn: true, router });

            await Shopware.Application.createApplicationRoot();

            expect(router.isReady).toHaveBeenCalledTimes(1);
            // isReady must resolve before the redirect decision is made
            expect(events).toEqual(['ready']);
            // already on a wizard route -> must not be pushed back to the wizard start
            expect(router.push).not.toHaveBeenCalled();

            getContainerSpy.mockRestore();
        });

        it('should redirect into the first run wizard when the resolved route is outside the wizard', async () => {
            Shopware.Context.app.environment = 'production';
            Shopware.Context.app.firstRunWizard = true;
            Shopware.Application.view = { init: jest.fn() };

            const router = {
                isReady: jest.fn(() => Promise.resolve()),
                currentRoute: { value: { name: 'sw.dashboard.index' } },
                push: jest.fn(),
            };

            const getContainerSpy = mockContainers({ isLoggedIn: true, router });

            await Shopware.Application.createApplicationRoot();

            expect(router.isReady).toHaveBeenCalledTimes(1);
            expect(router.push).toHaveBeenCalledWith({ name: 'sw.first.run.wizard.index' });

            getContainerSpy.mockRestore();
        });

        it('should not touch the router when the first run wizard is disabled', async () => {
            Shopware.Context.app.environment = 'production';
            Shopware.Context.app.firstRunWizard = false;
            Shopware.Application.view = { init: jest.fn() };

            const router = {
                isReady: jest.fn(() => Promise.resolve()),
                currentRoute: { value: { name: 'sw.dashboard.index' } },
                push: jest.fn(),
            };

            const getContainerSpy = mockContainers({ isLoggedIn: true, router });

            await Shopware.Application.createApplicationRoot();

            expect(router.isReady).not.toHaveBeenCalled();
            expect(router.push).not.toHaveBeenCalled();

            getContainerSpy.mockRestore();
        });
    });

    it('should load plugins correctly in watch with all permissions', async () => {
        process.env.NODE_ENV = 'development';

        global.fetch = jest.fn(() =>
            Promise.resolve({
                json: () => ({
                    'test-plugin': {
                        html: 'http://localhost:8000/bundles/testplugin/administration/',
                    },
                }),
            }),
        );

        // Mock plugins
        Shopware.Context.app.config.bundles = {
            'test-plugin': {
                baseUrl: 'http://localhost:8000/bundles/testplugin/administration/',
            },
        };

        // Load plugins
        await Shopware.Application.loadPlugins();

        // Check if new plugin added the correct extension to the store
        expect(Shopware.Store.get('extensions').extensionsState['test-plugin']).toEqual({
            name: 'test-plugin',
            baseUrl: 'http://localhost:8000/bundles/testplugin/administration/',
            permissions: {
                additional: ['*'],
                create: ['*'],
                read: ['*'],
                update: ['*'],
                delete: ['*'],
            },
            version: undefined,
            type: 'plugin',
            integrationId: undefined,
            active: undefined,
        });
    });
});
