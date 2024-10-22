/**
 * @package admin
 * @private
 * @description Apply for upselling service only, no public usage
 */
import type { AppModuleDefinition } from '../../core/service/api/app-modules.service';
import type { ModuleManifest } from '../../core/factory/module.factory';

type NavigationEntry = Exclude<ModuleManifest['navigation'], undefined>[number];

interface MenuService {
    getNavigationFromApps(apps: AppModuleDefinition[]): AppModuleDefinition[];
}

const adminMenuStore = Shopware.Store.register({
    id: 'adminMenu',

    state: () => ({
        /**
         * The expanded state of the sidebar menu
         */
        isExpanded: true,
        /**
         * The entries that are currently expanded in the sidebar menu
         */
        expandedEntries: [] as NavigationEntry[],
        /**
         * The navigation entries for the sidebar menu
         */
        adminModuleNavigation: [] as NavigationEntry[],
    }),

    actions: {
        /**
         * Clears the expanded menu entries collapsing all entries
         */
        clearExpandedMenuEntries() {
            this.expandedEntries = [];
        },
        /**
         * Expands a sidebar menu entry
         * @param entry The Navigation Entry to expand
         */
        expandMenuEntry(entry: NavigationEntry) {
            this.expandedEntries.push(entry);
        },
        /**
         * Collapses a sidebar menu entry
         * @param entry The Navigation Entry to collapse
         */
        collapseMenuEntry(entry: NavigationEntry) {
            this.expandedEntries = this.expandedEntries.filter((e) => e.id !== entry.id);
        },
        /**
         * Expands the  sidebar menu
         */
        collapseSidebar() {
            this.isExpanded = false;
        },
        /**
         * Collapses the sidebar menu
         */
        expandSidebar() {
            this.isExpanded = true;
        },
    },

    getters: {
        appModuleNavigation() {
            const menuService = Shopware.Service('menuService') as MenuService;
            // eslint-disable-next-line no-warning-comments
            // TODO: Change this when `shopwareApps` store is converted to Pinia
            const shopwareAppsState = Shopware.State.get('shopwareApps') as { apps: AppModuleDefinition[] };

            return menuService?.getNavigationFromApps(shopwareAppsState.apps);
        },
    },
});

/**
 * @private
 */
export type AdminMenuStore = ReturnType<typeof adminMenuStore>;

/**
 * @private
 * @description
 * The `adminMenuStore` is responsible for managing the state of the sidebar menu.
 */
export default adminMenuStore;
