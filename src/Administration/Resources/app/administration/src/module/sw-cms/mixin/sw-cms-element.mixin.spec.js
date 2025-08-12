/**
 * @sw-package discovery
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
async function createWrapper(element = defaultElement, routeName = '') {
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
            mocks: {
                $route: {
                    name: routeName,
                },
            },
        },
    });
}

describe('module/sw-cms/mixin/sw-cms-element.mixin.ts', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/text');

        Shopware.Store.register({
            id: 'swProductDetail',
            state: () => ({
                product: null,
            }),
        });
    });

    beforeEach(() => {
        Shopware.Store.get('swCategoryDetail').$reset();
        Shopware.Store.get('swProductDetail').$reset();
    });

    afterEach(() => {
        Shopware.Store.get('cmsPage').resetCmsPageState();
    });

    it('initElementConfig is properly merging configs from various sources', async () => {
        Shopware.Store.get('swCategoryDetail').category = {
            id: '12345',
            translations: [
                {
                    languageId: Shopware.Context.api.systemLanguageId,
                    name: 'Category name B',
                    slotConfig: {
                        [defaultElement.id]: {
                            overrideFromCategory: 'bar',
                        },
                    },
                },
            ],
        };

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
            overrideFromCategory: 'bar',
        };

        const wrapper = await createWrapper(defaultElement, 'sw.category.detail');
        wrapper.vm.initElementConfig('text');

        expect(wrapper.vm.element.config).toEqual(expectedElementConfig);
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
        const store = Shopware.Store.get('cmsPage');

        store.currentDemoEntity = {
            id: '12345',
            translations: [
                {
                    languageId: Shopware.Context.api.systemLanguageId,
                    name: 'Category name B',
                    slotConfig: {
                        'sw-cms-el-text-1234': {
                            content: 'Demo content',
                        },
                    },
                },
            ],
        };

        expect(wrapper.vm.getDemoValue('category.translations')).toMatchObject(store.currentDemoEntity.translations);
    });

    it('should return category from store when available', async () => {
        const wrapper = await createWrapper();
        const mockCategory = {
            id: 'category-1',
            name: 'Test Category',
            translations: [],
        };

        expect(wrapper.vm.category).toBeNull();

        Shopware.Store.get('swCategoryDetail').category = mockCategory;

        expect(wrapper.vm.category).toMatchObject(mockCategory);
    });

    it('should return product from store when available', async () => {
        const wrapper = await createWrapper();
        const mockProduct = {
            id: 'product-1',
            name: 'Test Product',
            translations: [],
        };

        expect(wrapper.vm.product).toBeNull();

        Shopware.Store.get('swProductDetail').product = mockProduct;

        expect(wrapper.vm.product).toMatchObject(mockProduct);
    });

    it('should return correct moduleEntity based on route meta', async () => {
        const wrapper = await createWrapper(defaultElement, 'sw.category.detail');
        const mockCategory = {
            id: 'category-1',
            name: 'Test Category',
            translations: [],
        };

        Shopware.Store.get('swCategoryDetail').category = mockCategory;
        expect(wrapper.vm.moduleEntity).toMatchObject(mockCategory);
    });

    it('should return null when no module entity is defined', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.$route = {
            meta: {},
        };

        expect(wrapper.vm.moduleEntity).toBeNull();
    });

    it('should return null for unknown module entity', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.$route = {
            meta: {
                $module: {
                    entity: 'foo-bar',
                },
            },
        };

        expect(wrapper.vm.moduleEntity).toBeNull();
    });

    it('should return configOverride from entity slot config', async () => {
        const wrapper = await createWrapper(defaultElement, 'sw.category.detail');
        Shopware.Store.get('swCategoryDetail').category = {
            id: 'category-1',
            translated: {
                slotConfig: {
                    [defaultElement.id]: {
                        content: 'override content',
                    },
                },
            },
        };

        expect(wrapper.vm.configOverride.content).toBe('override content');
    });

    it('should fall back to element translated config when no entity config found', async () => {
        const wrapper = await createWrapper({
            ...defaultElement,
            translated: {
                config: {
                    content: 'translated content',
                },
            },
        });

        expect(wrapper.vm.configOverride.content).toBe('translated content');
    });

    it('should return slot config from entity translated data', async () => {
        const wrapper = await createWrapper(defaultElement, 'sw.category.detail');
        Shopware.Store.get('swCategoryDetail').category = {
            id: 'category-1',
            translated: {
                slotConfig: {
                    [defaultElement.id]: {
                        content: 'entity content',
                    },
                },
            },
        };

        const slotConfig = wrapper.vm.getEntitySlotConfig();

        expect(slotConfig.content).toBe('entity content');
    });

    it('should fall back to default translations when no translated config found', async () => {
        const wrapper = await createWrapper(defaultElement, 'sw.category.detail');

        Shopware.Store.get('swCategoryDetail').category = {
            id: 'category-1',
            translations: [
                {
                    languageId: Shopware.Context.api.systemLanguageId,
                    slotConfig: {
                        [defaultElement.id]: {
                            content: 'default content',
                        },
                    },
                },
            ],
        };

        const slotConfig = wrapper.vm.getEntitySlotConfig();

        expect(slotConfig.content).toBe('default content');
    });
});
