/**
 * @private
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const topBarButtonStoreId = 'topBarButton';

/**
 * @sw-package innovation
 * @private
 * @description Apply for upselling service only, no public usage
 */

const topBarButtonStore = Shopware.Store.register({
    id: topBarButtonStoreId,

    state: () => ({
        buttons: [] as unknown[],
    }),
});

/**
 * @private
 */
export type TopBarButtonStore = ReturnType<typeof topBarButtonStore>;

/**
 * @private
 */
export default topBarButtonStore;
