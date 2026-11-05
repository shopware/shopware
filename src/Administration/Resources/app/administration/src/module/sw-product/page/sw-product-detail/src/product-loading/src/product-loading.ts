import {
    cloneDeep,
    Criteria,
    getApiContext,
    getCustomFieldDataProviderService,
    reactive,
    ref,
    type Ref,
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
 * What loading needs from the product detail page: which product it loads, the repositories it loads from, and how
 * it tells the merchant that the product is gone.
 *
 * @private
 */
export interface ProductLoadingDependencies {
    /** The id of the product the page opened, or `null` while it creates a new one. */
    productId: () => string | null;
    productCriteria: () => Criteria;
    defaultFeatureSetCriteria: () => Criteria;
    productApiContext: () => unknown;
    productRepository: () => {
        get: (id: string, context: unknown, criteria: Criteria) => Promise<LoadedProduct | null>;
    };
    propertyRepository: () => SearchRepository<{ source?: unknown }>;
    currencyRepository: () => SearchRepository<Iterable<unknown>>;
    taxRepository: () => SearchRepository<unknown>;
    featureSetRepository: () => SearchRepository<unknown>;
    /** The provider the page injected, if any. Without one, loading takes the global service. */
    customFieldDataProviderService?: { getCustomFieldSets: (entity: string) => Promise<unknown> };
    /** Lets a variant inherit the prices of its parent, after both are loaded. */
    syncVariantPriceInheritance: () => void;
    notifyError: (notification: { message: string }) => void;
    translate: (key: string) => string;
    navigate: (route: { name: string }) => Promise<unknown>;
}

/**
 * The state of loading, which the page shows and resets when it opens another product.
 *
 * @private
 */
export interface ProductLoadingState {
    /** Whether the product the page opened does not exist. */
    isProductNotFound: boolean;
    /** The product whose first view loading reported last, so a reload does not report it again. */
    lastViewedProductId: string | null;
}

const SHARED_DATA_TTL = 5 * 60 * 1000;

type Context = {
    deps: ProductLoadingDependencies;
    state: { [Key in keyof ProductLoadingState]: Ref<ProductLoadingState[Key]> };
    step: ProductLoading;
};

/**
 * Loads a product and the shop data its page needs into the product detail store, each behind its loading flag.
 * A loaded product without purchase prices gets the default ones, and its properties replace the plain property ids.
 * The returned object is reactive and holds the state of loading next to the steps.
 *
 * @example
 * const loading = useProductLoading({}, deps);
 * await loading.loadAll();
 * loading.isProductNotFound; // false
 *
 * @private
 */
export function useProductLoading(
    // Until 6.8, the page passes its own methods here, so a Component.override of one applies to these calls too.
    steps: Partial<ProductLoading>,
    deps: ProductLoadingDependencies,
): ProductLoading & ProductLoadingState {
    const context = {
        deps,
        state: {
            isProductNotFound: ref(false),
            lastViewedProductId: ref<string | null>(null),
        },
    } as Context;
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

    return reactive({ ...loading, ...context.state });
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
    const { deps, step } = context;
    const store = useProductDetailStore();

    return whileLoading('product', async () => {
        const product = await deps
            .productRepository()
            .get(deps.productId() || store.product.id, deps.productApiContext(), deps.productCriteria());

        if (!product) {
            await step.onProductNotFound();
            return;
        }

        if (!product.parentId) {
            await fillMissingPurchasePrices(context, product);
        }
        await loadProperties(deps, product, createPropertyCriteria(product.propertyIds ?? [], product.id));

        store.isDownloadExternallyManaged = !product.downloads?.length;
        store.product = product as never;

        if (product.parentId) {
            await step.loadParentProduct();
            deps.syncVariantPriceInheritance();
        } else {
            store.parentProduct = {} as never;
        }

        trackFirstView(context, product);
    });
}

function loadParentProduct(context: Context) {
    const { deps } = context;
    const store = useProductDetailStore();

    return whileLoading('parentProduct', async () => {
        const parent = (await deps
            .productRepository()
            .get(store.product.parentId as string, getApiContext(), deps.productCriteria())) as LoadedProduct;

        await fillMissingPurchasePrices(context, parent);
        await loadProperties(deps, parent, createPropertyCriteria(parent.propertyIds ?? []));

        store.parentProduct = parent as never;
    });
}

function loadCurrencies({ deps }: Context) {
    const criteria = new Criteria(1, 500);
    criteria.addSorting(Criteria.sort('name', 'ASC', false));

    return whileLoading('currencies', async () => {
        const currencies = await searchSharedData(deps.currencyRepository(), criteria, 'currencies');
        useProductDetailStore().currencies = [...currencies] as never;
    });
}

function loadTaxes({ deps }: Context) {
    const criteria = new Criteria(1, 500);
    criteria.addSorting(Criteria.sort('position'));

    return whileLoading('taxes', async () => {
        const taxes = await searchSharedData(deps.taxRepository(), criteria, 'taxes');
        useProductDetailStore().setTaxes(taxes as never);
    });
}

function loadAttributeSet({ deps }: Context) {
    const customFieldDataProvider = deps.customFieldDataProviderService ?? getCustomFieldDataProviderService();

    return whileLoading('customFieldSets', async () => {
        const customFieldSets: unknown = await customFieldDataProvider.getCustomFieldSets('product');
        useProductDetailStore().customFieldSets = customFieldSets as never;
    });
}

function loadDefaultFeatureSet({ deps }: Context) {
    return whileLoading('defaultFeatureSet', async () => {
        const featureSets = await deps.featureSetRepository().search(deps.defaultFeatureSetCriteria());
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

async function onProductNotFound({ deps, state }: Context) {
    state.isProductNotFound.value = true;
    useShopwareAppsStore().selectedIds = [];
    deps.notifyError({ message: deps.translate('sw-product.detail.messageProductNotFound') });

    try {
        await deps.navigate({ name: 'sw.product.index' });
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

async function loadProperties(deps: ProductLoadingDependencies, product: LoadedProduct, criteria: Criteria) {
    if (!product.propertyIds?.length) {
        return;
    }

    const properties = await deps.propertyRepository().search(criteria);
    properties.source = product.properties.source;

    product._origin.properties = cloneDeep(properties);
    product.properties = properties;
}

function trackFirstView({ deps, state }: Context, product: LoadedProduct) {
    if (deps.productId() !== product.id || state.lastViewedProductId.value === product.id) {
        return;
    }

    state.lastViewedProductId.value = product.id;
    trackTelemetry({ eventName: 'sw_product.detail.product_viewed', product_type: product.type as string });
}
