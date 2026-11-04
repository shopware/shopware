import {
    cloneDeep,
    Criteria,
    getApiContext,
    getCustomFieldDataProviderService,
    trackTelemetry,
    useProductDetailStore,
    useShopwareAppsStore,
} from '../dependencies';
import { createPropertyCriteria } from './product-criteria';

/**
 * @sw-package inventory
 */

/**
 * A product as the API returns it, with the fields loading fills in.
 *
 * @private
 */
export type LoadedProduct = {
    id: string;
    type?: string;
    parentId?: string | null;
    purchasePrices?: PurchasePrice[] | null;
    propertyIds?: string[] | null;
    properties: { source?: unknown };
    downloads?: unknown[] | null;
    _origin: Record<string, unknown>;
};

/**
 * A purchase price of a product in one currency.
 *
 * @private
 */
export type PurchasePrice = { currencyId: string | undefined; net: number; gross: number; linked: boolean };

type SearchRepository<Result> = {
    search: (criteria: Criteria, context?: unknown, options?: Record<string, unknown>) => Promise<Result>;
};

type LoadingFlag = Parameters<ReturnType<typeof useProductDetailStore>['setLoading']>[0][0];

/**
 * What the product detail page loads into its store. Each member is also a step an override can replace.
 *
 * @private
 */
export interface ProductLoading {
    /** Loads the product, the currencies, the taxes and the custom field sets at once. */
    loadAll(): Promise<unknown[]>;
    /** Loads the product of the page with its properties, and its parent when it is a variant. */
    loadProduct(): Promise<void>;
    /** Loads the parent of the variant in the store, with its properties. */
    loadParentProduct(): Promise<void>;
    loadCurrencies(): Promise<void>;
    loadTaxes(): Promise<void>;
    loadAttributeSet(): Promise<void>;
    loadDefaultFeatureSet(): Promise<void>;
    /** The purchase prices of a product without any: 0 in the default currency. */
    getDefaultPurchasePrices(): PurchasePrice[];
    /** Tells the merchant that the product is gone and leaves for the product list. */
    onProductNotFound(): Promise<void>;
}

/**
 * The members of the product detail page that loading reads and writes.
 *
 * @private
 */
export interface ProductLoadingPage {
    readonly productId: string | null;
    readonly productCriteria: Criteria;
    readonly defaultFeatureSetCriteria: Criteria;
    readonly productApiContext: unknown;
    readonly productRepository: {
        get: (id: string, context: unknown, criteria: Criteria) => Promise<LoadedProduct | null>;
    };
    readonly propertyRepository: SearchRepository<{ source?: unknown }>;
    readonly currencyRepository: SearchRepository<Iterable<unknown>>;
    readonly taxRepository: SearchRepository<unknown>;
    readonly featureSetRepository: SearchRepository<unknown>;
    readonly customFieldDataProviderService?: { getCustomFieldSets: (entity: string) => Promise<unknown> };
    lastViewedProductId: string | null;
    isProductNotFound: boolean;
    syncVariantPriceInheritance: () => void;
    createNotificationError: (notification: { message: string }) => void;
    $t: (key: string) => string;
    $router: { push: (route: { name: string }) => Promise<unknown> };
}

const SHARED_DATA_TTL = 5 * 60 * 1000;

type Context = { page: ProductLoadingPage; step: ProductLoading };

/**
 * Loads a product and the shop data its page needs into the product detail store, each behind its loading flag.
 * A loaded product without purchase prices gets the default ones, and its properties replace the plain property ids.
 *
 * @example
 * const loading = useProductLoading({}, page);
 * await loading.loadAll();
 *
 * @private
 */
export function useProductLoading(
    // Until 6.8, the page passes its own methods here, so a Component.override of one applies to these calls too.
    steps: Partial<ProductLoading>,
    page: ProductLoadingPage,
): ProductLoading {
    const context = { page } as Context;
    const loading: ProductLoading = {
        loadAll: () => loadAll(context),
        loadProduct: () => loadProduct(context),
        loadParentProduct: () => loadParentProduct(context),
        loadCurrencies: () => loadCurrencies(context),
        loadTaxes: () => loadTaxes(context),
        loadAttributeSet: () => loadAttributeSet(context),
        loadDefaultFeatureSet: () => loadDefaultFeatureSet(context),
        getDefaultPurchasePrices,
        onProductNotFound: () => onProductNotFound(context),
    };
    context.step = { ...loading, ...steps };

    return loading;
}

function loadAll({ step }: Context) {
    return Promise.all([
        step.loadProduct(),
        step.loadCurrencies(),
        step.loadTaxes(),
        step.loadAttributeSet(),
    ]);
}

