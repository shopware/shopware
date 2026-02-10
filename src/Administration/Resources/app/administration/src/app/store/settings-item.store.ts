/**
 * @sw-package framework
 * @private
 */

import { useExtensionOrdereredArrayMap } from '../composables/use-extension-ordered-container';

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
 * @sw-package framework
 * @private
 */
const settingsItems = Shopware.Store.register('settingsItems', () => {
    const settingsByGroup = useExtensionOrdereredArrayMap<SettingsItem>();
    const settingsGroups = settingsByGroup.items;

    const addItem = (settingsItem: SettingsItem) => {
        let group = settingsItem.group;

        if (typeof group === 'function') {
            group = group();
        }

        if (!group || typeof group !== 'string') {
            throw new Error('Group is undefined or invalid');
        }

        const groupArray = settingsByGroup.get(group);

        if (groupArray.items.value.some((setting) => setting.name === settingsItem.name)) {
            return;
        }

        groupArray.push(settingsItem);
    };

    const defaultGroups = [
        'general',
        'customer',
        'automation',
        'localization',
        'content',
        'commerce',
        'system',
        'account',
        'plugins',
    ];

    // Initialize the default groups by accessing them via get, which creates empty entries in the map
    const initDefaults = () => defaultGroups.forEach((group) => settingsByGroup.get(group));
    initDefaults();

    const reset = () => {
        settingsByGroup.reset();
        initDefaults();
    };

    return {
        settingsGroups,
        addItem,
        reset,
    };
});

/**
 * @private
 */
export type SettingsItems = ReturnType<typeof settingsItems>;

/**
 * @private
 */
export default settingsItems;
