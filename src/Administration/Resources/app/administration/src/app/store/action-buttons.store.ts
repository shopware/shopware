/**
 * @sw-package framework
 */
import type { actionButtonAdd } from '@shopware-ag/meteor-admin-sdk/es/ui/action-button';

type ActionButtonConfig = Omit<actionButtonAdd, 'responseType'>;

/**
 * @private
 * @description Store for action buttons
 */
const actionButtonsStore = Shopware.Store.register({
    id: 'actionButtons',

    state: () => ({
        /**
         * List of all action buttons
         */
        buttons: [] as ActionButtonConfig[],
    }),

    actions: {
        /**
         * Add a new action button
         * @param button - The button to add
         */
        add(button: ActionButtonConfig): void {
            this.buttons.push(button);
        },
    },
});

/**
 * @private
 */
export type ActionButtonsStore = ReturnType<typeof actionButtonsStore>;

/**
 * @private
 */
export default actionButtonsStore;
