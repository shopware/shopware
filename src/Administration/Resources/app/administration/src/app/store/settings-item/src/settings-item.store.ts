/**
 * @sw-package framework
 */
import { defineStoreOptions, getStore, hasOwnProperty, type StoreOf } from '../dependencies';

/**
 * @public
 */
export interface SettingsItem {
    name?: string;
    group: string | (() => string);
    icon?: string;
    id?: string;
    label?: string;
    to?:
        | {
              name: string;
              params?: {
                  id: string;
                  back: string;
              };
          }
        | string;
}

/**
 * @public
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const settingsItemsStoreId = 'settingsItems';

/**
 * @private
 * The options the store is registered with, see `register.ts`.
 */
export const settingsItemsStoreOptions = defineStoreOptions({
    id: settingsItemsStoreId,

    state: (): {
        settingsGroups: Record<string, SettingsItem[]>;
    } => {
        return {
            settingsGroups: {
                general: [],
                customer: [],
                automation: [],
                localization: [],
                content: [],
                commerce: [],
                system: [],
                account: [],
                plugins: [],
            },
        };
    },

    actions: {
        addItem(settingsItem: SettingsItem) {
            let group = settingsItem.group;

            if (typeof group === 'function') {
                group = group();
            }

            if (!group || typeof group !== 'string') {
                throw new Error('Group is undefined or invalid');
            }

            if (!hasOwnProperty(this.settingsGroups, group)) {
                this.settingsGroups[group] = [];
            }

            if (this.settingsGroups[group].some((setting) => setting.name === settingsItem.name)) {
                return;
            }

            this.settingsGroups[group].push(settingsItem);
        },
    },
});

/**
 * @public
 */
export type SettingsItems = StoreOf<typeof settingsItemsStoreOptions>;

/**
 * @public
 * The store, looked up when it is called, so everyone who uses it shares one state.
 */
export const useSettingsItemsStore = (): SettingsItems => getStore(settingsItemsStoreId);
