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
export function getMatchingVariantDocuments(variant: Entity<'product'>, mediaId: string) {
    return variant.documents?.filter(
        (document) => document.productId === variant.id && (document.mediaId || document.translated?.mediaId) === mediaId,
    );
}
