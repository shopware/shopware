/**
 * @sw-package framework
 */
import {
    defineStoreOptions,
    getStore,
    type StoreOf,
    type uiTabsAddTabItem,
    type uiTabsSetVisibility,
    warn,
} from '../dependencies';

/**
 * @public
 */
export type TabItemEntry = Omit<uiTabsAddTabItem, 'responseType' | 'positionId'>;

interface TabsState {
    tabItems: {
        [positionId: string]: TabItemEntry[];
    };
}

/**
 * @public
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const tabsStoreId = 'tabs';

/**
 * @private
 * The options the store is registered with, see `register.ts`.
 */
export const tabsStoreOptions = defineStoreOptions({
    id: tabsStoreId,

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
                warn(
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
 * @public
 */
export type TabsStore = StoreOf<typeof tabsStoreOptions>;

/**
 * @public
 * The store, looked up when it is called, so everyone who uses it shares one state.
 */
export const useTabsStore = (): TabsStore => getStore(tabsStoreId);
