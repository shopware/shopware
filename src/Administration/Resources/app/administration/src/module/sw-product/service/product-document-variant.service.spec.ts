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
        versionId: 'live-version',
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
        syncDeleted: jest.fn(() => Promise.resolve()),
    };
    const repositoryFactory = { create: jest.fn(() => repository) } as unknown as RepositoryFactory;
    const syncService = { sync: jest.fn(() => Promise.resolve()) };

    return {
        service: new ProductDocumentVariantService(repositoryFactory, syncService),
        repository,
        syncService,
        searchedCriteria,
    };
}

describe('src/module/sw-product/service/product-document-variant.service', () => {
    it('sends nothing without operations', async () => {
        const { service, repository } = createService([]);

        expect(await service.sync('product-id', [])).toEqual([]);
        expect(repository.search).not.toHaveBeenCalled();
    });

    it('dry-runs against the variant copies with the same file and exactly the same title', async () => {
        const { service, repository, syncService, searchedCriteria } = createService([
            [
                createDocument('variant-a', 'Manual'),
                createDocument('variant-a', 'Manual', 'other-media'),
                createDocument('variant-b', 'Manual'),
                createDocument('variant-c', 'MANUAL'),
                createDocument('variant-d', 'Manual '),
            ],
        ]);

        const variantIds = await service.sync('product-id', [{ type: 'remove', mediaId: MEDIA_ID, title: 'Manual' }], true);

        expect(variantIds).toEqual(['variant-a', 'variant-b']);
        expect(searchedCriteria[0].filter).toEqual([
            { type: 'equals', field: 'product.parentId', value: 'product-id' },
            { type: 'equalsAny', field: 'mediaId', value: MEDIA_ID },
        ]);
        expect(syncService.sync).not.toHaveBeenCalled();
        expect(repository.syncDeleted).not.toHaveBeenCalled();
    });

    it('removes matching copies and applies title and file changes in one request each in the default language', async () => {
        const removed = createDocument('variant-a', 'Manual');
        const renamed = createDocument('variant-b', 'Datasheet', 'datasheet-media');
        const { service, repository, syncService } = createService([[removed, renamed]]);

        await service.sync('product-id', [
            { type: 'remove', mediaId: MEDIA_ID, title: 'Manual' },
            {
                type: 'update',
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
        expect(syncService.sync).toHaveBeenCalledTimes(1);
        expect(syncService.sync).toHaveBeenCalledWith(
            {
                'product-document-variants': {
                    entity: 'product_document',
                    action: 'upsert',
                    payload: [{ id: renamed.id, versionId: 'live-version', title: 'Safety datasheet' }],
                },
            },
            {},
            { 'sw-language-id': Shopware.Context.api.systemLanguageId },
        );
    });

    it('clears the language version of matching copies in another language', async () => {
        const copy = createDocument('variant-a', 'Handbuch');
        const { service, repository, syncService } = createService([[copy]]);

        await service.sync(
            'product-id',
            [{ type: 'update', mediaId: MEDIA_ID, title: 'Handbuch', changes: { title: null, mediaId: null } }],
            false,
            GERMAN_LANGUAGE_ID,
        );

        expect(syncService.sync).toHaveBeenCalledWith(
            {
                'product-document-variants': {
                    entity: 'product_document',
                    action: 'upsert',
                    payload: [{ id: copy.id, versionId: 'live-version', title: null, mediaId: null }],
                },
            },
            {},
            { 'sw-language-id': GERMAN_LANGUAGE_ID },
        );
        expect(repository.syncDeleted).not.toHaveBeenCalled();
    });

    it('loads all variant copies in one request with only the fields it compares and writes', async () => {
        const { service, repository, searchedCriteria } = createService([[createDocument('variant-a', 'Manual')]]);

        await service.sync('product-id', [{ type: 'remove', mediaId: MEDIA_ID, title: 'Manual' }], true);

        expect(repository.search).toHaveBeenCalledTimes(1);
        expect(searchedCriteria[0]).toMatchObject({
            fields: ['productId', 'mediaId', 'title'],
            includes: {
                product_document: [
                    'id',
                    'versionId',
                    'productId',
                    'mediaId',
                    'title',
                    'translated',
                ],
            },
            'total-count-mode': 0,
        });
        expect(searchedCriteria[0]).not.toHaveProperty('limit');
    });
});
