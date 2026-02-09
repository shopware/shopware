/**
 * @sw-package framework
 */
import type { uiTabsAddTabItem } from '@shopware-ag/meteor-admin-sdk/es/ui/tabs';
import { useExtensionOrdereredArrayMap } from '../composables/use-extension-ordered-container';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type TabItemEntry = Omit<uiTabsAddTabItem, 'responseType' | 'positionId'>;

const tabsStore = Shopware.Store.register(`tabs`, () => {
    const tabs = useExtensionOrdereredArrayMap<TabItemEntry>();
    const tabItems = tabs.items;

    const addTabItem = ({ label, componentSectionId, positionId }: Omit<uiTabsAddTabItem, 'responseType'>): void => {
        const tabsForPositionId = tabs.get(positionId);
        tabsForPositionId.push({
            label,
            componentSectionId,
        });
    };

    return {
        tabItems,
        addTabItem,
        reset: tabs.reset,
    };
});

/**
 * @private
 */
export type TabsStore = ReturnType<typeof tabsStore>;

/**
 * @private
 */
export default tabsStore;
