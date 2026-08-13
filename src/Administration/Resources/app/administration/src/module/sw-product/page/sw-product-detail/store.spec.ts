/**
 * @sw-package inventory
 */
import { createPinia, setActivePinia } from 'pinia';

const taxes = [
    { id: 'tax-0', name: '0%', taxRate: 0, position: 1 },
    { id: 'tax-19', name: '19%', taxRate: 19, position: 2 },
] as EntitySchema.tax[];

function getStore() {
    return Shopware.Store.get('swProductDetail');
}

describe('sw-product-detail.store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        getStore().$reset();
    });

    describe('setTaxes', () => {
        it('assigns the first tax rate to a root product without a tax rate', () => {
            const store = getStore();
            store.product = { id: 'product-id', taxId: null } as EntitySchema.product;

            store.setTaxes(taxes);

            expect(store.product.taxId).toBe('tax-0');
        });

        it('keeps the tax rate of a root product that already has one', () => {
            const store = getStore();
            store.product = { id: 'product-id', taxId: 'tax-19' } as EntitySchema.product;

            store.setTaxes(taxes);

            expect(store.product.taxId).toBe('tax-19');
        });

        // A variant inherits the tax rate of its parent, so its own taxId is null. The taxes
        // are loaded in parallel to the product, therefore they can arrive while the parent
        // product is still being fetched. The variant must not be treated as a root product
        // in that window, otherwise it silently loses its inheritance.
        it('does not assign a tax rate to a variant while its parent product is still loading', () => {
            const store = getStore();
            store.product = { id: 'variant-id', parentId: 'parent-id', taxId: null } as EntitySchema.product;
            store.parentProduct = {} as EntitySchema.product;

            store.setTaxes(taxes);

            expect(store.product.taxId).toBeNull();
        });
    });

    describe('productTaxRate', () => {
        it('returns the tax rate of the product', () => {
            const store = getStore();
            store.taxes = taxes;
            store.product = { id: 'product-id', taxId: 'tax-19' } as EntitySchema.product;

            expect(store.productTaxRate).toEqual(taxes[1]);
        });

        it('returns the inherited tax rate of the parent product', () => {
            const store = getStore();
            store.taxes = taxes;
            store.product = { id: 'variant-id', parentId: 'parent-id', taxId: null } as EntitySchema.product;
            store.parentProduct = { id: 'parent-id', taxId: 'tax-19' } as EntitySchema.product;

            expect(store.productTaxRate).toEqual(taxes[1]);
        });

        it('returns an empty object when neither the product nor its parent has a tax rate', () => {
            const store = getStore();
            store.taxes = taxes;
            store.product = { id: 'variant-id', parentId: 'parent-id', taxId: null } as EntitySchema.product;
            store.parentProduct = {} as EntitySchema.product;

            expect(store.productTaxRate).toEqual({});
        });

        it('returns an empty object when the tax rate is unknown', () => {
            const store = getStore();
            store.taxes = taxes;
            store.product = { id: 'product-id', taxId: 'deleted-tax' } as EntitySchema.product;

            expect(store.productTaxRate).toEqual({});
        });
    });
});
