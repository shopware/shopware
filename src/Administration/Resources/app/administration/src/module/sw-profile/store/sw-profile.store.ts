/**
 * @sw-package fundamentals@framework
 */
const swProfileStore = Shopware.Store.register('swProfile', {
    state() {
        return {
            searchPreferences: [],
            userSearchPreferences: null,
        };
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
