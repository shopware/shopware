import {
    EntityValidationService,
    EventBus,
    isSeoUrlStoreRegistered,
    trackTelemetry,
    types,
    useErrorStore,
    useProductDetailStore,
    useSeoUrlStore,
} from '../dependencies';
import { describeSaveError, describeSeoUrlErrors, type Translate } from './save-errors';
import { hasValidPurchaseLimits, normalizeReferencePrices, type ReferencePriceField } from './save-validation';

/**
 * @sw-package inventory
 */

type Price = Parameters<typeof normalizeReferencePrices>[0];

/**
 * A product as the save flow sees it.
 *
 * @private
 */
export type SavedProduct = {
    id: string;
    productNumber?: string;
    slotConfig?: unknown;
    minPurchase?: number | null;
    maxPurchase?: number | null;
    price?: Price;
    prices: Array<{ price?: Price }>;
    downloads?: unknown[];
    isNew(): boolean;
};

/**
 * How a save ended: `success`, `empty` when nothing changed, or the failed request.
 *
 * @private
 */
export type SaveResult = 'success' | 'empty' | Parameters<typeof describeSaveError>[0];

type ValidationError = Record<string, unknown>;

type SeoUrl = { seoPathInfo?: string; isModified?: boolean; languageId: string };

/**
 * How the product detail page saves a product. Each member is also a step an override can replace.
 *
 * @private
 */
export interface ProductSaving {
    /** Checks the product, saves it with its SEO URLs, and tells the merchant how it went. */
    onSave(): Promise<void>;
    /** Saves the product and its changed SEO URLs, and reloads the page. Resolves with how the save ended. */
    saveProduct(): Promise<SaveResult>;
    /** Shows the outcome of a save and waits for the SEO URLs before it marks the save successful. */
    onSaveFinished(response: SaveResult): void;
    /** Resets the save button and opens a new product's own page. */
    saveFinish(): void;
    /** The page's own checks for the entity validation, which add to `errors`. */
    customValidate(errors: ValidationError[], product: SavedProduct): ValidationError[];
    validateProductPurchase(): boolean;
    /** Completes or clears the list and regulation prices of every price of the product. */
    validateProductPrices(): void;
    validatePrices(field: ReferencePriceField, prices: Price): void;
}

/**
 * The members of the product detail page that saving reads and writes.
 *
 * @private
 */
export interface ProductSavingPage {
    readonly productId: string | null;
    readonly product: SavedProduct;
    readonly productType: string;
    readonly isUploadedFileRequired: boolean;
    readonly ignoreFieldsValidation: string[];
    readonly productApiContext: unknown;
    readonly lengthUnit: string;
    readonly weightUnit: string;
    readonly productRepository: { hasChanges: (product: SavedProduct) => boolean };
    readonly syncRepository: { save: (product: SavedProduct, context: unknown) => Promise<unknown> };
    readonly entityValidationService: {
        validate: (
            product: SavedProduct,
            customValidator: ProductSaving['customValidate'],
            ignoredFields: string[],
        ) => boolean;
    };
    readonly numberRangeService: { reserve: (entity: string) => Promise<{ number: string }> };
    readonly seoUrlService: { updateCanonicalUrl: (seoUrl: SeoUrl, languageId: string) => Promise<unknown> };
    productNumberPreview: string;
    isSaveSuccessful: boolean;
    updateSeoPromises: Promise<unknown>[];
    previousLengthUnit: string | null;
    previousWeightUnit: string | null;
    loadAll: () => Promise<unknown>;
    loadProduct: () => Promise<unknown>;
    savePreferenceUnits: () => Promise<unknown>;
    createNotificationError: (notification: { title?: string; message: string }) => void;
    $t: Translate;
    $router: { push: (route: { name: string; params?: Record<string, string> }) => unknown };
}

type Context = { page: ProductSavingPage; step: ProductSaving };

/**
 * Saves the product of the product detail page: checks it, saves it with its changed SEO URLs, and reloads it.
 * A merchant learns from a notification why a save failed.
 *
 * @example
 * const saving = useProductSaving({}, page);
 * await saving.onSave();
 *
 * @private
 */
export function useProductSaving(
    // Until 6.8, the page passes its own methods here, so a Component.override of one applies to these calls too.
    steps: Partial<ProductSaving>,
    page: ProductSavingPage,
): ProductSaving {
    const context = { page } as Context;
    const saving: ProductSaving = {
        onSave: () => onSave(context),
        saveProduct: () => saveProduct(context),
        onSaveFinished: (response) => onSaveFinished(context, response),
        saveFinish: () => saveFinish(context),
        customValidate: (errors, product) => customValidate(context, errors, product),
        validateProductPurchase: () => hasValidPurchaseLimits(page.product),
        validateProductPrices: () => validateProductPrices(context),
        validatePrices: (field, prices) => normalizeReferencePrices(prices, field),
    };
    context.step = { ...saving, ...steps };

    return saving;
}

