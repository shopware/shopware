import { createPinia, setActivePinia } from 'pinia';
import { type ProductSavingDependencies, type SavedProduct, useProductSaving } from '../private';

/**
 * @sw-package inventory
 */

/**
 * A translator that, like vue-i18n, returns the key, with the values appended so a test sees them.
 */
function translate(key: string, values?: Record<string, unknown>) {
    return values ? `${key} ${JSON.stringify(values)}` : key;
}

/**
 * The dependencies of saving for a valid, changed product that saves successfully, with the given changes.
 */
function createDependencies(changes: Partial<ProductSavingDependencies> = {}, product: Partial<SavedProduct> = {}) {
    const savedProduct: SavedProduct = {
        id: 'product-id',
        productNumber: 'SW1',
        prices: [],
        isNew: () => false,
        ...product,
    };
    const syncRepository = { save: jest.fn(() => Promise.resolve()) };
    const numberRangeService = { reserve: jest.fn(() => Promise.resolve({ number: 'SW2' })) };

    return {
        productId: () => 'product-id',
        product: () => savedProduct,
        productType: () => 'physical',
        isUploadedFileRequired: () => false,
        ignoreFieldsValidation: () => [],
        productApiContext: () => ({}),
        productRepository: () => ({ hasChanges: () => true }),
        syncRepository: () => syncRepository,
        entityValidationService: {
            validate: (validated, customValidate) => customValidate([], validated).length === 0,
        } satisfies ProductSavingDependencies['entityValidationService'],
        numberRangeService,
        seoUrlService: { updateCanonicalUrl: () => Promise.resolve() },
        loadAll: jest.fn(() => Promise.resolve()),
        loadProduct: jest.fn(() => Promise.resolve()),
        saveMeasurementUnits: () => Promise.resolve(),
        notifyError: jest.fn(),
        translate,
        navigate: jest.fn(),
        ...changes,
    } satisfies ProductSavingDependencies;
}

// The product detail page saves the product a merchant edits, reloads it, and marks the save button as done.
// Before it saves, it checks that customers can buy the product and completes or clears its list and regulation prices.
// When the API refuses the product, the merchant learns why from a notification.
// Extensions replace single steps of the page, so every step saving takes over from the page stays replaceable.
describe('saving a product on the product detail page', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        jest.spyOn(Shopware.Telemetry, 'track').mockImplementation(() => {});
    });

    /**
     * @requirement A merchant saves a product and sees that the save succeeded.
     */
    it('saves the product, reloads the page and marks the save as successful', async () => {
        // ARRANGE
        const deps = createDependencies();
        const saving = useProductSaving({}, deps);

        // ACT
        await saving.onSave();

        // ASSERT
        expect(deps.syncRepository().save).toHaveBeenCalledWith(deps.product(), deps.productApiContext());
        expect(deps.loadAll).toHaveBeenCalledTimes(1);
        expect(saving.isSaveSuccessful).toBe(true);
    });

    /**
     * @requirement A merchant cannot save a product nobody could buy, because its minimum purchase exceeds its maximum.
     */
    it('refuses a product whose minimum purchase exceeds its maximum purchase', async () => {
        // ARRANGE
        const deps = createDependencies({}, { minPurchase: 5, maxPurchase: 2 });

        // ACT
        await useProductSaving({}, deps).onSave();

        // ASSERT
        expect(deps.notifyError).toHaveBeenCalledWith({ message: 'sw-product.detail.errorMinMaxPurchase' });
        expect(deps.syncRepository().save).not.toHaveBeenCalled();
    });

    /**
     * @requirement A merchant who sets a list or regulation price on one side only gets the other side as 0,
     * and an empty one is removed.
     */
    it('completes one-sided reference prices and clears empty ones', async () => {
        // ARRANGE
        const price = [{ listPrice: { gross: 10, net: null }, regulationPrice: { gross: null, net: null } }];
        const deps = createDependencies({}, { price });

        // ACT
        await useProductSaving({}, deps).onSave();

        // ASSERT
        expect(price).toEqual([{ listPrice: { gross: 10, net: 0 }, regulationPrice: null }]);
    });

    /**
     * @requirement A merchant who sells a digital product must upload its file before saving it.
     */
    it('refuses a digital product without a file', async () => {
        // ARRANGE
        const deps = createDependencies({ isUploadedFileRequired: () => true }, { downloads: [] });

        // ACT
        await useProductSaving({}, deps).onSave();

        // ASSERT
        expect(deps.notifyError).toHaveBeenCalledWith({
            title: 'global.default.error',
            message: 'global.notification.notificationSaveErrorMessageRequiredFieldsInvalid',
        });
        expect(deps.syncRepository().save).not.toHaveBeenCalled();
    });

    /**
     * @requirement A merchant who reuses a product number learns which number is taken.
     */
    it('names the taken product number when the API refuses it', async () => {
        // ARRANGE
        const failure = Object.assign(new Error('Request failed'), {
            response: {
                data: { errors: [{ code: 'CONTENT__DUPLICATE_PRODUCT_NUMBER', meta: { parameters: { number: 'SW1' } } }] },
            },
        });
        const deps = createDependencies({ syncRepository: () => ({ save: () => Promise.reject(failure) }) });
        const saving = useProductSaving({}, deps);

        // ACT
        await saving.onSave();

        // ASSERT
        expect(deps.notifyError).toHaveBeenCalledWith({
            title: 'global.default.error',
            message: 'sw-product.notification.notificationSaveErrorProductNoAlreadyExists {"productNo":"SW1"}',
        });
        expect(saving.isSaveSuccessful).toBe(false);
    });

    /**
     * @requirement A merchant who saves a product without changes sees the save succeed without a request.
     */
    it('skips the request when nothing changed', async () => {
        // ARRANGE
        const deps = createDependencies({ productRepository: () => ({ hasChanges: () => false }) });
        const saving = useProductSaving({}, deps);

        // ACT
        await saving.onSave();

        // ASSERT
        expect(deps.syncRepository().save).not.toHaveBeenCalled();
        expect(saving.isSaveSuccessful).toBe(true);
    });

    /**
     * @requirement A new product keeps the number the merchant saw while creating it.
     */
    it('reserves the shown product number when a new product is saved', async () => {
        // ARRANGE
        const deps = createDependencies({ productId: () => null });
        const saving = useProductSaving({}, deps);
        saving.productNumberPreview = 'SW1';

        // ACT
        await saving.onSave();

        // ASSERT
        expect(deps.numberRangeService.reserve).toHaveBeenCalledWith('product');
        expect(deps.product().productNumber).toBe('SW2');
        expect(saving.productNumberPreview).toBe('reserved');
    });

    /**
     * @requirement An extension that replaces one step of the page changes what saving the page does.
     */
    it('runs the steps the page passes instead of its own', async () => {
        // ARRANGE
        const deps = createDependencies();
        const saveProduct = jest.fn(() => Promise.resolve('empty' as const));

        // ACT
        await useProductSaving({ saveProduct }, deps).onSave();

        // ASSERT
        expect(saveProduct).toHaveBeenCalledTimes(1);
        expect(deps.syncRepository().save).not.toHaveBeenCalled();
    });
});
