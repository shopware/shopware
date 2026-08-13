/**
 * @sw-package inventory
 */
import { createPinia, setActivePinia } from 'pinia';

const taxes = [
    { id: 'tax-0', name: '0%', taxRate: 0, position: 1 },
    { id: 'tax-19', name: '19%', taxRate: 19, position: 2 },
] as unknown as EntitySchema.tax[];

function getStore() {
    return Shopware.Store.get('swProductDetail');
}

// The product entities are reduced to the properties the tax rate handling reads, so the
// scenario of each test stays visible. They are cast through `unknown` because a full
// product entity is not needed to describe the behaviour under test.
function setProduct(product: Record<string, unknown>) {
    const store = getStore();

    store.product = product as unknown as typeof store.product;
}

function setParentProduct(parentProduct: Record<string, unknown>) {
    const store = getStore();

    store.parentProduct = parentProduct as unknown as typeof store.parentProduct;
}

describe('sw-product-detail.store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        getStore().$reset();
    });

    describe('setTaxes', () => {
        it('assigns the first tax rate to a root product without a tax rate', () => {
            setProduct({ id: 'product-id', taxId: null });

            getStore().setTaxes(taxes);

            expect(getStore().product.taxId).toBe('tax-0');
        });

        it('keeps the tax rate of a root product that already has one', () => {
            setProduct({ id: 'product-id', taxId: 'tax-19' });

            getStore().setTaxes(taxes);

            expect(getStore().product.taxId).toBe('tax-19');
        });

        // A variant inherits the tax rate of its parent, so its own taxId is null. The taxes
        // are loaded in parallel to the product, therefore they can arrive while the parent
        // product is still being fetched. The variant must not be treated as a root product
        // in that window, otherwise it silently loses its inheritance.
        it('does not assign a tax rate to a variant while its parent product is still loading', () => {
            setProduct({ id: 'variant-id', parentId: 'parent-id', taxId: null });
            setParentProduct({});

            getStore().setTaxes(taxes);

            expect(getStore().product.taxId).toBeNull();
        });
    });

    describe('productTaxRate', () => {
        it('returns the tax rate of the product', () => {
            getStore().taxes = taxes;
            setProduct({ id: 'product-id', taxId: 'tax-19' });

            expect(getStore().productTaxRate).toEqual(taxes[1]);
        });

        it('returns the inherited tax rate of the parent product', () => {
            getStore().taxes = taxes;
            setProduct({ id: 'variant-id', parentId: 'parent-id', taxId: null });
            setParentProduct({ id: 'parent-id', taxId: 'tax-19' });

            expect(getStore().productTaxRate).toEqual(taxes[1]);
        });

        it('returns an empty object when neither the product nor its parent has a tax rate', () => {
            getStore().taxes = taxes;
            setProduct({ id: 'variant-id', parentId: 'parent-id', taxId: null });
            setParentProduct({});

            expect(getStore().productTaxRate).toEqual({});
        });

        it('returns an empty object when the tax rate is unknown', () => {
            getStore().taxes = taxes;
            setProduct({ id: 'product-id', taxId: 'deleted-tax' });

            expect(getStore().productTaxRate).toEqual({});
        });
    });
});
