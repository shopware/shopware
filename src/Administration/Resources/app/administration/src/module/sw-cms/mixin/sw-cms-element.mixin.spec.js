/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

const defaultElement = {
    id: 'sw-cms-el-text-1234',
    config: {
        overrideFromProp: 'foo',
    },
    data: null,
};

/**
 * Using a real component for testing
 */
async function createWrapper(element = defaultElement) {
    return mount(await wrapTestComponent('sw-cms-el-text', { sync: true }), {
        props: {
            element,
        },
        global: {
            provide: {
                cmsService: Shopware.Service('cmsService'),
            },
            stubs: {
                'sw-text-editor': true,
            },
        },
    });
}

describe('module/sw-cms/mixin/sw-cms-element.mixin.ts', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/text');
    });

    afterEach(() => {
        Shopware.Store.get('cmsPageState').resetCmsPageState();
    });

    it('initElementConfig is properly merging configs from various sources', async () => {
        try {
            Shopware.State.registerModule('swCategoryDetail', {
                namespaced: true,
                state: {
                    category: {
                        id: '12345',
                        translations: [{
                            languageId: Shopware.Context.api.systemLanguageId,
                            name: 'Category name B',
                            slotConfig: {
                                'sw-cms-el-text-1234': {
                                    overrideFromCategory: 'bar',
                                },
                            },
                        }],
                    },
                },
            });

            // Config structure is derived from the default config -> module/sw-cms/elements/text/index.js
            const expectedElementConfig = {
                content: {
                    source: 'static',
                    value: expect.any(String),
                },
                verticalAlign: {
                    source: 'static',
                    value: null,
                },
                overrideFromProp: 'foo',
                overrideFromCategory: 'bar',
            };

            const wrapper = await createWrapper();
            wrapper.vm.initElementConfig('text');

            expect(wrapper.vm.element.config).toEqual(expectedElementConfig);
        } finally {
            Shopware.State.unregisterModule('swCategoryDetail');
        }
    });

    it('initElementData is using the provided element.data as config', async () => {
        const customData = {
            content: 'Hello World',
        };
        const wrapper = await createWrapper({
            ...defaultElement,
            data: customData,
        });
        wrapper.vm.initElementData('text');

        expect(wrapper.vm.element.data).toMatchObject(customData);
    });

    it('initElementData is using default data as fallback', async () => {
        const wrapper = await createWrapper({
            ...defaultElement,
        });
        const registry = Shopware.Service('cmsService').getCmsElementRegistry();
        registry.text.defaultData = {
            defaultProperty: 'foo-bar',
        };

        wrapper.vm.initElementData('text');
        expect(wrapper.vm.element.data).toMatchObject({
            defaultProperty: 'foo-bar',
        });
    });

    it('getDemoValue is invoking cmsService.getPropertyByMappingPath', async () => {
        const wrapper = await createWrapper();
        const store = Shopware.Store.get('cmsPageState');

        store.currentDemoEntity = {
            id: '12345',
            translations: [{
                languageId: Shopware.Context.api.systemLanguageId,
                name: 'Category name B',
                slotConfig: {
                    'sw-cms-el-text-1234': {
                        content: 'Demo content',
                    },
                },
            }],
        };

        expect(wrapper.vm.getDemoValue('category.translations')).toMatchObject(store.currentDemoEntity.translations);
    });
});
