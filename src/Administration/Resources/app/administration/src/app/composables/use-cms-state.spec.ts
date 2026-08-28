/**
 * @sw-package discovery
 */
import useCmsState from './use-cms-state';

const route: { name: string | null } = { name: null };

jest.mock('vue-router', () => ({
    useRoute: () => route,
}));

// The store module only exists to register `cmsPage` for real components; the spec stubs the store
// registry itself.
jest.mock('src/module/sw-cms/store/cms-page.store', () => ({}));

type StoreStubs = {
    cmsPage?: Record<string, unknown>;
    swCategoryDetail?: Record<string, unknown> | Error;
    swProductDetail?: Record<string, unknown> | Error;
    languageId?: string;
    parentLanguageId?: string | null;
};

function stubShopware(stubs: StoreStubs = {}): { setSelectedBlock: jest.Mock; setSelectedSection: jest.Mock } {
    const setSelectedBlock = jest.fn();
    const setSelectedSection = jest.fn();

    const stores: Record<string, unknown> = {
        cmsPage: {
            selectedBlock: null,
            selectedSection: null,
            currentCmsDeviceView: 'desktop',
            isSystemDefaultLanguage: true,
            setSelectedBlock,
            setSelectedSection,
            ...stubs.cmsPage,
        },
        context: {
            api: {
                languageId: stubs.languageId ?? 'current-language',
                language: { parentId: stubs.parentLanguageId ?? null },
            },
        },
        swCategoryDetail: stubs.swCategoryDetail,
        swProductDetail: stubs.swProductDetail,
    };

    window.Shopware = {
        Store: {
            get: jest.fn((id: string) => {
                const store = stores[id];

                if (store instanceof Error) {
                    throw store;
                }

                return store;
            }),
        },
        Utils: { object: { cloneDeep: (value: unknown) => structuredClone(value) } },
    } as unknown as typeof Shopware;

    return { setSelectedBlock, setSelectedSection };
}

describe('src/app/composables/use-cms-state', () => {
    beforeEach(() => {
        route.name = null;
    });

    it('reads the editor state off the cmsPage store', () => {
        stubShopware({ cmsPage: { currentCmsDeviceView: 'mobile', isSystemDefaultLanguage: false } });
        const { currentDeviceView, isSystemDefaultLanguage } = useCmsState();

        expect(currentDeviceView.value).toBe('mobile');
        expect(isSystemDefaultLanguage.value).toBe(false);
    });

    it('writes the selection back through the store actions', () => {
        const { setSelectedBlock, setSelectedSection } = stubShopware();
        const { selectedBlock, selectedSection } = useCmsState();
        const block = { id: 'block-1' } as Entity<'cms_block'>;
        const section = { id: 'section-1' } as Entity<'cms_section'>;

        selectedBlock.value = block;
        selectedSection.value = section;

        expect(setSelectedBlock).toHaveBeenCalledWith(block);
        expect(setSelectedSection).toHaveBeenCalledWith(section);
    });

    it.each([
        [
            'sw.category.detail',
            'category-1',
        ],
        [
            'sw.category.landingPageDetail.base',
            'landing-page-1',
        ],
        [
            'sw.product.detail',
            'product-1',
        ],
    ])('resolves the content entity of the %s route', (routeName, expectedId) => {
        stubShopware({
            swCategoryDetail: { category: { id: 'category-1' }, landingPage: { id: 'landing-page-1' } },
            swProductDetail: { product: { id: 'product-1' } },
        });
        route.name = routeName;
        const { contentEntity } = useCmsState();

        expect(contentEntity.value?.id).toBe(expectedId);
    });

    it('has no content entity outside the category and product routes', () => {
        stubShopware({ swCategoryDetail: { category: { id: 'category-1' } } });
        route.name = 'sw.cms.detail';
        const { contentEntity } = useCmsState();

        expect(contentEntity.value).toBeNull();
    });

    // The detail stores only exist while their module is loaded.
    it('treats an unregistered detail store as no entity', () => {
        stubShopware({ swCategoryDetail: new Error('store swCategoryDetail not found') });
        route.name = 'sw.category.detail';
        const { category, contentEntity } = useCmsState();

        expect(category.value).toBeNull();
        expect(contentEntity.value).toBeNull();
    });

    it('reads the slot config of the current language off the content entity', () => {
        stubShopware({
            languageId: 'de-DE',
            swCategoryDetail: { category: { id: 'category-1', slotConfig: { slot: { value: 'current' } } } },
        });
        route.name = 'sw.category.detail';
        const { getSlotConfigForLanguage } = useCmsState();

        expect(getSlotConfigForLanguage('de-DE')).toEqual({ slot: { value: 'current' } });
        expect(getSlotConfigForLanguage(null)).toBeNull();
    });

    it('reads the slot config of another language off its translation', () => {
        stubShopware({
            languageId: 'de-DE',
            swCategoryDetail: {
                category: {
                    id: 'category-1',
                    translations: [{ languageId: 'en-GB', slotConfig: { slot: { value: 'parent' } } }],
                },
            },
        });
        route.name = 'sw.category.detail';
        const { getSlotConfigForLanguage } = useCmsState();

        expect(getSlotConfigForLanguage('en-GB')).toEqual({ slot: { value: 'parent' } });
        expect(getSlotConfigForLanguage('fr-FR')).toBeNull();
    });

    it('merges the inherited slot config field-by-field so a partial override keeps parent fields', () => {
        stubShopware({
            languageId: 'de-DE',
            parentLanguageId: 'en-GB',
            swCategoryDetail: {
                category: {
                    id: 'category-1',
                    slotConfig: { slot: { headline: 'child' } },
                    translations: [
                        {
                            languageId: 'en-GB',
                            slotConfig: { slot: { headline: 'parent', text: 'parent' }, other: { value: 'parent' } },
                        },
                    ],
                },
            },
        });
        route.name = 'sw.category.detail';
        const { inheritedSlotConfig } = useCmsState();

        expect(inheritedSlotConfig.value).toEqual({
            slot: { headline: 'child', text: 'parent' },
            other: { value: 'parent' },
        });
    });

    it('has no inherited slot config when neither language configures a slot', () => {
        stubShopware({ swCategoryDetail: { category: { id: 'category-1' } } });
        route.name = 'sw.category.detail';
        const { inheritedSlotConfig } = useCmsState();

        expect(inheritedSlotConfig.value).toBeNull();
    });
});
