/**
 * @sw-package framework
 */
import type { uiTabsAddTabItem } from '@shopware-ag/meteor-admin-sdk/es/ui/tabs';

// `visible` lets an extension show/hide its own registered tab (e.g. based on the currently
// opened entity). It is optional and defaults to visible; renderers hide an entry only when it
// is explicitly `false`. Declared locally until it lands on the SDK `uiTabsAddTabItem` type.
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type TabItemEntry = Omit<uiTabsAddTabItem, 'responseType' | 'positionId'> & {
    visible?: boolean;
};

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
        addTabItem({
            label,
            componentSectionId,
            positionId,
            visible,
        }: Omit<uiTabsAddTabItem, 'responseType'> & { visible?: boolean }): void {
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
