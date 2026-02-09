/**
 * @sw-package framework
 */
import type { actionButtonAdd } from '@shopware-ag/meteor-admin-sdk/es/ui/action-button';
import { useExtensionOrderedArray } from '../composables/use-extension-ordered-container';

type ActionButtonConfig = Omit<actionButtonAdd, 'responseType'>;

/**
 * @private
 * @description Store for action buttons
 */
const actionButtonsStore = Shopware.Store.register('actionButtons', () => {
    const buttonsOrdered = useExtensionOrderedArray<ActionButtonConfig>();
    const buttons = buttonsOrdered.items;

    const add = (button: ActionButtonConfig): void => {
        buttonsOrdered.push(button);
    };

    return {
        buttons,
        add,
        reset: buttonsOrdered.reset,
    };
});

/**
 * @private
 */
export type ActionButtonsStore = ReturnType<typeof actionButtonsStore>;

/**
 * @private
 */
export default actionButtonsStore;
