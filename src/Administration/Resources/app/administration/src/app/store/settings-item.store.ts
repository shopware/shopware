const { hasOwnProperty } = Shopware.Utils.object;

/**
 * @private
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
 * @private
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const settingsItemsStoreId = 'settingsItems';

/**
 * @sw-package framework
 * @private
 */
const settingsItems = Shopware.Store.register({
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
 * @private
 */
export type SettingsItems = ReturnType<typeof settingsItems>;

/**
 * @private
 */
export default settingsItems;
