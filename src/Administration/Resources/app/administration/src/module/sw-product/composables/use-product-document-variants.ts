/**
 * @sw-package inventory
 */
import { inject } from 'vue';
import type RepositoryFactory from 'src/core/data/repository-factory.data';

/** @private */
export default function useProductDocumentVariants() {
    const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
    if (!repositoryFactory) {
        throw new Error('repositoryFactory is required for product document variants');
    }

    const productRepository = repositoryFactory.create('product');

    async function loadDocumentVariants(
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
            criteria.addAssociation('documents');
            result = await productRepository.search(criteria, context);
            variants.push(...result);
            page += 1;
        } while (result.length === 100);
        return variants;
    }

    return { loadDocumentVariants, stageVariantDocumentChanges, stageVariantDocumentRemovals };
}

function stageVariantDocumentChanges(
    variants: Entity<'product'>[],
    originalMediaId: EntityKey<'media'>,
    mediaId: EntityKey<'media'>,
    title: string,
): string[] {
    const store = Shopware.Store.get('swProductDetail');
    const children =
        store.product.children || new Shopware.Data.EntityCollection('/product', 'product', Shopware.Context.api);
    const candidates = new Map(variants.map((variant) => [variant.id, variant]));
    children.forEach((variant) => candidates.set(variant.id, variant));
    const updatedVariantIds: string[] = [];
    candidates.forEach((variant) => {
        let changed = false;
        variant.documents?.forEach((copy) => {
            if (copy.productId !== variant.id || (copy.mediaId || copy.translated?.mediaId) !== originalMediaId) {
                return;
            }
            copy.title = title;
            copy.mediaId = mediaId;
            const origin = copy.getOrigin();
            if (origin.title !== title || origin.mediaId !== mediaId) {
                changed = true;
            }
        });
        if (changed) {
            if (!children.has(variant.id)) {
                children.add(variant);
            }
            updatedVariantIds.push(variant.id);
        }
    });
    store.product.children = children;
    return updatedVariantIds;
}

function stageVariantDocumentRemovals(variants: Entity<'product'>[], mediaId: EntityKey<'media'>): void {
    const store = Shopware.Store.get('swProductDetail');
    const children =
        store.product.children || new Shopware.Data.EntityCollection('/product', 'product', Shopware.Context.api);
    const origin = store.product.getOrigin();
    const originChildren =
        origin.children || new Shopware.Data.EntityCollection('/product', 'product', Shopware.Context.api);
    const candidates = new Map(variants.map((variant) => [variant.id, variant]));
    children.forEach((variant) => candidates.set(variant.id, variant));
    candidates.forEach((variant) => {
        const documents = variant.documents;
        const matching = documents?.filter(
            (copy) => copy.productId === variant.id && (copy.mediaId || copy.translated?.mediaId) === mediaId,
        );
        if (!documents || !matching?.length) {
            return;
        }
        // Register loaded children in the origin so the changeset includes nested document deletions.
        if (!variant.isNew() && !originChildren.has(variant.id)) {
            originChildren.add(variant);
        }
        if (!children.has(variant.id)) {
            children.add(variant);
        }
        matching.forEach((copy) => documents.remove(copy.id));
        documents.forEach((copy, index) => {
            copy.position = index;
        });
    });
    store.product.children = children;
    origin.children = originChildren;
}