function loadProduct(context: Context) {
    const { page, step } = context;
    const store = useProductDetailStore();

    return whileLoading('product', async () => {
        const product = await page.productRepository.get(
            page.productId || store.product.id,
            page.productApiContext,
            page.productCriteria,
        );

        if (!product) {
            await step.onProductNotFound();
            return;
        }

        if (!product.parentId) {
            await fillMissingPurchasePrices(context, product);
        }
        await loadProperties(page, product, createPropertyCriteria(product.propertyIds ?? [], product.id));

        store.isDownloadExternallyManaged = !product.downloads?.length;
        store.product = product as never;

        if (product.parentId) {
            await step.loadParentProduct();
            page.syncVariantPriceInheritance();
        } else {
            store.parentProduct = {} as never;
        }

        trackFirstView(page, product);
    });
}

function loadParentProduct(context: Context) {
    const { page } = context;
    const store = useProductDetailStore();

    return whileLoading('parentProduct', async () => {
        const parent = (await page.productRepository.get(
            store.product.parentId as string,
            getApiContext(),
            page.productCriteria,
        )) as LoadedProduct;

        await fillMissingPurchasePrices(context, parent);
        await loadProperties(page, parent, createPropertyCriteria(parent.propertyIds ?? []));

        store.parentProduct = parent as never;
    });
}

function loadCurrencies({ page }: Context) {
    const criteria = new Criteria(1, 500);
    criteria.addSorting(Criteria.sort('name', 'ASC', false));

    return whileLoading('currencies', async () => {
        const currencies = await searchSharedData(page.currencyRepository, criteria, 'currencies');
        useProductDetailStore().currencies = [...currencies] as never;
    });
}

function loadTaxes({ page }: Context) {
    const criteria = new Criteria(1, 500);
    criteria.addSorting(Criteria.sort('position'));

    return whileLoading('taxes', async () => {
        const taxes = await searchSharedData(page.taxRepository, criteria, 'taxes');
        useProductDetailStore().setTaxes(taxes as never);
    });
}

function loadAttributeSet({ page }: Context) {
    const customFieldDataProvider = page.customFieldDataProviderService ?? getCustomFieldDataProviderService();

    return whileLoading('customFieldSets', async () => {
        const customFieldSets: unknown = await customFieldDataProvider.getCustomFieldSets('product');
        useProductDetailStore().customFieldSets = customFieldSets as never;
    });
}

function loadDefaultFeatureSet({ page }: Context) {
    return whileLoading('defaultFeatureSet', async () => {
        const featureSets = await page.featureSetRepository.search(page.defaultFeatureSetCriteria);
        useProductDetailStore().setDefaultFeatureSet(featureSets as never);
    });
}

function getDefaultPurchasePrices(): PurchasePrice[] {
    return [
        {
            currencyId: useProductDetailStore().defaultCurrency.id,
            net: 0,
            linked: true,
            gross: 0,
        },
    ];
}

async function onProductNotFound({ page }: Context) {
    page.isProductNotFound = true;
    useShopwareAppsStore().selectedIds = [];
    page.createNotificationError({ message: page.$t('sw-product.detail.messageProductNotFound') });

    try {
        await page.$router.push({ name: 'sw.product.index' });
    } catch {
        // Ignore navigation failures. The missing product state still prevents the detail view from rendering.
    } finally {
        const store = useProductDetailStore();
        store.product = {} as never;
        store.parentProduct = {} as never;
    }
}

/** Raises a loading flag of the store while `load` runs, and lowers it again also when `load` fails. */
async function whileLoading(flag: LoadingFlag, load: () => Promise<void>) {
    useProductDetailStore().setLoading([flag, true]);

    try {
        await load();
    } finally {
        useProductDetailStore().setLoading([flag, false]);
    }
}

/** Searches data all pages share, cached per language for five minutes. */
function searchSharedData<Result>(repository: SearchRepository<Result>, criteria: Criteria, name: string) {
    return repository.search(criteria, getApiContext(), {
        cacheKey: ['shared-data', name, getApiContext().languageId ?? 'default'],
        ttl: SHARED_DATA_TTL,
    });
}

async function fillMissingPurchasePrices({ step }: Context, product: LoadedProduct) {
    if (product.purchasePrices?.length) {
        return;
    }

    if (!useProductDetailStore().defaultCurrency?.id) {
        await step.loadCurrencies();
    }

    product.purchasePrices = step.getDefaultPurchasePrices();
    product._origin.purchasePrices = cloneDeep(product.purchasePrices);
}

async function loadProperties(page: ProductLoadingPage, product: LoadedProduct, criteria: Criteria) {
    if (!product.propertyIds?.length) {
        return;
    }

    const properties = await page.propertyRepository.search(criteria);
    properties.source = product.properties.source;

    product._origin.properties = cloneDeep(properties);
    product.properties = properties;
}

function trackFirstView(page: ProductLoadingPage, product: LoadedProduct) {
    if (page.productId !== product.id || page.lastViewedProductId === product.id) {
        return;
    }

    page.lastViewedProductId = product.id;
    trackTelemetry({ eventName: 'sw_product.detail.product_viewed', product_type: product.type as string });
}
