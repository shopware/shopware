/**
 * @sw-package innovation
 */
import { useTopBarButtonStore } from './src/topbar-button.store';

/**
 * @private
 * @description Apply for upselling service only, no public usage
 */
export default function initializeTopBarButtons(): void {
    // @ts-expect-error - There are no types for this as it is private API
    Shopware.ExtensionAPI.handle('__upsellingMenuButton', (configuration) => {
        const store = useTopBarButtonStore();
        store.buttons.push(configuration);
    });
}
