/**
 * @sw-package framework
 */
import type { uiTabsAddTabItem } from '@shopware-ag/meteor-admin-sdk/es/ui/tabs';
import { useExtensionOrdereredArrayMap } from '../composables/use-extension-ordered-container';
import { computed, unref } from 'vue';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type TabItemEntry = Omit<uiTabsAddTabItem, 'responseType' | 'positionId'>;

const tabsStore = Shopware.Store.register(`tabs`, () => {
    const tabs = useExtensionOrdereredArrayMap<TabItemEntry>();
    const tabItems = computed(() => {
        const record = unref(tabs.items);
        return Object.fromEntries(
            Object.entries(record).map(([key, itemRef]) => [key, unref(itemRef) ?? []] as const),
        ) as Record<string, TabItemEntry[]>;
    });

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
        flushByCurrentExtension: tabs.flushByCurrentExtension,
    };
});

/**
 * @private
 */
export type TabsStore = ReturnType<typeof tabsStore>;
