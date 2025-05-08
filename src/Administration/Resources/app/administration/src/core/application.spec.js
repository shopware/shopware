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
});
