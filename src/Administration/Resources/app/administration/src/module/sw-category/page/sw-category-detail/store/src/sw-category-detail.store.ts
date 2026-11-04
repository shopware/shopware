/**
 * @sw-package inventory
 */
import {
    type ContextStore,
    Criteria,
    type CriteriaType,
    defineStoreOptions,
    type Entity,
    getStore,
    type Repository,
    type StoreOf,
} from '../dependencies';

interface LoadPayload<EntityName extends keyof EntitySchema.EntityKeys> {
    repository: Repository<EntityName>;
    id: EntityKey<EntityName>;
    apiContext: ContextStore['api'];
    criteria?: CriteriaType;
}

/**
 * @public
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const swCategoryDetailStoreId = 'swCategoryDetail';

/**
 * @private
 * The options the store is registered with, see `register.ts`.
 */
export const swCategoryDetailStoreOptions = defineStoreOptions({
    id: swCategoryDetailStoreId,

    state: () => ({
        landingPage: null as Entity<'landing_page'> | null,
        category: null as Entity<'category'> | null,
        isCategoryColumn: false,
        customFieldSets: [],
        landingPagesToDelete: undefined,
        categoriesToDelete: undefined,
    }),

    actions: {
        loadActiveLandingPage({ repository, id, apiContext, criteria = new Criteria(1, 25) }: LoadPayload<'landing_page'>) {
            if (id === 'create') {
                const landingPage = repository.create(apiContext);
                landingPage.cmsPageId = undefined;
                this.landingPage = landingPage;
                return Promise.resolve();
            }

            return repository.get(id, apiContext, criteria).then((landingPage) => {
                this.landingPage = landingPage;
            });
        },

        loadActiveCategory({ repository, id, apiContext, criteria = new Criteria(1, 25) }: LoadPayload<'category'>) {
            return repository
                .get(id, apiContext, criteria)
                .then((category) => {
                    if (category) {
                        this.isCategoryColumn = false;
                        if (category.parentId) {
                            const parentCriteria = new Criteria(1, 25);
                            parentCriteria.addAssociation('footerSalesChannels');

                            return repository.get(category.parentId, apiContext, parentCriteria).then((parent) => {
                                category.parent = parent ?? undefined;

                                this.isCategoryColumn = category.parent?.footerSalesChannels?.length !== 0;

                                return category;
                            });
                        }
                    }
                    return category;
                })
                .then((category) => {
                    this.category = category;
                });
        },
    },
});

/**
 * @public
 */
export type SwCategoryDetailStore = StoreOf<typeof swCategoryDetailStoreOptions>;

/**
 * @public
 * The store, looked up when it is called, so the page, its views and extensions share one state.
 */
export const useSwCategoryDetailStore = (): SwCategoryDetailStore => getStore(swCategoryDetailStoreId);
