import { shallowMount, config } from '@vue/test-utils';
import AppCmsService from 'src/app/service/app-cms.service';
import VueAdapter from 'src/app/adapter/view/vue.adapter';
import fixtures from './_mocks/appBlocks.json';

Shopware.Service().register('cmsService', () => {
    return {
        registerCmsBlock: jest.fn()
    };
});

describe('src/app/service/app-cms.service', () => {
    let vueAdapter;
    let service;

    beforeEach(() => {
        global.console.warn = jest.fn();

        vueAdapter = new VueAdapter({
            getContainer: () => ({
                component: '',
                locale: { getLocaleRegistry: () => [], getLastKnownLocale: () => 'en-GB' }
            })
        });

        service = new AppCmsService({
            fetchAppBlocks() {
                return Promise.resolve(fixtures);
            }
        }, vueAdapter);
    });

    afterEach(() => {
        global.console.warn.mockReset();
        service = null;
    });

    it('should be able to override the default block configuration', () => {
        let defaultConfig = service.defaultBlockConfig;

        Shopware.Locale.register('de-DE', {});
        Shopware.Locale.register('en-GB', {});

        expect(defaultConfig.prefix).toBe('sw-cms-block-');
        expect(defaultConfig.componentSuffix).toBe('-component');
        expect(defaultConfig.previewComponentSuffix).toBe('-preview-component');

        service.setDefaultConfig({
            prefix: 'sw-cms-custom-block-',
            componentSuffix: '-foo',
            previewComponentSuffix: '-bar'
        });

        defaultConfig = service.defaultBlockConfig;

        expect(defaultConfig.prefix).toBe('sw-cms-custom-block-');
        expect(defaultConfig.componentSuffix).toBe('-foo');
        expect(defaultConfig.previewComponentSuffix).toBe('-bar');
    });

    it('should iterate the received blocks', () => {
        expect(service.iterateCmsBlocks(fixtures.blocks)).toBeTruthy();
    });

    it('should validate the category of a block', () => {
        expect(service.validateBlockCategory('commerce')).toBeTruthy();
        expect(service.validateBlockCategory('text')).toBeTruthy();
        expect(service.validateBlockCategory('foobar')).toBeFalsy();
    });

    it('should register the block label to the global locale factory', () => {
        service.registerBlockSnippets('fooBar', {
            'en-GB': 'MyFooBarBlock',
            'pt-PT': 'MyFooBarBlock'
        });

        const translations = Shopware.Locale.getByName('en-GB');
        expect(translations).toStrictEqual({
            'sw-app-system-cms': {
                'label-fooBar': 'MyFooBarBlock',
                'label-my-first-block': 'First block from app',
                'label-my-second-block': 'Second block from app'
            }
        });

        expect(global.console.warn).toBeCalledWith(
            '[AppCmsService]',
            'The locale "pt-PT" is not registered in Shopware.Locale.'
        );
    });

    it('should register a block to the application', () => {
        service.registerCmsBlock({
            category: 'foobar',
            label: {
                'de-DE': 'MyBlockLabel'
            }
        });

        expect(global.console.warn).toBeCalledWith(
            '[AppCmsService]',
            'The category "foobar" is not a valid category.'
        );
    });

    it('should collect & inject styles for custom cms blocks', () => {
        const cssFixtures = '#foo { color: #f00 }';

        service.registerStyles({
            styles: ''
        });
        expect(service.blockStyles).toBe('');
        expect(service.injectStyleTag()).toBeFalsy();

        service.registerStyles({
            styles: cssFixtures
        });
        expect(service.blockStyles).toBe(cssFixtures);
        expect(service.injectStyleTag()).toBeTruthy();
    });

    it('should create a vue.js component for the block component', () => {
        // delete global $router and $routes mocks
        delete config.mocks.$router;
        delete config.mocks.$route;

        const blockDefinition = fixtures.blocks[0];
        const component = service.createBlockPreviewComponent(blockDefinition);

        const mountedComponent = shallowMount(component, {
            stubs: {
                'sw-cms-el-preview-manufacturer-logo': true,
                'sw-cms-el-preview-image-gallery': true,
                'sw-cms-el-preview-buy-box': true
            }
        });

        expect(mountedComponent.vm).toBeTruthy();

        const previewComponent = service.createBlockPreviewComponent(blockDefinition);
        const mountedPreviewComponent = shallowMount(previewComponent, {
            stubs: {
                'sw-cms-el-preview-manufacturer-logo': true,
                'sw-cms-el-preview-image-gallery': true,
                'sw-cms-el-preview-buy-box': true
            }
        });

        expect(mountedPreviewComponent.vm).toBeTruthy();
    });
});
