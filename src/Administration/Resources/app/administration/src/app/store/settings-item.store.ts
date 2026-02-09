/**
 * @sw-package framework
 * @private
 */

import { reactive } from 'vue';
import { useExtensionOrdereredArrayMap } from '../composables/use-extension-ordered-container';

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

        // @ts-expect-error - the inferred type is incorrect
        if (groupArray.items.some((setting) => setting.name === settingsItem.name)) {
            return;
        }

        groupArray.push(settingsItem);
    };

    const defaultGroups = {
        general: [],
        customer: [],
        automation: [],
        localization: [],
        content: [],
        commerce: [],
        system: [],
        account: [],
        plugins: [],
    };

    return reactive({
        settingsGroups,
        addItem,
        reset: settingsByGroup.reset,
    });
});

/**
 * @private
 */
export type SettingsItems = ReturnType<typeof settingsItems>;

/**
 * @private
 */
export default settingsItems;
