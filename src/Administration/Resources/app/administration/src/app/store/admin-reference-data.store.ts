/**
 * @sw-package framework
 */

const { Criteria } = Shopware.Data;

const DEFAULT_TTL = 5 * 60 * 1000;
const DEFAULT_LIMIT = 100;
const DEFAULT_CURRENCY_LIMIT = 500;

interface ReferenceDataRepository<EntityName extends keyof EntitySchema.Entities> {
    get(id: string, context?: unknown): Promise<Entity<EntityName> | null>;
    search(criteria: unknown, context?: unknown): Promise<EntityCollection<EntityName>>;
    searchIds(criteria: unknown, context?: unknown): Promise<{ data: string[] }>;
}

interface RepositoryFactory {
    create<EntityName extends keyof EntitySchema.Entities>(entityName: EntityName): ReferenceDataRepository<EntityName>;
}

interface AdminReferenceDataState {
    ttl: number;
    systemCurrency: Entity<'currency'> | null;
    systemCurrencyLoadedAt: number;
    currencies: EntityCollection<'currency'> | null;
    currenciesLoadedAt: number;
    currenciesLanguageId: string | null;
    activeLanguages: EntityCollection<'language'> | null;
    activeLanguagesLoadedAt: number;
    activeLanguagesLanguageId: string | null;
    taxes: EntityCollection<'tax'> | null;
    taxesLoadedAt: number;
    taxesLanguageId: string | null;
    salesChannelTypes: EntityCollection<'sales_channel_type'> | null;
    salesChannelTypesLoadedAt: number;
    salesChannelTypesLanguageId: string | null;
    productNumberRangeIds: string[] | null;
    productNumberRangeIdsLoadedAt: number;
}

let pendingSystemCurrency: Promise<Entity<'currency'> | null> | null = null;
let pendingCurrencies: Promise<EntityCollection<'currency'>> | null = null;
let pendingActiveLanguages: Promise<EntityCollection<'language'>> | null = null;
let pendingTaxes: Promise<EntityCollection<'tax'>> | null = null;
let pendingSalesChannelTypes: Promise<EntityCollection<'sales_channel_type'>> | null = null;
let pendingProductNumberRangeIds: Promise<string[]> | null = null;

