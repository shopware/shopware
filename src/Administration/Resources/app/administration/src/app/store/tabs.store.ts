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

            // Check if tab with same componentSectionId already exists to prevent duplicates
            // This is important for HMR: when extension modules re-execute, they call addTabItem again
            const existingIndex = this.tabItems[positionId].findIndex(
                (item) => item.componentSectionId === componentSectionId,
            );

            if (existingIndex !== -1) {
                // Update existing tab item (label might have changed)
                this.tabItems[positionId][existingIndex] = {
                    label,
                    componentSectionId,
                };
                return;
            }

            this.tabItems[positionId].push({
                label,
                componentSectionId,
            });
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
