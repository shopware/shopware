/**
 * @package content
 */
import { mount } from '@vue/test-utils';
import AppCmsService from 'src/app/service/app-cms.service';
import VueAdapter from 'src/app/adapter/view/vue.adapter';
import fixtures from './_mocks/appBlocks.json';

describe('src/app/service/app-cms.service', () => {
    let vueAdapter;
    let service;

    beforeAll(async () => {
        await import('src/module/sw-cms/service/cms.service');
        await import('src/app/service/app-cms.service');
    });

    beforeEach(async () => {
        global.console.warn = jest.fn();

        vueAdapter = new VueAdapter({
            getContainer: () => ({
                component: '',
                locale: { getLocaleRegistry: () => [], getLastKnownLocale: () => 'en-GB' },
            }),
        });

        service = await new AppCmsService({
            fetchAppBlocks() {
                return Promise.resolve(fixtures.blocks);
            },
        }, vueAdapter);
    });

    it('should be able to override the default block configuration', async () => {
        let defaultConfig = service.defaultBlockConfig;

        Shopware.Locale.register('de-DE', {});
        Shopware.Locale.register('en-GB', {});

        expect(defaultConfig.prefix).toBe('sw-cms-block-');
        expect(defaultConfig.componentSuffix).toBe('-component');
        expect(defaultConfig.previewComponentSuffix).toBe('-preview-component');

        service.setDefaultConfig({
            prefix: 'sw-cms-custom-block-',
            componentSuffix: '-foo',
            previewComponentSuffix: '-bar',
        });

        defaultConfig = service.defaultBlockConfig;

        expect(defaultConfig.prefix).toBe('sw-cms-custom-block-');
        expect(defaultConfig.componentSuffix).toBe('-foo');
        expect(defaultConfig.previewComponentSuffix).toBe('-bar');
    });

    it('should iterate the received blocks', async () => {
        expect(service.iterateCmsBlocks(fixtures.blocks)).toBeTruthy();
    });

    it('should validate the category of a block', async () => {
        expect(service.validateBlockCategory('commerce')).toBeTruthy();
        expect(service.validateBlockCategory('text')).toBeTruthy();
        expect(service.validateBlockCategory('foobar')).toBeFalsy();
    });

    it('should register the block label to the global locale factory', async () => {
        service.registerBlockSnippets('fooBar', {
            'en-GB': 'MyFooBarBlock',
            'pt-PT': 'MyFooBarBlock',
        });

        const translations = Shopware.Locale.getByName('en-GB');
        expect(translations).toStrictEqual({
            'sw-app-system-cms': {
                'label-fooBar': 'MyFooBarBlock',
                'label-my-first-block': 'First block from app',
                'label-my-second-block': 'Second block from app',
            },
        });

        expect(global.console.warn).toHaveBeenCalledWith(
            '[AppCmsService]',
            'The locale "pt-PT" is not registered in Shopware.Locale.',
        );
    });

    it('should register a block to the application', async () => {
        service.registerCmsBlock({
            category: 'foobar',
            label: {
                'de-DE': 'MyBlockLabel',
            },
        });

        expect(global.console.warn).toHaveBeenCalledWith(
            '[AppCmsService]',
            'The category "foobar" is not a valid category.',
        );
    });

    it('should collect & inject styles for custom cms blocks', async () => {
        const cssFixtures = '#foo { color: #f00 }';

        service.registerStyles({
            styles: cssFixtures,
        });
        expect(service.blockStyles).toContain(cssFixtures);
        expect(service.injectStyleTag()).toBeTruthy();
    });

    it('should create vue.js components incl. preview for the block component in the right order', async () => {
        const blockDefinition = fixtures.blocks[0];

        const component = service.createBlockComponent(blockDefinition);
        const mountedComponent = mount(component, {
            slots: {
                left: 'third_',
                middle: 'first_',
                right: 'second_',
            },
        });

        expect(mountedComponent.vm).toBeTruthy();
        expect(mountedComponent.text()).toBe('first_second_third_');

        const previewComponent = service.createBlockPreviewComponent(blockDefinition);
        const mountedPreviewComponent = mount(previewComponent, {
            stubs: {
                'sw-cms-el-preview-manufacturer-logo': true,
                'sw-cms-el-preview-image-gallery': true,
                'sw-cms-el-preview-buy-box': true,
            },
        });

        expect(mountedPreviewComponent.vm).toBeTruthy();
        expect(mountedPreviewComponent.text()).toBe('first-slot second-slot third-slot');
    });

    it('should create vue.js components for the block component in the given order, with no position set', async () => {
        const blockDefinition = fixtures.blocks[1];

        const component = service.createBlockComponent(blockDefinition);
        const mountedComponent = mount(component, {
            slots: {
                left: 'first_',
                middle: 'second_',
                right: 'third_',
            },
        });

        expect(mountedComponent.vm).toBeTruthy();
        expect(mountedComponent.text()).toBe('first_second_third_');
    });
});