function onSave(context: Context): Promise<void> {
    const { page, step } = context;

    if (!step.validateProductPurchase()) {
        page.createNotificationError({ message: page.$t('sw-product.detail.errorMinMaxPurchase') });

        return Promise.resolve();
    }

    step.validateProductPrices();
    reserveProductNumber(page);

    page.isSaveSuccessful = false;

    if (types.isEmpty(page.product.slotConfig)) {
        page.product.slotConfig = null;
    }

    const isValid = page.entityValidationService.validate(
        page.product,
        (errors, product) => step.customValidate(errors, product),
        page.ignoreFieldsValidation,
    );

    if (!isValid) {
        page.createNotificationError({
            title: page.$t('global.default.error'),
            message: page.$t('global.notification.notificationSaveErrorMessageRequiredFieldsInvalid'),
        });

        return Promise.resolve();
    }

    return step.saveProduct().then((response) => step.onSaveFinished(response));
}

function saveProduct({ page }: Context): Promise<SaveResult> {
    const store = useProductDetailStore();
    store.setLoading(['product', true]);

    page.updateSeoPromises = [];
    queueSeoUrlUpdates(page);

    return new Promise((resolve) => {
        if (!page.productRepository.hasChanges(page.product)) {
            store.setLoading(['product', false]);
            resolve('empty');
            return;
        }

        const productType = page.productType;
        const productIsNew = page.product.isNew();

        page.syncRepository
            .save(page.product, page.productApiContext)
            .then(() => {
                trackTelemetry({
                    eventName: 'sw_product.detail.product_saved',
                    product_type: productType,
                    product_is_new: productIsNew,
                });

                page.savePreferenceUnits()
                    .then(() => {
                        page.previousLengthUnit = page.lengthUnit;
                        page.previousWeightUnit = page.weightUnit;
                    })
                    .catch(resolve);

                void page.loadAll().then(() => {
                    store.setLoading(['product', false]);
                    resolve('success');
                });
            })
            .catch((response: SaveResult) => {
                store.setLoading(['product', false]);
                resolve(response);
            });
    });
}

function onSaveFinished({ page }: Context, response: SaveResult) {
    if (response !== 'success' && response !== 'empty') {
        page.createNotificationError(describeSaveError(response, (...args) => page.$t(...args)));
        return;
    }

    useErrorStore().resetApiErrors();
    EventBus.emit('sw-product-detail-save-success');

    if (page.updateSeoPromises.length === 0) {
        page.isSaveSuccessful = true;
        return;
    }

    const store = useProductDetailStore();
    store.setLoading(['product', true]);

    Promise.all(page.updateSeoPromises)
        .then(() => {
            EventBus.emit('sw-product-detail-save-finish');
            page.isSaveSuccessful = true;
        })
        .catch(() => Promise.resolve())
        .finally(() => {
            store.setLoading(['product', false]);
            void page.loadProduct();
        });
}

function saveFinish({ page }: Context) {
    page.isSaveSuccessful = false;

    if (!page.productId) {
        void page.$router.push({ name: 'sw.product.detail', params: { id: page.product.id } });
    }
}

function customValidate({ page }: Context, errors: ValidationError[], product: SavedProduct) {
    if (page.isUploadedFileRequired && (product.downloads === undefined || product.downloads.length < 1)) {
        errors.push({
            ...EntityValidationService.createRequiredError('/0/downloads'),
            code: 'SW_PRODUCT_DOWNLOADS_EXTERNAL_MANAGEMENT_REQUIRED',
            detail: page.$t('sw-product.detailBase.downloadsExternalManagementRequired'),
        });
    }

    return errors;
}

function validateProductPrices({ page, step }: Context) {
    (['listPrice', 'regulationPrice'] as const).forEach((field) => {
        page.product.prices.forEach((advancedPrice) => step.validatePrices(field, advancedPrice.price));
        step.validatePrices(field, page.product.price);
    });
}

/** A new product keeps the number it showed while the merchant edited it, reserved for good on its first save. */
function reserveProductNumber(page: ProductSavingPage) {
    if (page.productId || page.productNumberPreview !== page.product.productNumber) {
        return;
    }

    void page.numberRangeService.reserve('product').then((response) => {
        page.productNumberPreview = 'reserved';
        page.product.productNumber = response.number;
    });
}

/** Starts saving every new or changed SEO URL, and notifies the merchant of each one the API refuses. */
function queueSeoUrlUpdates(page: ProductSavingPage) {
    if (!isSeoUrlStoreRegistered()) {
        return;
    }

    const seoUrlStore = useSeoUrlStore();
    const defaultSeoUrl = seoUrlStore.defaultSeoUrl as SeoUrl;

    (seoUrlStore.newOrModifiedUrls as SeoUrl[] | null)?.forEach((seoUrl) => {
        if (!seoUrl.seoPathInfo) {
            seoUrl.seoPathInfo = defaultSeoUrl.seoPathInfo;
            seoUrl.isModified = false;
        } else {
            seoUrl.isModified = true;
        }

        page.updateSeoPromises.push(
            page.seoUrlService.updateCanonicalUrl(seoUrl, seoUrl.languageId).catch((error: unknown) => {
                describeSeoUrlErrors(error as never, (...args) => page.$t(...args)).forEach((message) => {
                    page.createNotificationError({ message });
                });

                // eslint-disable-next-line @typescript-eslint/prefer-promise-reject-errors -- onSaveFinished() waits for this request and keeps the API error as it is
                return Promise.reject(error);
            }),
        );
    });
}
