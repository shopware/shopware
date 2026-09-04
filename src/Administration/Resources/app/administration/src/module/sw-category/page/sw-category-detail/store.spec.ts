import { createPinia, setActivePinia } from 'pinia';
import type Repository from '../../../../core/data/repository.data';
import type { ContextStore } from '../../../../app/store/context.store';

const newLandingPageMock = {
    cmsPageId: '12345',
};

const existingLandingPageMock = {
    cmsPageId: '67890',
};

const categoriesMock: Record<string, Partial<Entity<'category'>>> = {
    'without-parent': {
        id: '12345' as EntityKey<'category'>,
    },
    'with-parent': {
        id: '67890' as EntityKey<'category'>,
        parentId: 'parent' as EntityKey<'category'>,
    },
    parent: {
        id: '111213' as EntityKey<'category'>,
        footerSalesChannels: [{ typeId: '12345' }] as EntityCollection<'sales_channel'>,
    },
};

const apiContextMock = {} as ContextStore['api'];

const landingPageRepositoryMock = {
    create: jest.fn(() => ({
        ...newLandingPageMock,
    })),
    get: jest.fn(() => Promise.resolve(existingLandingPageMock)),
} as unknown as Repository<'landing_page'>;

const categoryRepositoryMock = {
    get: jest.fn((id: EntityKey<'category'>) => Promise.resolve({ ...categoriesMock[id] })),
} as unknown as Repository<'category'>;

describe('sw-category.store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('should have the initial state', () => {
        const swCategoryDetailStore = Shopware.Store.get('swCategoryDetail');

        expect(swCategoryDetailStore.landingPage).toBeNull();
        expect(swCategoryDetailStore.category).toBeNull();
        expect(swCategoryDetailStore.isCategoryColumn).toBe(false);
        expect(swCategoryDetailStore.customFieldSets).toEqual([]);
        expect(swCategoryDetailStore.landingPagesToDelete).toBeUndefined();
        expect(swCategoryDetailStore.categoriesToDelete).toBeUndefined();
    });

    it('loads an active landing page creating a new one', async () => {
        const swCategoryDetailStore = Shopware.Store.get('swCategoryDetail');

        await swCategoryDetailStore.loadActiveLandingPage({
            id: 'create' as EntityKey<'landing_page'>,
            repository: landingPageRepositoryMock,
            apiContext: apiContextMock,
        });

        // eslint-disable-next-line @typescript-eslint/unbound-method
        expect(landingPageRepositoryMock.create).toHaveBeenCalledWith(apiContextMock);
        expect(swCategoryDetailStore.landingPage).toStrictEqual({ cmsPageId: undefined });
    });

    it('loads an active landing page loading an existing one', async () => {
        const swCategoryDetailStore = Shopware.Store.get('swCategoryDetail');

        await swCategoryDetailStore.loadActiveLandingPage({
            id: '67890' as EntityKey<'landing_page'>,
            repository: landingPageRepositoryMock,
            apiContext: apiContextMock,
        });

        // eslint-disable-next-line @typescript-eslint/unbound-method
        expect(landingPageRepositoryMock.get).toHaveBeenCalledWith('67890', apiContextMock, expect.anything());
        expect(swCategoryDetailStore.landingPage).toStrictEqual(existingLandingPageMock);
    });

    it('loads an active category', async () => {
        const swCategoryDetailStore = Shopware.Store.get('swCategoryDetail');

        await swCategoryDetailStore.loadActiveCategory({
            id: 'without-parent' as EntityKey<'category'>,
            repository: categoryRepositoryMock,
            apiContext: apiContextMock,
        });

        // eslint-disable-next-line @typescript-eslint/unbound-method
        expect(categoryRepositoryMock.get).toHaveBeenCalledWith('without-parent', apiContextMock, expect.anything());
        expect(swCategoryDetailStore.isCategoryColumn).toBe(false);
        expect(swCategoryDetailStore.category).toStrictEqual(categoriesMock['without-parent']);
    });

    it('loads an active category with parent', async () => {
        const swCategoryDetailStore = Shopware.Store.get('swCategoryDetail');

        await swCategoryDetailStore.loadActiveCategory({
            id: 'with-parent' as EntityKey<'category'>,
            repository: categoryRepositoryMock,
            apiContext: apiContextMock,
        });

        // eslint-disable-next-line @typescript-eslint/unbound-method
        expect(categoryRepositoryMock.get).toHaveBeenCalledWith('with-parent', apiContextMock, expect.anything());
        expect(swCategoryDetailStore.isCategoryColumn).toBe(true);
        expect(swCategoryDetailStore.category).toStrictEqual({
            ...categoriesMock['with-parent'],
            parent: categoriesMock.parent,
        });
    });
});
