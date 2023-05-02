/**
 * @package admin
 */


describe('core/application.js', () => {
    const originalInjectJs = Shopware.Application.injectJs;

    beforeEach(() => {
        Shopware.Application.injectJs = originalInjectJs;
        Shopware.Context.app.config.bundles = {};
    });

    it('should be error tolerant if loading a plugin\'s files fails', async () => {
        const warningSpy = jest.spyOn(console, 'warn').mockImplementation();

        Shopware.Application.injectJs = async () => {
            throw new Error('Inject js fails');
        };

        const result = await Shopware.Application.injectPlugin({
            js: ['some.js'],
        });

        expect(warningSpy).toHaveBeenCalledWith('Error while loading plugin', { js: ['some.js'] });
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
});
