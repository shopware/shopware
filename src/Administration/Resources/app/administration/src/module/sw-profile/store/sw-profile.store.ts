/**
 * @private
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const swProfileStoreId = 'swProfile';

/**
 * @sw-package fundamentals@framework
 */
const swProfileStore = Shopware.Store.register(swProfileStoreId, {
    state() {
        return {
            minSearchTermLength: 2,
            searchPreferences: [],
            userSearchPreferences: null,
        };
    },

    actions: {
        setMinSearchTermLength(minSearchTermLength: number) {
            this.minSearchTermLength = minSearchTermLength;
        },
    },
});

/**
 * @private
 */
export default swProfileStore;

/**
 * @private
 */
export type SwProfileStore = ReturnType<typeof swProfileStore>;
