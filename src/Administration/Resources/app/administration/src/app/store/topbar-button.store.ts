/**
 * @package customer-order
 * @private
 * @description Apply for upselling service only, no public usage
 */

const topBarButtonStore = Shopware.Store.register({
    id: 'topBarButton',

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
