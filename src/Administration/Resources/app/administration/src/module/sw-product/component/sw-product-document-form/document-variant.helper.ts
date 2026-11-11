/**
 * @sw-package inventory
 */
import type Repository from 'src/core/data/repository.data';

/** @private */
export async function loadDocumentVariants(
    repository: Repository<'product'>,
    productId: string,
    mediaId: string,
    context: typeof Shopware.Context.api,
): Promise<Entity<'product'>[]> {
    const { Criteria } = Shopware.Data;
    const variants: Entity<'product'>[] = [];
    let page = 1;
    let result: EntityCollection<'product'>;
    do {
        const criteria = new Criteria(page, 100);
        criteria.setTotalCountMode(0);
        criteria.addSorting(Criteria.sort('id'));
        criteria.addFilter(Criteria.equals('parentId', productId));
        criteria.addFilter(Criteria.equals('documents.mediaId', mediaId));
        criteria.addAssociation('documents.translations.media');
        criteria.getAssociation('documents').addSorting(Criteria.sort('position', 'ASC'));
        result = await repository.search(criteria, context);
        variants.push(...result);
        page += 1;
    } while (result.length === 100);
    return variants;
}

/** @private */
export function getMatchingVariantDocuments(variant: Entity<'product'>, original: Entity<'product_document'>) {
    const mediaId = original.mediaId || original.translated?.mediaId;
    const title = original.title || original.translated?.title || '';
    return variant.documents?.filter(
        (document) =>
            document.productId === variant.id &&
            (document.mediaId || document.translated?.mediaId) === mediaId &&
            (document.title || document.translated?.title || '') === title,
    );
}

/** @private */
export function getDocumentVariantCandidates(
    variants: Entity<'product'>[],
    children: EntityCollection<'product'>,
    original: Entity<'product_document'>,
) {
    const candidates = new Map(variants.map((variant) => [variant.id, variant]));
    children.forEach((variant) => candidates.set(variant.id, variant));
    return Array.from(candidates.values()).flatMap((variant) => {
        const documents = variant.documents;
        const matching = getMatchingVariantDocuments(variant, original);
        return documents && matching?.length ? [{ variant, documents, matching }] : [];
    });
}
