const { hasOwnProperty } = Shopware.Utils.object;

interface SettingsItem {
    name?: string;
    group: 'shop' | 'system' | 'plugins';
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
 * @package admin
 * @private
 */
const settingsItems = Shopware.Store.register({
    id: 'settingsItems',

    state: (): {
        settingsGroups: {
            shop: SettingsItem[];
            system: SettingsItem[];
            plugins: SettingsItem[];
        };
    } => {
        return {
            settingsGroups: {
                shop: [],
                system: [],
                plugins: [],
            },
        };
    },

    actions: {
        addItem(settingsItem: SettingsItem) {
            const group = settingsItem.group;

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
