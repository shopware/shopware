import {
    Criteria,
    getApiContext,
    getCacheService,
    isFeatureActive,
    types,
    useProductDetailStore,
    useShopwareAppsStore,
} from '../dependencies';

/**
 * @sw-package inventory
 */

/**
 * A new product as creation fills it in.
 *
 * @private
 */
export type NewProduct = {
    id: string;
    active?: boolean;
    taxId?: string | null;
    type?: string;
    maxPurchase?: number;
    featureSetId?: string;
    price?: unknown[];
    purchasePrices?: unknown[];
    visibilities: unknown[];
    isNew?: () => boolean;
    [field: string]: unknown;
};

type SalesChannel = { id: string };

/**
 * The sales channels and visibilities a new product starts with, as the shop's settings name them.
 *
 * @private
 */
export type DefaultSalesChannels = {
    defaultSalesChannelIds?: string[];
    defaultVisibilities?: Record<string, number>;
    defaultActive?: boolean;
};

/**
 * How the product detail page sets up a new product. Each member is also a step an override can replace.
 *
 * @private
 */
export interface ProductCreation {
    /** Creates the new product in the store, loads what it needs, and fills in the shop's defaults. */
    createState(): Promise<void>;
    /** Applies the product type the merchant chose to create, e.g. a digital product sells once per order. */
    adjustProductAccordingToType(): void;
    /** The id of the tax rate the shop settings name as default, or `null`. */
    getDefaultTaxRate(): Promise<string | null>;
    getDefaultSalesChannels(): Promise<DefaultSalesChannels>;
    fetchSalesChannelByIds(ids: string[]): Promise<SalesChannel[]>;
    createProductVisibilityEntity(visibility: Record<string, number>, salesChannel: SalesChannel): unknown;
}

/**
 * The members of the product detail page that creation reads and calls.
 *
 * @private
 */
export interface ProductCreationPage {
    readonly creationStates: string[] | null;
    readonly creationType: string;
    readonly defaultSalesChannelVisibility: number;
    readonly productRepository: { create: () => NewProduct };
    readonly salesChannelRepository: { search: (criteria: Criteria) => Promise<SalesChannel[]> };
    readonly productVisibilityRepository: { create: (context: unknown) => Record<string, unknown> };
    readonly systemConfigApiService: { getValues: (domain: string) => Promise<Record<string, unknown>> };
    loadCurrencies: () => Promise<unknown>;
    loadTaxes: () => Promise<unknown>;
    loadAttributeSet: () => Promise<unknown>;
    loadDefaultFeatureSet: () => Promise<unknown>;
    getDefaultPurchasePrices: () => unknown[];
}

const SHARED_DATA_TTL = 5 * 60 * 1000;

type Context = { page: ProductCreationPage; step: ProductCreation };

/**
 * Sets up a new product on the product detail page: active, of the type the merchant chose, priced in the default
 * currency, and with the tax rate, sales channels and feature set the shop settings name as defaults.
 *
 * @example
 * const creation = useProductCreation({}, page);
 * await creation.createState();
 *
 * @private
 */
export function useProductCreation(
    // Until 6.8, the page passes its own methods here, so a Component.override of one applies to these calls too.
    steps: Partial<ProductCreation>,
    page: ProductCreationPage,
): ProductCreation {
    const context = { page } as Context;
    const creation: ProductCreation = {
        createState: () => createState(context),
        adjustProductAccordingToType: () => adjustProductAccordingToType(context),
        getDefaultTaxRate: () => getDefaultTaxRate(context),
        getDefaultSalesChannels: () => getDefaultSalesChannels(context),
        fetchSalesChannelByIds: (ids) => fetchSalesChannelByIds(context, ids),
        createProductVisibilityEntity: (visibility, salesChannel) =>
            createProductVisibilityEntity(context, visibility, salesChannel),
    };
    context.step = { ...creation, ...steps };

    return creation;
}

/** The product the store holds, which creation fills in. */
function newProduct() {
    return useProductDetailStore().product as NewProduct;
}

