/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

const defaultElement = {
    id: 'sw-cms-el-text-1234',
    type: 'text',
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
    let initialLanguageId;
    let initialLanguage;

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
        initialLanguageId = Shopware.Store.get('context').api.languageId;
        initialLanguage = Shopware.Store.get('context').api.language;
        Shopware.Store.get('swCategoryDetail').$reset();
        Shopware.Store.get('swProductDetail').$reset();
    });

    afterEach(() => {
        Shopware.Store.get('cmsPage').resetCmsPageState();
        Shopware.Store.get('context').api.languageId = initialLanguageId;
        Shopware.Store.get('context').api.language = initialLanguage;
    });

    it('initElementConfig is properly merging configs from various sources', async () => {
        Shopware.Store.get('swCategoryDetail').category = {
            id: '12345',
            slotConfig: {
                [defaultElement.id]: {
                    overrideFromCategory: 'bar',
                },
            },
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

        const expectedElementConfig = {
            content: {
                source: 'static',
                value: expect.any(String),
            },
            overrideFromCategory: 'bar',
            verticalAlign: {
                source: 'static',
                value: null,
            },
            overrideFromProp: 'foo',
        };

        const wrapper = await createWrapper(defaultElement, 'sw.category.detail');

        /**
         * Existing properties on the element will remain ("overrideFromProp").
         * Content overrides on the entity are applied on top, even if the key does not exist in the default config.
         */
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

    it('should apply inherited slotConfig from the explicit parent language', async () => {
        Shopware.Store.get('swProductDetail').product = {
            translations: [
                {
                    languageId: 'parent-language-id',
                    slotConfig: {
                        [defaultElement.id]: {
                            content: {
                                source: 'static',
                                value: 'inherited override',
                            },
                        },
                    },
                },
            ],
        };
        Shopware.Store.get('context').api.languageId = 'child-language-id';
        Shopware.Store.get('context').api.language = { parentId: 'parent-language-id' };

        const wrapper = await createWrapper(defaultElement, 'sw.product.detail');

        expect(wrapper.vm.element.config.content).toStrictEqual({
            source: 'static',
            value: 'inherited override',
        });
    });

    it('should not apply system language slotConfig without explicit parent language', async () => {
        Shopware.Store.get('swProductDetail').product = {
            translations: [
                {
                    languageId: Shopware.Context.api.systemLanguageId,
                    slotConfig: {
                        [defaultElement.id]: {
                            content: {
                                source: 'static',
                                value: 'system override',
                            },
                        },
                    },
                },
            ],
        };
        Shopware.Store.get('context').api.languageId = 'child-language-id';
        Shopware.Store.get('context').api.language = { parentId: null };

        const wrapper = await createWrapper(defaultElement, 'sw.product.detail');

        expect(wrapper.vm.element.config.content.value).not.toBe('system override');
    });

    it('should not mutate the parent language slotConfig when editing inherited content', async () => {
        const parentSlotConfig = {
            [defaultElement.id]: {
                content: {
                    source: 'static',
                    value: 'default - override',
                },
            },
        };

        Shopware.Store.get('swProductDetail').product = {
            translations: [
                {
                    languageId: 'parent-language-id',
                    slotConfig: parentSlotConfig,
                },
            ],
        };
        Shopware.Store.get('context').api.languageId = 'child-language-id';
        Shopware.Store.get('context').api.language = { parentId: 'parent-language-id' };

        const wrapper = await createWrapper(defaultElement, 'sw.product.detail');

        wrapper.vm.element.config.content.value = 'child custom content';

        expect(parentSlotConfig[defaultElement.id].content.value).toBe('default - override');
    });
});
