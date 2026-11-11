/**
 * @sw-package discovery
 */

/** @private */
export function collectProductUsageOwners(media: Pick<Entity<'media'>, 'productMedia' | 'productDocumentTranslations'>) {
    const products = new Map<string, Entity<'product'> | null>();
    const assignments = [
        ...(media.productMedia ?? []),
        ...(media.productDocumentTranslations ?? []).map(({ productDocument }) => productDocument),
    ];

    assignments.forEach((assignment) => {
        if (!assignment?.productId) {
            return;
        }
        const { productId, product } = assignment;

        if (!products.has(productId)) {
            products.set(productId, null);
        }
        if (product?.id === productId) {
            products.set(productId, product);
        }
    });

    return products;
}
