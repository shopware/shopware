/**
 * @sw-package framework
 */

const { Criteria } = Shopware.Data;

const DEFAULT_TTL = 5 * 60 * 1000;
const DEFAULT_LIMIT = 100;

interface ReferenceDataRepository<EntityName extends keyof EntitySchema.Entities> {
    get(id: string, context?: unknown): Promise<Entity<EntityName> | null>;
    search(criteria: unknown, context?: unknown): Promise<EntityCollection<EntityName>>;
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
    salesChannelTypes: EntityCollection<'sales_channel_type'> | null;
    salesChannelTypesLoadedAt: number;
    salesChannelTypesLanguageId: string | null;
}

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
            salesChannelTypes: null,
            salesChannelTypesLoadedAt: 0,
            salesChannelTypesLanguageId: null,
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

            const systemCurrencyId = Shopware.Context.app.systemCurrencyId;

            if (!systemCurrencyId) {
                this.systemCurrency = null;
                this.systemCurrencyLoadedAt = Date.now();

                return null;
            }

            this.systemCurrency = await this.getRepository('currency').get(systemCurrencyId, Shopware.Context.api);
            this.systemCurrencyLoadedAt = Date.now();

            return this.systemCurrency;
        },

        async loadCurrencies(forceReload = false): Promise<EntityCollection<'currency'>> {
            if (!forceReload && this.currencies && this.isFresh(this.currenciesLoadedAt, this.currenciesLanguageId)) {
                return this.currencies;
            }

            const criteria = new Criteria(1, DEFAULT_LIMIT);

            criteria.addSorting(Criteria.sort('name', 'ASC', false));

            this.currencies = await this.getRepository('currency').search(criteria, Shopware.Context.api);
            this.currenciesLoadedAt = Date.now();
            this.currenciesLanguageId = this.getLanguageId();

            return this.currencies;
        },

        async loadActiveLanguages(forceReload = false): Promise<EntityCollection<'language'>> {
            if (
                !forceReload &&
                this.activeLanguages &&
                this.isFresh(this.activeLanguagesLoadedAt, this.activeLanguagesLanguageId)
            ) {
                return this.activeLanguages;
            }

            const criteria = new Criteria(1, DEFAULT_LIMIT);

            criteria.addSorting(Criteria.sort('name', 'ASC', false));
            criteria.addFilter(Criteria.equals('active', true));

            this.activeLanguages = await this.getRepository('language').search(criteria, Shopware.Context.api);
            this.activeLanguagesLoadedAt = Date.now();
            this.activeLanguagesLanguageId = this.getLanguageId();

            return this.activeLanguages;
        },

        async loadSalesChannelTypes(forceReload = false): Promise<EntityCollection<'sales_channel_type'>> {
            if (
                !forceReload &&
                this.salesChannelTypes &&
                this.isFresh(this.salesChannelTypesLoadedAt, this.salesChannelTypesLanguageId)
            ) {
                return this.salesChannelTypes;
            }

            const criteria = new Criteria(1, DEFAULT_LIMIT);

            this.salesChannelTypes = await this.getRepository('sales_channel_type').search(criteria, Shopware.Context.api);
            this.salesChannelTypesLoadedAt = Date.now();
            this.salesChannelTypesLanguageId = this.getLanguageId();

            return this.salesChannelTypes;
        },

        invalidateAll(): void {
            this.invalidateSystemCurrency();
            this.invalidateCurrencies();
            this.invalidateActiveLanguages();
            this.invalidateSalesChannelTypes();
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

        invalidateSalesChannelTypes(): void {
            this.salesChannelTypes = null;
            this.salesChannelTypesLoadedAt = 0;
            this.salesChannelTypesLanguageId = null;
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
