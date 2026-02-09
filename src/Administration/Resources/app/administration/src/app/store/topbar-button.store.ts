/**
 * @sw-package innovation
 * @private
 * @description Apply for upselling service only, no public usage
 */

import { useExtensionOrderedArray } from '../composables/use-extension-ordered-container';

const topBarButtonStore = Shopware.Store.register('topBarButton', () => {
    const buttonsOrdered = useExtensionOrderedArray<unknown>();
    const buttons = buttonsOrdered.items;

    const addButton = (configuration: unknown) => {
        buttonsOrdered.push(configuration);
    };

    return {
        buttons,
        addButton,
        reset: buttonsOrdered.reset,
    };
});

/**
 * @private
 */
export type TopBarButtonStore = ReturnType<typeof topBarButtonStore>;

/**
 * @private
 */
export default topBarButtonStore;
