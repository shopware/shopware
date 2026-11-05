import {
    Criteria,
    getApiContext,
    getCacheService,
    isFeatureActive,
    reactive,
    ref,
    type Ref,
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
 * What creation needs from the product detail page: the type the merchant chose, the repositories it creates
 * entities with, and how the page loads the shop data a new product starts from.
 *
 * @private
 */
export interface ProductCreationDependencies {
    /** @deprecated tag:v6.8.0 - will be removed, use `creationType` instead */
    creationStates: () => string[] | null;
    /** The product type the merchant chose to create, e.g. `digital`. */
    creationType: () => string;
    productRepository: () => { create: () => NewProduct };
    salesChannelRepository: () => { search: (criteria: Criteria) => Promise<SalesChannel[]> };
    productVisibilityRepository: () => { create: (context: unknown) => Record<string, unknown> };
    systemConfigApiService: { getValues: (domain: string) => Promise<Record<string, unknown>> };
    /** Loads the currencies, taxes, custom field sets and the default feature set a new product needs. */
    loadShopData: () => Promise<unknown>;
    /** The purchase prices a new product starts with. */
    getDefaultPurchasePrices: () => unknown[];
}

/**
 * The state of creation.
 *
 * @private
 */
export interface ProductCreationState {
    /** The visibility a default sales channel gets when the shop settings name none for it. */
    defaultSalesChannelVisibility: number;
}

const SHARED_DATA_TTL = 5 * 60 * 1000;

type Context = {
    deps: ProductCreationDependencies;
    state: { [Key in keyof ProductCreationState]: Ref<ProductCreationState[Key]> };
    step: ProductCreation;
};

/**
 * Sets up a new product on the product detail page: active, of the type the merchant chose, priced in the default
 * currency, and with the tax rate, sales channels and feature set the shop settings name as defaults.
 * The returned object is reactive and holds the state of creation next to the steps.
 *
 * @example
 * const creation = useProductCreation({}, deps);
 * await creation.createState();
 *
 * @private
 */
export function useProductCreation(
    // Until 6.8, the page passes its own methods here, so a Component.override of one applies to these calls too.
    steps: Partial<ProductCreation>,
    deps: ProductCreationDependencies,
): ProductCreation & ProductCreationState {
    const context = { deps, state: { defaultSalesChannelVisibility: ref(30) } } as Context;
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

    return reactive({ ...creation, ...context.state });
}

/** The product the store holds, which creation fills in. */
function newProduct() {
    return useProductDetailStore().product as NewProduct;
}

function createState(context: Context) {
    const { deps, step } = context;
    const store = useProductDetailStore();
    store.localMode = true;
    useShopwareAppsStore().selectedIds = [];
    store.setLoading(['product', true]);

    if (!isFeatureActive('v6.8.0.0')) {
        store.creationStates = deps.creationStates() as never;
    }
    store.creationType = deps.creationType();

    store.product = deps.productRepository().create() as never;
    Object.assign(newProduct(), {
        active: true,
        taxId: null,
        metaTitle: '',
        additionalText: '',
        variantListingConfig: {},
    });

    if (deps.creationType()) {
        step.adjustProductAccordingToType();
    }

    return deps.loadShopData().then(() => {
        newProduct().price = [{ currencyId: store.defaultCurrency.id, net: null, linked: true, gross: null }];
        newProduct().purchasePrices = deps.getDefaultPurchasePrices();

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

function adjustProductAccordingToType({ deps }: Context) {
    const creationType = deps.creationType();

    if (creationType === 'digital') {
        newProduct().maxPurchase = 1;
    }

    newProduct().type = creationType;
}

function getDefaultTaxRate({ deps }: Context) {
    return getCacheService().query({
        key: ['shared-data', 'default-tax-rate-id'],
        ttl: SHARED_DATA_TTL,
        fn: () =>
            deps.systemConfigApiService.getValues('core.tax').then((response) => {
                const defaultTaxRateId = response['core.tax.defaultTaxRate'];

                return typeof defaultTaxRateId === 'string' ? defaultTaxRateId : null;
            }),
    });
}

function getDefaultSalesChannels({ deps }: Context) {
    return deps.systemConfigApiService.getValues('core.defaultSalesChannel').then((response): DefaultSalesChannels => {
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

function fetchSalesChannelByIds({ deps }: Context, ids: string[]) {
    const criteria = new Criteria(1, 25);
    criteria.addFilter(Criteria.equalsAny('id', ids));

    return deps.salesChannelRepository().search(criteria);
}

function createProductVisibilityEntity(
    { deps, state }: Context,
    visibility: Record<string, number>,
    salesChannel: SalesChannel,
) {
    return Object.assign(deps.productVisibilityRepository().create(getApiContext()), {
        visibility: visibility[salesChannel.id] || state.defaultSalesChannelVisibility.value,
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
