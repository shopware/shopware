/**
 * @sw-package inventory
 */
import EntityCollection from 'src/core/data/entity-collection.data';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import type CriteriaType from 'src/core/data/criteria.data';
import ProductDocumentVariantService from './product-document-variant.service';

const MEDIA_ID = 'media-id';
const GERMAN_LANGUAGE_ID = 'german-language-id' as EntityKey<'language'>;

function createDocument(productId: string, title: string, mediaId = MEDIA_ID): Entity<'product_document'> {
    return {
        id: `${productId}-${title}`,
        productId,
        title,
        mediaId,
        translated: { title, mediaId },
    } as unknown as Entity<'product_document'>;
}

function createService(pages: Entity<'product_document'>[][]) {
    const searchedCriteria: ReturnType<CriteriaType['parse']>[] = [];
    const repository = {
        search: jest.fn((criteria: CriteriaType) => {
            searchedCriteria.push(criteria.parse());
            const documents = pages[(criteria.page ?? 1) - 1] ?? [];
            return Promise.resolve(
                new EntityCollection('/product-document', 'product_document', Shopware.Context.api, null, documents),
            );
        }),
        sync: jest.fn(() => Promise.resolve()),
        syncDeleted: jest.fn(() => Promise.resolve()),
    };
    const repositoryFactory = { create: jest.fn(() => repository) } as unknown as RepositoryFactory;

    return { service: new ProductDocumentVariantService(repositoryFactory), repository, searchedCriteria };
}

describe('src/module/sw-product/service/product-document-variant.service', () => {
    it('sends nothing without operations', async () => {
        const { service, repository } = createService([]);

        expect(await service.sync('product-id', [])).toEqual([]);
        expect(repository.search).not.toHaveBeenCalled();
    });

    it('dry-runs against the variant copies with the same file and exactly the same title', async () => {
        const { service, repository, searchedCriteria } = createService([
            [
                createDocument('variant-a', 'Manual'),
                createDocument('variant-a', 'Manual', 'other-media'),
                createDocument('variant-b', 'Manual'),
                createDocument('variant-c', 'MANUAL'),
                createDocument('variant-d', 'Manual '),
            ],
        ]);

        const variantIds = await service.sync('product-id', [{ mediaId: MEDIA_ID, title: 'Manual' }], true);

        expect(variantIds).toEqual(['variant-a', 'variant-b']);
        expect(searchedCriteria[0].filter).toEqual([
            { type: 'equals', field: 'product.parentId', value: 'product-id' },
            { type: 'equalsAny', field: 'mediaId', value: MEDIA_ID },
        ]);
        expect(repository.sync).not.toHaveBeenCalled();
        expect(repository.syncDeleted).not.toHaveBeenCalled();
    });

    it('removes matching copies and applies title and file changes in one request each in the default language', async () => {
        const removed = createDocument('variant-a', 'Manual');
        const renamed = createDocument('variant-b', 'Datasheet', 'datasheet-media');
        const { service, repository } = createService([[removed, renamed]]);

        await service.sync('product-id', [
            { mediaId: MEDIA_ID, title: 'Manual' },
            {
                mediaId: 'datasheet-media',
                title: 'Datasheet',
                changes: { title: 'Safety datasheet', position: 3 } as unknown as { title: string },
            },
        ]);

        expect(repository.syncDeleted).toHaveBeenCalledTimes(1);
        expect(repository.syncDeleted).toHaveBeenCalledWith(
            [removed.id],
            expect.objectContaining({ languageId: Shopware.Context.api.systemLanguageId }),
        );
        expect(repository.sync).toHaveBeenCalledTimes(1);
        expect(renamed).toMatchObject({ title: 'Safety datasheet', mediaId: 'datasheet-media' });
        expect(renamed).not.toHaveProperty('position');
        expect(removed.title).toBe('Manual');
    });

    it('clears the language version of matching copies in another language', async () => {
        const copy = createDocument('variant-a', 'Handbuch');
        const { service, repository } = createService([[copy]]);

        await service.sync('product-id', [{ mediaId: MEDIA_ID, title: 'Handbuch' }], false, GERMAN_LANGUAGE_ID);

        expect(copy).toMatchObject({ title: null, mediaId: null });
        expect(repository.sync).toHaveBeenCalledWith(
            expect.anything(),
            expect.objectContaining({ languageId: GERMAN_LANGUAGE_ID }),
        );
        expect(repository.syncDeleted).not.toHaveBeenCalled();
    });

    it('loads all pages of variant documents', async () => {
        const fullPage = Array.from({ length: 100 }, (_, index) => createDocument(`variant-${index}`, 'Manual'));
        const { service, repository } = createService([fullPage, [createDocument('variant-last', 'Manual')]]);

        const variantIds = await service.sync('product-id', [{ mediaId: MEDIA_ID, title: 'Manual' }], true);

        expect(repository.search).toHaveBeenCalledTimes(2);
        expect(variantIds).toHaveLength(101);
        expect(variantIds.at(-1)).toBe('variant-last');
    });
});