function createState(context: Context) {
    const { page, step } = context;
    const store = useProductDetailStore();
    store.localMode = true;
    useShopwareAppsStore().selectedIds = [];
    store.setLoading(['product', true]);

    if (!isFeatureActive('v6.8.0.0')) {
        store.creationStates = page.creationStates as never;
    }
    store.creationType = page.creationType;

    store.product = page.productRepository.create() as never;
    Object.assign(newProduct(), {
        active: true,
        taxId: null,
        metaTitle: '',
        additionalText: '',
        variantListingConfig: {},
    });

    if (page.creationType) {
        step.adjustProductAccordingToType();
    }

    return Promise.all([
        page.loadCurrencies(),
        page.loadTaxes(),
        page.loadAttributeSet(),
        page.loadDefaultFeatureSet(),
    ]).then(() => {
        newProduct().price = [{ currencyId: store.defaultCurrency.id, net: null, linked: true, gross: null }];
        newProduct().purchasePrices = page.getDefaultPurchasePrices();

        if (newProduct().isNew) {
            applyDefaultTaxRate(context);
            applyDefaultSalesChannels(context);
        }

        const featureSets = store.getDefaultFeatureSet as Array<{ id: string }>;
        if (featureSets?.length) {
            newProduct().featureSetId = featureSets[0].id;
        }

        store.setLoading(['product', false]);
    });
}

function adjustProductAccordingToType({ page }: Context) {
    if (page.creationType === 'digital') {
        newProduct().maxPurchase = 1;
    }

    newProduct().type = page.creationType;
}

function getDefaultTaxRate({ page }: Context) {
    return getCacheService().query({
        key: ['shared-data', 'default-tax-rate-id'],
        ttl: SHARED_DATA_TTL,
        fn: () =>
            page.systemConfigApiService.getValues('core.tax').then((response) => {
                const defaultTaxRateId = response['core.tax.defaultTaxRate'];

                return typeof defaultTaxRateId === 'string' ? defaultTaxRateId : null;
            }),
    });
}

function getDefaultSalesChannels({ page }: Context) {
    return page.systemConfigApiService.getValues('core.defaultSalesChannel').then((response): DefaultSalesChannels => {
        if (types.isEmpty(response)) {
            return {};
        }

        return {
            defaultSalesChannelIds: response?.['core.defaultSalesChannel.salesChannel'] as string[] | undefined,
            defaultVisibilities: response?.['core.defaultSalesChannel.visibility'] as Record<string, number>,
            defaultActive: !!response?.['core.defaultSalesChannel.active'],
        };
    });
}

function fetchSalesChannelByIds({ page }: Context, ids: string[]) {
    const criteria = new Criteria(1, 25);
    criteria.addFilter(Criteria.equalsAny('id', ids));

    return page.salesChannelRepository.search(criteria);
}

function createProductVisibilityEntity({ page }: Context, visibility: Record<string, number>, salesChannel: SalesChannel) {
    return Object.assign(page.productVisibilityRepository.create(getApiContext()), {
        visibility: visibility[salesChannel.id] || page.defaultSalesChannelVisibility,
        productId: newProduct().id,
        salesChannelId: salesChannel.id,
        salesChannel,
    });
}

function applyDefaultTaxRate({ step }: Context) {
    void step.getDefaultTaxRate().then((taxId) => {
        newProduct().taxId = taxId;
    });
}

function applyDefaultSalesChannels({ step }: Context) {
    void step.getDefaultSalesChannels().then(async (defaults) => {
        if (types.isEmpty(defaults)) {
            return;
        }

        newProduct().active = defaults.defaultActive;

        if (!defaults.defaultSalesChannelIds?.length) {
            return;
        }

        const salesChannels = await step.fetchSalesChannelByIds(defaults.defaultSalesChannelIds);
        salesChannels.forEach((salesChannel) => {
            newProduct().visibilities.push(
                step.createProductVisibilityEntity(defaults.defaultVisibilities as Record<string, number>, salesChannel),
            );
        });
    });
}
