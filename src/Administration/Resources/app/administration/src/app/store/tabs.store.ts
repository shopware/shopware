/**
 * @sw-package framework
 */
import type { uiTabsAddTabItem } from '@shopware-ag/meteor-admin-sdk/es/ui/tabs';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type TabItemEntry = Omit<uiTabsAddTabItem, 'responseType' | 'positionId'>;

interface TabsState {
    tabItems: {
        [positionId: string]: TabItemEntry[];
    };
}

const tabsStore = Shopware.Store.register({
    id: 'tabs',

    state: (): TabsState => ({
        tabItems: {},
    }),

    actions: {
        addTabItem({ label, componentSectionId, positionId }: Omit<uiTabsAddTabItem, 'responseType'>): void {
            if (!this.tabItems[positionId]) {
                this.tabItems[positionId] = [];
            }

            const store = Shopware.Store.get('extensionOrderTracker');
            
            store.insert(this.tabItems[positionId], {
                label,
                componentSectionId,
            });
        },

        /**
         * Flush all tab items for the current extension context from all position IDs.
         */
        flushByCurrentExtension(): void {
            const store = Shopware.Store.get('extensionOrderTracker');
            store.flushMap(this.tabItems);
        },
    },
});

/**
 * @private
 */
export type TabsStore = ReturnType<typeof tabsStore>;

/**
 * @private
 */
export default tabsStore;
