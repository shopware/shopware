import {
    EntityValidationService,
    reactive,
    ref,
    type Ref,
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

type Notification = { title?: string; message: string };

/**
 * What saving needs from the product detail page: the product it saves, the services it saves it with, and how it
 * reloads the page and tells the merchant about the outcome.
 *
 * @private
 */
export interface ProductSavingDependencies {
    /** The id of the product the page opened, or `null` while it creates a new one. */
    productId: () => string | null;
    product: () => SavedProduct;
    productType: () => string;
    /** Whether the product needs an uploaded file, because it is digital and nobody else manages its downloads. */
    isUploadedFileRequired: () => boolean;
    /** The translated fields the entity validation skips, because they inherit from the parent language. */
    ignoreFieldsValidation: () => string[];
    productApiContext: () => unknown;
    productRepository: () => { hasChanges: (product: SavedProduct) => boolean };
    syncRepository: () => { save: (product: SavedProduct, context: unknown) => Promise<unknown> };
    entityValidationService: {
        validate: (
            product: SavedProduct,
            customValidator: ProductSaving['customValidate'],
            ignoredFields: string[],
        ) => boolean;
    };
    numberRangeService: { reserve: (entity: string) => Promise<{ number: string }> };
    seoUrlService: { updateCanonicalUrl: (seoUrl: SeoUrl, languageId: string) => Promise<unknown> };
    /** Reloads everything the page shows, after the product is saved. */
    loadAll: () => Promise<unknown>;
    /** Reloads the product, after its SEO URLs are saved. */
    loadProduct: () => Promise<unknown>;
    /** Saves the measurement units the merchant chose as the user's preference. */
    saveMeasurementUnits: () => Promise<unknown>;
    notifyError: (notification: Notification) => void;
    translate: Translate;
    navigate: (route: { name: string; params?: Record<string, string> }) => unknown;
}

/**
 * The state of a save, which the page shows.
 *
 * @private
 */
export interface ProductSavingState {
    /** Whether the save button shows that the last save succeeded. */
    isSaveSuccessful: boolean;
    /** The SEO URL requests of the last save, which it waits for before it counts as successful. */
    updateSeoPromises: Promise<unknown>[];
    /** The number a new product shows until its first save reserves it, then `reserved`. */
    productNumberPreview: string;
}

type Context = {
    deps: ProductSavingDependencies;
    state: { [Key in keyof ProductSavingState]: Ref<ProductSavingState[Key]> };
    step: ProductSaving;
};

/**
 * Saves the product of the product detail page: checks it, saves it with its changed SEO URLs, and reloads it.
 * A merchant learns from a notification why a save failed.
 * The returned object is reactive and holds the state of the save next to the steps.
 *
 * @example
 * const saving = useProductSaving({}, deps);
 * await saving.onSave();
 * saving.isSaveSuccessful; // true
 *
 * @private
 */
export function useProductSaving(
    // Until 6.8, the page passes its own methods here, so a Component.override of one applies to these calls too.
    steps: Partial<ProductSaving>,
    deps: ProductSavingDependencies,
): ProductSaving & ProductSavingState {
    const context = {
        deps,
        state: {
            isSaveSuccessful: ref(false),
            updateSeoPromises: ref<Promise<unknown>[]>([]),
            productNumberPreview: ref(''),
        },
    } as Context;
    const saving: ProductSaving = {
        onSave: () => onSave(context),
        saveProduct: () => saveProduct(context),
        onSaveFinished: (response) => onSaveFinished(context, response),
        saveFinish: () => saveFinish(context),
        customValidate: (errors, product) => customValidate(context, errors, product),
        validateProductPurchase: () => hasValidPurchaseLimits(deps.product()),
        validateProductPrices: () => validateProductPrices(context),
        validatePrices: (field, prices) => normalizeReferencePrices(prices, field),
    };
    context.step = { ...saving, ...steps };

    return reactive({ ...saving, ...context.state });
}

function onSave(context: Context): Promise<void> {
    const { deps, state, step } = context;
    const product = deps.product();

    if (!step.validateProductPurchase()) {
        deps.notifyError({ message: deps.translate('sw-product.detail.errorMinMaxPurchase') });

        return Promise.resolve();
    }

    step.validateProductPrices();
    reserveProductNumber(context);

    state.isSaveSuccessful.value = false;

    if (types.isEmpty(product.slotConfig)) {
        product.slotConfig = null;
    }

    const isValid = deps.entityValidationService.validate(
        product,
        (errors, validated) => step.customValidate(errors, validated),
        deps.ignoreFieldsValidation(),
    );

    if (!isValid) {
        deps.notifyError({
            title: deps.translate('global.default.error'),
            message: deps.translate('global.notification.notificationSaveErrorMessageRequiredFieldsInvalid'),
        });

        return Promise.resolve();
    }

    return step.saveProduct().then((response) => step.onSaveFinished(response));
}

function saveProduct(context: Context): Promise<SaveResult> {
    const { deps, state } = context;
    const store = useProductDetailStore();
    store.setLoading(['product', true]);

    state.updateSeoPromises.value = [];
    queueSeoUrlUpdates(context);

    return new Promise((resolve) => {
        const product = deps.product();

        if (!deps.productRepository().hasChanges(product)) {
            store.setLoading(['product', false]);
            resolve('empty');
            return;
        }

        const productType = deps.productType();
        const productIsNew = product.isNew();

        deps.syncRepository()
            .save(product, deps.productApiContext())
            .then(() => {
                trackTelemetry({
                    eventName: 'sw_product.detail.product_saved',
                    product_type: productType,
                    product_is_new: productIsNew,
                });

                deps.saveMeasurementUnits().catch(resolve);

                void deps.loadAll().then(() => {
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

function onSaveFinished({ deps, state }: Context, response: SaveResult) {
    if (response !== 'success' && response !== 'empty') {
        deps.notifyError(describeSaveError(response, deps.translate));
        return;
    }

    useErrorStore().resetApiErrors();
    EventBus.emit('sw-product-detail-save-success');

    if (state.updateSeoPromises.value.length === 0) {
        state.isSaveSuccessful.value = true;
        return;
    }

    const store = useProductDetailStore();
    store.setLoading(['product', true]);

    Promise.all(state.updateSeoPromises.value)
        .then(() => {
            EventBus.emit('sw-product-detail-save-finish');
            state.isSaveSuccessful.value = true;
        })
        .catch(() => Promise.resolve())
        .finally(() => {
            store.setLoading(['product', false]);
            void deps.loadProduct();
        });
}

function saveFinish({ deps, state }: Context) {
    state.isSaveSuccessful.value = false;

    if (!deps.productId()) {
        void deps.navigate({ name: 'sw.product.detail', params: { id: deps.product().id } });
    }
}

function customValidate({ deps }: Context, errors: ValidationError[], product: SavedProduct) {
    if (deps.isUploadedFileRequired() && (product.downloads === undefined || product.downloads.length < 1)) {
        errors.push({
            ...EntityValidationService.createRequiredError('/0/downloads'),
            code: 'SW_PRODUCT_DOWNLOADS_EXTERNAL_MANAGEMENT_REQUIRED',
            detail: deps.translate('sw-product.detailBase.downloadsExternalManagementRequired'),
        });
    }

    return errors;
}

function validateProductPrices({ deps, step }: Context) {
    const product = deps.product();

    (['listPrice', 'regulationPrice'] as const).forEach((field) => {
        product.prices.forEach((advancedPrice) => step.validatePrices(field, advancedPrice.price));
        step.validatePrices(field, product.price);
    });
}

/** A new product keeps the number it showed while the merchant edited it, reserved for good on its first save. */
function reserveProductNumber({ deps, state }: Context) {
    const product = deps.product();

    if (deps.productId() || state.productNumberPreview.value !== product.productNumber) {
        return;
    }

    void deps.numberRangeService.reserve('product').then((response) => {
        state.productNumberPreview.value = 'reserved';
        product.productNumber = response.number;
    });
}

/** Starts saving every new or changed SEO URL, and notifies the merchant of each one the API refuses. */
function queueSeoUrlUpdates({ deps, state }: Context) {
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

        state.updateSeoPromises.value.push(
            deps.seoUrlService.updateCanonicalUrl(seoUrl, seoUrl.languageId).catch((error: unknown) => {
                describeSeoUrlErrors(error as never, deps.translate).forEach((message) => {
                    deps.notifyError({ message });
                });

                // eslint-disable-next-line @typescript-eslint/prefer-promise-reject-errors -- onSaveFinished() waits for this request and keeps the API error as it is
                return Promise.reject(error);
            }),
        );
    });
}
