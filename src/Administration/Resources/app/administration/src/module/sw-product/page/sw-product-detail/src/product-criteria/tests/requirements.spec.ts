import { createDefaultFeatureSetCriteria, createProductCriteria, createPropertyCriteria } from '../private';

/**
 * @sw-package inventory
 */

/**
 * The request body a criteria sends to the API, which is what the server sees.
 */
function toRequest(criteria: ReturnType<typeof createProductCriteria>): Record<string, unknown> {
    return criteria.parse() as Record<string, unknown>;
}

// The product detail page loads a product once with everything its tabs show, so switching tabs needs no new request.
// A variant's parent loads with the same criteria, so inherited data looks like the variant's own.
// The properties and the default feature set of a new product load with criteria of their own.
describe('the criteria of the product detail page', () => {
    /**
     * @requirement A merchant opens one product and sees its media, prices, SEO URLs and cross selling without a second request.
     */
    it('loads one product with the associations its tabs show', () => {
        // ACT
        const request = toRequest(createProductCriteria());

        // ASSERT
        expect(request).toMatchObject({ page: 1, limit: 1, 'total-count-mode': 0 });
        expect(Object.keys(request.associations as object)).toEqual(
            expect.arrayContaining([
                'media',
                'prices',
                'seoUrls',
                'crossSellings',
                'cover',
                'visibilities',
                'manufacturer',
                'downloads',
            ]),
        );
    });

    /**
     * @requirement A merchant sees media, advanced prices and cross selling products in the order they arranged them.
     */
    it('sorts the lists the merchant arranges', () => {
        // ACT
        const { associations } = toRequest(createProductCriteria()) as {
            associations: Record<string, { sort?: unknown[]; associations?: Record<string, { sort?: unknown[] }> }>;
        };

        // ASSERT
        expect(associations.media.sort).toEqual([expect.objectContaining({ field: 'position', order: 'ASC' })]);
        expect(associations.prices.sort).toEqual([expect.objectContaining({ field: 'quantityStart', order: 'ASC' })]);
        expect(associations.crossSellings.associations?.assignedProducts.sort).toEqual([
            expect.objectContaining({ field: 'position', order: 'ASC' }),
        ]);
    });

    /**
     * @requirement A merchant sees only the canonical SEO URL of the product, not its history.
     */
    it('loads only canonical SEO URLs', () => {
        // ACT
        const { associations } = toRequest(createProductCriteria()) as {
            associations: Record<string, { filter?: unknown[] }>;
        };

        // ASSERT
        expect(associations.seoUrls.filter).toEqual([
            expect.objectContaining({ type: 'equals', field: 'isCanonical', value: true }),
        ]);
    });

    /**
     * @requirement A merchant editing a variant sees the properties assigned to the variant itself, sorted by name.
     */
    it('loads the properties assigned to the product itself, sorted by name', () => {
        // ACT
        const request = toRequest(createPropertyCriteria(['option-1', 'option-2'], 'variant-id'));

        // ASSERT
        expect(request).toMatchObject({
            ids: 'option-1|option-2',
            sort: [expect.objectContaining({ field: 'name', order: 'ASC', naturalSorting: true })],
            filter: [expect.objectContaining({ type: 'equals', field: 'productProperties.id', value: 'variant-id' })],
        });
    });

    /**
     * @requirement A merchant sees all properties of a parent product, which its variants inherit.
     */
    it('loads every property of a parent product', () => {
        // ACT
        const request = toRequest(createPropertyCriteria(['option-1']));

        // ASSERT
        expect(request.filter).toBeUndefined();
    });

    /**
     * @requirement A new product starts with the feature set the shop shipped with, even when merchants added more.
     */
    it('picks the oldest feature set named Default or Standard', () => {
        // ACT
        const request = toRequest(createDefaultFeatureSetCriteria());

        // ASSERT
        expect(request).toMatchObject({
            limit: 1,
            sort: [expect.objectContaining({ field: 'createdAt', order: 'ASC' })],
            filter: [expect.objectContaining({ type: 'equalsAny', field: 'name', value: 'Default|Standard' })],
        });
    });
});