const adminReferenceDataStore = Shopware.Store.register({
    id: 'adminReferenceData',

    state: (): AdminReferenceDataState => {
        return {
            ttl: DEFAULT_TTL,
            systemCurrency: null,
            systemCurrencyLoadedAt: 0,
            currencies: null,
            currenciesLoadedAt: 0,
            currenciesLanguageId: null,
            activeLanguages: null,
            activeLanguagesLoadedAt: 0,
            activeLanguagesLanguageId: null,
            taxes: null,
            taxesLoadedAt: 0,
            taxesLanguageId: null,
            salesChannelTypes: null,
            salesChannelTypesLoadedAt: 0,
            salesChannelTypesLanguageId: null,
            productNumberRangeIds: null,
            productNumberRangeIdsLoadedAt: 0,
        };
    },

    actions: {
        isFresh(loadedAt: number, languageId?: string | null): boolean {
            if (loadedAt <= 0 || Date.now() - loadedAt >= this.ttl) {
                return false;
            }

            return languageId === undefined || languageId === this.getLanguageId();
        },

        getLanguageId(): string | null {
            return Shopware.Context.api.languageId ?? null;
        },

        getRepository<EntityName extends keyof EntitySchema.Entities>(
            entityName: EntityName,
        ): ReferenceDataRepository<EntityName> {
            const repositoryFactory = Shopware.Service('repositoryFactory') as unknown as RepositoryFactory;

            return repositoryFactory.create(entityName);
        },

        async loadSystemCurrency(forceReload = false): Promise<Entity<'currency'> | null> {
            if (!forceReload && this.systemCurrency && this.isFresh(this.systemCurrencyLoadedAt)) {
                return this.systemCurrency;
            }

            if (!forceReload && pendingSystemCurrency) {
                return pendingSystemCurrency;
            }

            const systemCurrencyId = Shopware.Context.app.systemCurrencyId;

            if (!systemCurrencyId) {
                this.systemCurrency = null;
                this.systemCurrencyLoadedAt = Date.now();

                return null;
            }

            pendingSystemCurrency = this.getRepository('currency')
                .get(systemCurrencyId, Shopware.Context.api)
                .then((systemCurrency) => {
                    this.systemCurrency = systemCurrency;
                    this.systemCurrencyLoadedAt = Date.now();

                    return this.systemCurrency;
                })
                .finally(() => {
                    pendingSystemCurrency = null;
                });

            return pendingSystemCurrency;
        },

        async loadCurrencies(forceReload = false): Promise<EntityCollection<'currency'>> {
            if (!forceReload && this.currencies && this.isFresh(this.currenciesLoadedAt, this.currenciesLanguageId)) {
                return this.currencies;
            }

            if (!forceReload && pendingCurrencies) {
                return pendingCurrencies;
            }

            const criteria = new Criteria(1, DEFAULT_CURRENCY_LIMIT);

            criteria.addSorting(Criteria.sort('name', 'ASC', false));

            pendingCurrencies = this.getRepository('currency')
                .search(criteria, Shopware.Context.api)
                .then((currencies) => {
                    this.currencies = currencies;
                    this.currenciesLoadedAt = Date.now();
                    this.currenciesLanguageId = this.getLanguageId();

                    return this.currencies;
                })
                .finally(() => {
                    pendingCurrencies = null;
                });

            return pendingCurrencies;
        },

        async loadActiveLanguages(forceReload = false): Promise<EntityCollection<'language'>> {
            if (
                !forceReload &&
                this.activeLanguages &&
                this.isFresh(this.activeLanguagesLoadedAt, this.activeLanguagesLanguageId)
            ) {
                return this.activeLanguages;
            }

            if (!forceReload && pendingActiveLanguages) {
                return pendingActiveLanguages;
            }

            const criteria = new Criteria(1, DEFAULT_LIMIT);

            criteria.addSorting(Criteria.sort('name', 'ASC', false));
            criteria.addFilter(Criteria.equals('active', true));

            pendingActiveLanguages = this.getRepository('language')
                .search(criteria, Shopware.Context.api)
                .then((activeLanguages) => {
                    this.activeLanguages = activeLanguages;
                    this.activeLanguagesLoadedAt = Date.now();
                    this.activeLanguagesLanguageId = this.getLanguageId();

                    return this.activeLanguages;
                })
                .finally(() => {
                    pendingActiveLanguages = null;
                });

            return pendingActiveLanguages;
        },

        async loadTaxes(forceReload = false): Promise<EntityCollection<'tax'>> {
            if (!forceReload && this.taxes && this.isFresh(this.taxesLoadedAt, this.taxesLanguageId)) {
                return this.taxes;
            }

            if (!forceReload && pendingTaxes) {
                return pendingTaxes;
            }

            const criteria = new Criteria(1, 500);

            criteria.addSorting(Criteria.sort('position'));

            pendingTaxes = this.getRepository('tax')
                .search(criteria, Shopware.Context.api)
                .then((taxes) => {
                    this.taxes = taxes;
                    this.taxesLoadedAt = Date.now();
                    this.taxesLanguageId = this.getLanguageId();

                    return this.taxes;
                })
                .finally(() => {
                    pendingTaxes = null;
                });

            return pendingTaxes;
        },

        async loadSalesChannelTypes(forceReload = false): Promise<EntityCollection<'sales_channel_type'>> {
            if (
                !forceReload &&
                this.salesChannelTypes &&
                this.isFresh(this.salesChannelTypesLoadedAt, this.salesChannelTypesLanguageId)
            ) {
                return this.salesChannelTypes;
            }

            if (!forceReload && pendingSalesChannelTypes) {
                return pendingSalesChannelTypes;
            }

            const criteria = new Criteria(1, DEFAULT_LIMIT);

            pendingSalesChannelTypes = this.getRepository('sales_channel_type')
                .search(criteria, Shopware.Context.api)
                .then((salesChannelTypes) => {
                    this.salesChannelTypes = salesChannelTypes;
                    this.salesChannelTypesLoadedAt = Date.now();
                    this.salesChannelTypesLanguageId = this.getLanguageId();

                    return this.salesChannelTypes;
                })
                .finally(() => {
                    pendingSalesChannelTypes = null;
                });

            return pendingSalesChannelTypes;
        },

        async loadProductNumberRangeIds(forceReload = false): Promise<string[]> {
            if (!forceReload && this.productNumberRangeIds && this.isFresh(this.productNumberRangeIdsLoadedAt)) {
                return this.productNumberRangeIds;
            }

            if (!forceReload && pendingProductNumberRangeIds) {
                return pendingProductNumberRangeIds;
            }

            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.equals('type.technicalName', 'product'));
            criteria.addFilter(Criteria.equals('global', true));

            pendingProductNumberRangeIds = this.getRepository('number_range')
                .searchIds(criteria, Shopware.Context.api)
                .then((numberRangeIds) => {
                    this.productNumberRangeIds = numberRangeIds.data;
                    this.productNumberRangeIdsLoadedAt = Date.now();

                    return this.productNumberRangeIds;
                })
                .finally(() => {
                    pendingProductNumberRangeIds = null;
                });

            return pendingProductNumberRangeIds;
        },

        invalidateAll(): void {
            this.invalidateSystemCurrency();
            this.invalidateCurrencies();
            this.invalidateActiveLanguages();
            this.invalidateTaxes();
            this.invalidateSalesChannelTypes();
            this.invalidateProductNumberRangeIds();
        },

        invalidateSystemCurrency(): void {
            this.systemCurrency = null;
            this.systemCurrencyLoadedAt = 0;
        },

        invalidateCurrencies(): void {
            this.currencies = null;
            this.currenciesLoadedAt = 0;
            this.currenciesLanguageId = null;
        },

        invalidateActiveLanguages(): void {
            this.activeLanguages = null;
            this.activeLanguagesLoadedAt = 0;
            this.activeLanguagesLanguageId = null;
        },

        invalidateTaxes(): void {
            this.taxes = null;
            this.taxesLoadedAt = 0;
            this.taxesLanguageId = null;
        },

        invalidateSalesChannelTypes(): void {
            this.salesChannelTypes = null;
            this.salesChannelTypesLoadedAt = 0;
            this.salesChannelTypesLanguageId = null;
        },

        invalidateProductNumberRangeIds(): void {
            this.productNumberRangeIds = null;
            this.productNumberRangeIdsLoadedAt = 0;
        },
    },
});

/**
 * @private
 */
export type AdminReferenceDataStore = ReturnType<typeof adminReferenceDataStore>;

/**
 * @private
 */
export default adminReferenceDataStore;
