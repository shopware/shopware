/**
 * @sw-package framework
 */

describe('core/application.js', () => {
    const originalInjectJs = Shopware.Application.injectJs;
    const originalInjectCss = Shopware.Application.injectCss;
    const originalInjectPlugin = Shopware.Application.injectPlugin;
    const originalInjectIframe = Shopware.Application.injectIframe;
    const originalNodeEnv = process.env.NODE_ENV;

    beforeEach(() => {
        jest.clearAllMocks();
        Shopware.Application.injectJs = originalInjectJs;
        Shopware.Application.injectCss = originalInjectCss;
        Shopware.Application.injectPlugin = originalInjectPlugin;
        Shopware.Application.injectIframe = originalInjectIframe;
        process.env.NODE_ENV = originalNodeEnv;
        Shopware.Context.app.config.bundles = {};
        global.fetch = jest.fn(() => Promise.resolve());
        document.head.innerHTML = '';
        document.body.innerHTML = '';
        jest.useRealTimers();
    });

    afterEach(() => {
        jest.restoreAllMocks();
        jest.useRealTimers();
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
        Shopware.Application.injectPlugin = jest.fn(() => Promise.resolve());

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

    it('should resolve CSS injection before timeout', async () => {
        jest.useFakeTimers();

        const result = Shopware.Application.injectCss('/bundles/acme/administration/assets/app.css');
        const link = document.head.querySelector('link');

        expect(link).not.toBeNull();
        expect(link.getAttribute('href')).toBe('/bundles/acme/administration/assets/app.css');

        link.dispatchEvent(new Event('load'));

        await expect(result).resolves.toBeUndefined();
    });

    it('should reject CSS injection on load error before timeout', async () => {
        jest.useFakeTimers();

        const result = Shopware.Application.injectCss('/bundles/acme/administration/assets/app.css');
        const link = document.head.querySelector('link');

        expect(link).not.toBeNull();

        link.dispatchEvent(new Event('error'));

        await expect(result).rejects.toThrow(
            'Failed to load Administration extension CSS asset: /bundles/acme/administration/assets/app.css',
        );
    });

    it('should reject CSS injection after timeout when request stays pending', async () => {
        jest.useFakeTimers();

        const result = Shopware.Application.injectCss('/bundles/acme/administration/assets/pending.css');

        jest.advanceTimersByTime(15000);

        await expect(result).rejects.toThrow(
            'Loading Administration extension CSS asset timed out after 15000ms: /bundles/acme/administration/assets/pending.css',
        );
    });

    it('should resolve JS injection before timeout', async () => {
        jest.useFakeTimers();

        const result = Shopware.Application.injectJs('/bundles/acme/administration/assets/app.js');
        const script = document.body.querySelector('script');

        expect(script).not.toBeNull();
        expect(script.getAttribute('src')).toBe('/bundles/acme/administration/assets/app.js');

        script.dispatchEvent(new Event('load'));

        await expect(result).resolves.toBeUndefined();
    });

    it('should reject JS injection on load error before timeout', async () => {
        jest.useFakeTimers();

        const result = Shopware.Application.injectJs('/bundles/acme/administration/assets/app.js');
        const script = document.body.querySelector('script');

        expect(script).not.toBeNull();

        script.dispatchEvent(new Event('error'));

        await expect(result).rejects.toThrow(
            'Failed to load Administration extension JavaScript asset: /bundles/acme/administration/assets/app.js',
        );
    });

    it('should reject JS injection after timeout when request stays pending', async () => {
        jest.useFakeTimers();

        const result = Shopware.Application.injectJs('/bundles/acme/administration/assets/pending.js');

        jest.advanceTimersByTime(15000);

        await expect(result).rejects.toThrow(
            'Loading Administration extension JavaScript asset timed out after 15000ms: /bundles/acme/administration/assets/pending.js',
        );
    });
});
