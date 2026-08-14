/**
 * @sw-package discovery
 */
import { useRoute } from 'vue-router';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';
import { useCmsState } from './use-cms-state';

jest.mock('vue-router', () => {
    const actual: object = jest.requireActual('vue-router');

    return { ...actual, useRoute: jest.fn(() => ({ name: '' })) };
});

const useRouteMock = useRoute as unknown as jest.Mock;

function onRoute(name: string): void {
    useRouteMock.mockReturnValue({ name });
}

describe('src/module/sw-cms/composables/use-cms-state', () => {
    let initialLanguageId: string | null;
    let initialLanguage: { name: string; parentId?: string } | null;

    beforeAll(async () => {
        await setupCmsEnvironment();
    });

    beforeEach(() => {
        onRoute('');
        initialLanguageId = Shopware.Store.get('context').api.languageId;
        initialLanguage = Shopware.Store.get('context').api.language;
    });

    afterEach(() => {
        Shopware.Store.get('cmsPage').resetCmsPageState();
        Shopware.Store.get('context').api.languageId = initialLanguageId;
        Shopware.Store.get('context').api.language = initialLanguage;
    });

    it('reads and writes the selection through the shared cms page store', () => {
        const { selectedBlock, selectedSection, currentDeviceView, isSystemDefaultLanguage } = useCmsState();
        const store = Shopware.Store.get('cmsPage');

        const block = { id: 'block-1234' } as Entity<'cms_block'>;
        selectedBlock.value = block;
        expect(selectedBlock.value).toEqual(block);
        expect(store.selectedBlock).toEqual(block);

        const section = { id: 'section-1234' } as Entity<'cms_section'>;
        selectedSection.value = section;
        expect(selectedSection.value).toEqual(section);
        expect(store.selectedSection).toEqual(section);

        expect(currentDeviceView.value).toBe('desktop');
        store.setCurrentCmsDeviceView('mobile');
        expect(currentDeviceView.value).toBe('mobile');

        expect(isSystemDefaultLanguage.value).toBe(true);
        store.setIsSystemDefaultLanguage(false);
        expect(isSystemDefaultLanguage.value).toBe(false);
    });

    it('resolves the content entity from the current route', () => {
        onRoute('sw.category.detail.cms');
        const category = { id: 'category-1', name: 'Test Category', translations: [] };
        Shopware.Store.get('swCategoryDetail').category = category as never;

        const { contentEntity } = useCmsState();

        expect(contentEntity.value).toMatchObject(category);
    });

    it('returns no content entity outside the category, product and landing page routes', () => {
        const { contentEntity } = useCmsState();

        expect(contentEntity.value).toBeNull();
    });

    it('prefers the parent translation slotConfig for inherited overrides', () => {
        onRoute('sw.category.detail.cms');
        const inheritedSlotConfig = { 'slot-id': { content: { value: 'inherited' } } };

        Shopware.Store.get('swCategoryDetail').category = {
            translations: [
                {
                    languageId: 'parent-language-id',
                    slotConfig: inheritedSlotConfig,
                },
            ],
        } as never;
        Shopware.Store.get('context').api.languageId = 'child-language-id';
        Shopware.Store.get('context').api.language = { parentId: 'parent-language-id' } as never;

        const { inheritedSlotConfig: resolved } = useCmsState();

        expect(resolved.value).toStrictEqual(inheritedSlotConfig);
    });

    it('keeps parent-language fields when the child slot overrides only one of them', () => {
        onRoute('sw.category.detail.cms');

        Shopware.Store.get('swCategoryDetail').category = {
            slotConfig: {
                'slot-id': { content: { value: 'child content' } },
            },
            translations: [
                {
                    languageId: 'parent-language-id',
                    slotConfig: {
                        'slot-id': {
                            content: { value: 'parent content' },
                            media: { value: 'parent media' },
                        },
                    },
                },
            ],
        } as never;
        Shopware.Store.get('context').api.languageId = 'child-language-id';
        Shopware.Store.get('context').api.language = { parentId: 'parent-language-id' } as never;

        const { inheritedSlotConfig } = useCmsState();

        expect(inheritedSlotConfig.value).toStrictEqual({
            'slot-id': {
                content: { value: 'child content' },
                media: { value: 'parent media' },
            },
        });
    });

    it('returns no slot config for a language the content entity has no translation for', () => {
        onRoute('sw.category.detail.cms');
        Shopware.Store.get('swCategoryDetail').category = { translations: [] } as never;

        const { getSlotConfigForLanguage } = useCmsState();

        expect(getSlotConfigForLanguage('unknown-language-id')).toBeNull();
        expect(getSlotConfigForLanguage(null)).toBeNull();
    });
});
