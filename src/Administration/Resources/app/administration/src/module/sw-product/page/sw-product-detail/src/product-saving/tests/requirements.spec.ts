import { createPinia, setActivePinia } from 'pinia';
import { type ProductSavingPage, type SavedProduct, useProductSaving } from '../private';

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
 * A product detail page that edits a valid, changed product and saves it successfully, with the given changes.
 */
function createPage(changes: Partial<ProductSavingPage> = {}, product: Partial<SavedProduct> = {}) {
    return {
        productId: 'product-id',
        product: { id: 'product-id', productNumber: 'SW1', prices: [], isNew: () => false, ...product },
        productType: 'physical',
        isUploadedFileRequired: false,
        ignoreFieldsValidation: [],
        productApiContext: {},
        lengthUnit: 'mm',
        weightUnit: 'kg',
        productRepository: { hasChanges: () => true },
        syncRepository: { save: jest.fn(() => Promise.resolve()) },
        entityValidationService: {
            validate: jest.fn(
                (validated: SavedProduct, customValidate: (errors: unknown[], p: SavedProduct) => unknown[]) => {
                    return customValidate([], validated).length === 0;
                },
            ),
        },
        numberRangeService: { reserve: jest.fn(() => Promise.resolve({ number: 'SW2' })) },
        seoUrlService: { updateCanonicalUrl: jest.fn(() => Promise.resolve()) },
        productNumberPreview: '',
        isSaveSuccessful: false,
        updateSeoPromises: [],
        previousLengthUnit: null,
        previousWeightUnit: null,
        loadAll: jest.fn(() => Promise.resolve()),
        loadProduct: jest.fn(() => Promise.resolve()),
        savePreferenceUnits: jest.fn(() => Promise.resolve()),
        createNotificationError: jest.fn(),
        $t: translate,
        $router: { push: jest.fn() },
        ...changes,
    } as ProductSavingPage;
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
        const page = createPage();

        // ACT
        await useProductSaving({}, page).onSave();

        // ASSERT
        expect(page.syncRepository.save).toHaveBeenCalledWith(page.product, page.productApiContext);
        expect(page.loadAll).toHaveBeenCalledTimes(1);
        expect(page.isSaveSuccessful).toBe(true);
    });

    /**
     * @requirement A merchant cannot save a product nobody could buy, because its minimum purchase exceeds its maximum.
     */
    it('refuses a product whose minimum purchase exceeds its maximum purchase', async () => {
        // ARRANGE
        const page = createPage({}, { minPurchase: 5, maxPurchase: 2 });

        // ACT
        await useProductSaving({}, page).onSave();

        // ASSERT
        expect(page.createNotificationError).toHaveBeenCalledWith({ message: 'sw-product.detail.errorMinMaxPurchase' });
        expect(page.syncRepository.save).not.toHaveBeenCalled();
    });

    /**
     * @requirement A merchant who sets a list or regulation price on one side only gets the other side as 0,
     * and an empty one is removed.
     */
    it('completes one-sided reference prices and clears empty ones', async () => {
        // ARRANGE
        const price = [{ listPrice: { gross: 10, net: null }, regulationPrice: { gross: null, net: null } }];
        const page = createPage({}, { price });

        // ACT
        await useProductSaving({}, page).onSave();

        // ASSERT
        expect(price).toEqual([{ listPrice: { gross: 10, net: 0 }, regulationPrice: null }]);
    });

    /**
     * @requirement A merchant who sells a digital product must upload its file before saving it.
     */
    it('refuses a digital product without a file', async () => {
        // ARRANGE
        const page = createPage({ isUploadedFileRequired: true }, { downloads: [] });

        // ACT
        await useProductSaving({}, page).onSave();

        // ASSERT
        expect(page.createNotificationError).toHaveBeenCalledWith({
            title: 'global.default.error',
            message: 'global.notification.notificationSaveErrorMessageRequiredFieldsInvalid',
        });
        expect(page.syncRepository.save).not.toHaveBeenCalled();
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
        const page = createPage({ syncRepository: { save: () => Promise.reject(failure) } });

        // ACT
        await useProductSaving({}, page).onSave();

        // ASSERT
        expect(page.createNotificationError).toHaveBeenCalledWith({
            title: 'global.default.error',
            message: 'sw-product.notification.notificationSaveErrorProductNoAlreadyExists {"productNo":"SW1"}',
        });
        expect(page.isSaveSuccessful).toBe(false);
    });

    /**
     * @requirement A merchant who saves a product without changes sees the save succeed without a request.
     */
    it('skips the request when nothing changed', async () => {
        // ARRANGE
        const page = createPage({ productRepository: { hasChanges: () => false } });

        // ACT
        await useProductSaving({}, page).onSave();

        // ASSERT
        expect(page.syncRepository.save).not.toHaveBeenCalled();
        expect(page.isSaveSuccessful).toBe(true);
    });

    /**
     * @requirement A new product keeps the number the merchant saw while creating it.
     */
    it('reserves the shown product number when a new product is saved', async () => {
        // ARRANGE
        const page = createPage({ productId: null, productNumberPreview: 'SW1' });

        // ACT
        await useProductSaving({}, page).onSave();

        // ASSERT
        expect(page.numberRangeService.reserve).toHaveBeenCalledWith('product');
        expect(page.product.productNumber).toBe('SW2');
    });

    /**
     * @requirement An extension that replaces one step of the page changes what saving the page does.
     */
    it('runs the steps the page passes instead of its own', async () => {
        // ARRANGE
        const page = createPage();
        const saveProduct = jest.fn(() => Promise.resolve('empty' as const));

        // ACT
        await useProductSaving({ saveProduct }, page).onSave();

        // ASSERT
        expect(saveProduct).toHaveBeenCalledTimes(1);
        expect(page.syncRepository.save).not.toHaveBeenCalled();
    });
});
