/**
 * @sw-package inventory
 */
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import type Repository from 'src/core/data/repository.data';

/** @private */
export type DocumentChanges = { title?: string | null; mediaId?: EntityKey<'media'> | null };

/** @private */
export type DocumentVariantOperation = { mediaId: string; title: string | null; changes?: DocumentChanges };

const { Context, Utils } = Shopware;
const { Criteria } = Shopware.Data;

const PAGE_SIZE = 100;

/** @private */
export default class ProductDocumentVariantService {
    private readonly documentRepository: Repository<'product_document'>;

    constructor(repositoryFactory: RepositoryFactory) {
        this.documentRepository = repositoryFactory.create('product_document');
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

        const removesDocuments = languageId === Context.api.systemLanguageId;
        const deletedIds: string[] = [];
        matches.forEach(({ operation, documents: matched }) => {
            if (!operation.changes && removesDocuments) {
                deletedIds.push(...matched.map(({ id }) => id));
                return;
            }

            const changes = Utils.object.pick(operation.changes ?? { title: null, mediaId: null }, ['title', 'mediaId']);
            matched.forEach((document) => Object.assign(document, changes));
        });

        await this.documentRepository.sync(documents, context);
        if (deletedIds.length) {
            await this.documentRepository.syncDeleted(deletedIds, context);
        }

        return variantIds;
    }

    private async loadVariantDocuments(
        productId: string,
        operations: DocumentVariantOperation[],
        context: typeof Context.api,
    ): Promise<EntityCollection<'product_document'>> {
        let page = 1;
        const criteria = new Criteria(page, PAGE_SIZE);
        criteria.setTotalCountMode(0);
        criteria.addFilter(Criteria.equals('product.parentId', productId));
        criteria.addFilter(Criteria.equalsAny('mediaId', [...new Set(operations.map(({ mediaId }) => mediaId))]));
        criteria.addSorting(Criteria.sort('id'));

        const documents = await this.documentRepository.search(criteria, context);
        let result = documents;
        while (result.length === PAGE_SIZE) {
            page += 1;
            criteria.setPage(page);
            result = await this.documentRepository.search(criteria, context);
            result.forEach((document) => documents.add(document));
        }

        return documents;
    }
}
