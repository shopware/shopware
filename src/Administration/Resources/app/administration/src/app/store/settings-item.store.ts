const { hasOwnProperty } = Shopware.Utils.object;

interface SettingsItem {
    name?: string;
    group: 'shop' | 'system' | 'plugins' | (() => 'shop' | 'system' | 'plugins');
    icon?: string;
    id?: string;
    label?: string;
    to:
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
 * @sw-package framework
 * @private
 */
const settingsItems = Shopware.Store.register({
    id: 'settingsItems',

    state: (): {
        settingsGroups: {
            general: SettingsItem[];
            customer: SettingsItem[];
            automation: SettingsItem[];
            localization: SettingsItem[];
            content: SettingsItem[];
            commerce: SettingsItem[];
            system: SettingsItem[];
            account: SettingsItem[];
            plugins: SettingsItem[];
            shop: SettingsItem[];

        };
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
                shop: [],
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
