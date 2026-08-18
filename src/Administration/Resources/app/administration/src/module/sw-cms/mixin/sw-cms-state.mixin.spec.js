/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

function createWrapper(routeName = '') {
    return mount(
        {
            template: '<div></div>',
            mixins: [
                Shopware.Mixin.getByName('cms-state'),
            ],
        },
        {
            global: {
                mocks: {
                    $route: {
                        name: routeName,
                    },
                },
            },
        },
    );
}

const deviceViews = {
    desktop: 'desktop',
    mobile: 'mobile',
};

describe('module/sw-cms/mixin/sw-cms-state.mixin.js', () => {
    let initialLanguageId;
    let initialLanguage;

    beforeAll(async () => {
        await setupCmsEnvironment();
    });

    beforeEach(() => {
        initialLanguageId = Shopware.Store.get('context').api.languageId;
        initialLanguage = Shopware.Store.get('context').api.language;
    });

    afterEach(() => {
        Shopware.Store.get('cmsPage').resetCmsPageState();
        Shopware.Store.get('context').api.languageId = initialLanguageId;
        Shopware.Store.get('context').api.language = initialLanguage;
    });

    it('properties are properly written to and read from the shared store', () => {
        const wrapper = createWrapper();
        const store = Shopware.Store.get('cmsPage');

        const block = { id: 'block-1234' };
        wrapper.vm.selectedBlock = block;
        expect(wrapper.vm.selectedBlock).toEqual(block);
        expect(wrapper.vm.selectedBlock).toEqual(store.selectedBlock);

        const section = { id: 'section-1234' };
        wrapper.vm.selectedSection = section;
        expect(wrapper.vm.selectedSection).toEqual(section);
        expect(wrapper.vm.selectedSection).toEqual(store.selectedSection);

        expect(wrapper.vm.currentDeviceView).toEqual(deviceViews.desktop);
        store.setCurrentCmsDeviceView(deviceViews.mobile);
        expect(wrapper.vm.currentDeviceView).toEqual(deviceViews.mobile);

        expect(wrapper.vm.isSystemDefaultLanguage).toBe(true);
        store.setIsSystemDefaultLanguage(false);
        expect(wrapper.vm.isSystemDefaultLanguage).toBe(false);
    });

    it('should return correct moduleEntity based on route meta', async () => {
        const wrapper = await createWrapper('sw.category.detail.cms');
        const mockCategory = {
            id: 'category-1',
            name: 'Test Category',
            translations: [],
        };

        Shopware.Store.get('swCategoryDetail').category = mockCategory;
        expect(wrapper.vm.contentEntity).toMatchObject(mockCategory);
    });

    it('should return null when no content entity is defined', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.contentEntity).toBeNull();
    });

    it('should prefer parent translation slotConfig for inherited CMS overrides', async () => {
        const wrapper = await createWrapper('sw.category.detail.cms');
        const inheritedSlotConfig = {
            'slot-id': {
                content: {
                    value: 'inherited',
                },
            },
        };

        Shopware.Store.get('swCategoryDetail').category = {
            translations: [
                {
                    languageId: 'parent-language-id',
                    slotConfig: inheritedSlotConfig,
                },
            ],
        };
        Shopware.Store.get('context').api.languageId = 'child-language-id';
        Shopware.Store.get('context').api.language = { parentId: 'parent-language-id' };

        expect(wrapper.vm.inheritedSlotConfig).toStrictEqual(inheritedSlotConfig);
    });

    it('should not fall back to system language slotConfig without explicit parent language', async () => {
        const wrapper = await createWrapper('sw.category.detail.cms');

        Shopware.Store.get('swCategoryDetail').category = {
            translations: [
                {
                    languageId: Shopware.Context.api.systemLanguageId,
                    slotConfig: {
                        'slot-id': {
                            content: {
                                value: 'system',
                            },
                        },
                    },
                },
            ],
        };
        Shopware.Store.get('context').api.languageId = 'child-language-id';
        Shopware.Store.get('context').api.language = { parentId: null };

        expect(wrapper.vm.inheritedSlotConfig).toBeNull();
    });

    it('should retain parent-language fields when the child slot has a partial override', async () => {
        const wrapper = await createWrapper('sw.category.detail.cms');

        Shopware.Store.get('swCategoryDetail').category = {
            slotConfig: {
                'slot-id': {
                    content: { value: 'child content' },
                },
            },
            translations: [
                {
                    languageId: 'parent-language-id',
                    slotConfig: {
                        'slot-id': {
                            title: { value: 'parent title' },
                            content: { value: 'parent content' },
                        },
                    },
                },
            ],
        };
        Shopware.Store.get('context').api.languageId = 'child-language-id';
        Shopware.Store.get('context').api.language = { parentId: 'parent-language-id' };

        expect(wrapper.vm.inheritedSlotConfig).toStrictEqual({
            'slot-id': {
                title: { value: 'parent title' },
                content: { value: 'child content' },
            },
        });
    });

    it('should prefer the live current-language slotConfig over the stale translation row', async () => {
        const wrapper = await createWrapper('sw.category.detail.cms');

        Shopware.Store.get('swCategoryDetail').category = {
            slotConfig: null,
            translations: [
                {
                    languageId: 'child-language-id',
                    slotConfig: {
                        'slot-id': {
                            content: {
                                value: 'stale child override',
                            },
                        },
                    },
                },
                {
                    languageId: 'parent-language-id',
                    slotConfig: {
                        'slot-id': {
                            content: {
                                value: 'parent override',
                            },
                        },
                    },
                },
            ],
        };
        Shopware.Store.get('context').api.languageId = 'child-language-id';
        Shopware.Store.get('context').api.language = { parentId: 'parent-language-id' };

        expect(wrapper.vm.inheritedSlotConfig).toStrictEqual({
            'slot-id': {
                content: {
                    value: 'parent override',
                },
            },
        });
    });
});
