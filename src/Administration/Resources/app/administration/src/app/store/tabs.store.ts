/**
 * @sw-package framework
 */
import type { uiTabsAddTabItem, uiTabsSetVisibility } from '@shopware-ag/meteor-admin-sdk/es/ui/tabs';

/**
 * @private
 */
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
        addTabItem({ label, componentSectionId, positionId, visible }: Omit<uiTabsAddTabItem, 'responseType'>): void {
            if (!this.tabItems[positionId]) {
                this.tabItems[positionId] = [];
            }

            // Upsert by componentSectionId so an extension can re-register the same tab to update
            // its visibility (or label) instead of pushing a duplicate.
            const existing = this.tabItems[positionId].find((item) => item.componentSectionId === componentSectionId);

            if (existing) {
                existing.label = label;
                existing.visible = visible;

                return;
            }

            this.tabItems[positionId].push({
                label,
                componentSectionId,
                visible,
            });
        },

        setVisibility({ positionId, componentSectionId, visible }: Omit<uiTabsSetVisibility, 'responseType'>): void {
            const existing = this.tabItems[positionId]?.find((item) => item.componentSectionId === componentSectionId);

            if (!existing) {
                Shopware.Utils.debug.warn(
                    'TabsStore',
                    `Cannot set visibility for unknown tab item "${componentSectionId}" at position "${positionId}"`,
                );

                return;
            }

            existing.visible = visible;
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
