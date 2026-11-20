/**
 * @sw-package inventory
 */
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import type Repository from 'src/core/data/repository.data';

/** @private */
export type DocumentChanges = { title?: string | null; mediaId?: EntityKey<'media'> | null };

/** @private */
export type DocumentVariantOperation = { mediaId: string; title: string | null } & (
    | { type: 'update'; changes: DocumentChanges }
    | { type: 'remove' }
);

type SyncService = {
    sync: (payload: object, additionalParams?: object, additionalHeaders?: Record<string, string>) => Promise<unknown>;
};

const { Context, Utils } = Shopware;
const { Criteria } = Shopware.Data;

/** @private */
export default class ProductDocumentVariantService {
    private readonly documentRepository: Repository<'product_document'>;

    private readonly syncService: SyncService;

    constructor(repositoryFactory: RepositoryFactory, syncService: SyncService) {
        this.documentRepository = repositoryFactory.create('product_document');
        this.syncService = syncService;
    }

    async sync(
        productId: string,
        operations: DocumentVariantOperation[],
        dryRun = false,
        languageId = Context.api.languageId,
    ): Promise<string[]> {
        if (!operations.length) {
            return [];
        }

        const context = { ...Context.api, languageId };
        const documents = await this.loadVariantDocuments(productId, operations, context);
        const matches = operations.map((operation) => ({
            operation,
            documents: documents.filter(
                ({ translated }) =>
                    translated?.mediaId === operation.mediaId && (translated?.title ?? null) === operation.title,
            ),
        }));
        const variantIds = [...new Set(matches.flatMap((match) => match.documents.map(({ productId: id }) => id)))];

        if (dryRun) {
            return variantIds;
        }

        const deletedIds: string[] = [];
        const updates: object[] = [];
        matches.forEach(({ operation, documents: matched }) => {
            if (operation.type === 'remove') {
                deletedIds.push(...matched.map(({ id }) => id));
                return;
            }

            const changes = Utils.object.pick(operation.changes, ['title', 'mediaId']);
            updates.push(...matched.map(({ id, versionId }) => ({ id, versionId, ...changes })));
        });

        if (updates.length) {
            await this.syncService.sync(
                { 'product-document-variants': { entity: 'product_document', action: 'upsert', payload: updates } },
                {},
                languageId ? { 'sw-language-id': languageId } : {},
            );
        }
        if (deletedIds.length) {
            await this.documentRepository.syncDeleted(deletedIds, context);
        }

        return variantIds;
    }

    private loadVariantDocuments(
        productId: string,
        operations: DocumentVariantOperation[],
        context: typeof Context.api,
    ): Promise<EntityCollection<'product_document'>> {
        const criteria = new Criteria(1, null);
        criteria.setTotalCountMode(0);
        criteria.addFields('productId', 'mediaId', 'title');
        criteria.addIncludes({
            product_document: [
                'id',
                'versionId',
                'productId',
                'mediaId',
                'title',
                'translated',
            ],
        });
        criteria.addFilter(Criteria.equals('product.parentId', productId));
        criteria.addFilter(Criteria.equalsAny('mediaId', [...new Set(operations.map(({ mediaId }) => mediaId))]));

        return this.documentRepository.search(criteria, context);
    }
}
