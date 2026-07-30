/**
 * @sw-package framework
 * @private
 * @description Apply for upselling service only, no public usage
 */
import type { AppModuleDefinition } from '../../core/service/api/app-modules.service';
import type { ModuleManifest } from '../../core/factory/module.factory';

type NavigationEntry = Exclude<ModuleManifest['navigation'], undefined>[number];

interface MenuService {
    getNavigationFromApps(apps: AppModuleDefinition[]): AppModuleDefinition[];
}

/**
 * Navigation entries are not required to carry an `id` — path-only entries are valid — so the
 * identity of an entry is its id with the path as fallback. Must match how the admin menu keys
 * its branches, otherwise collapsing one id-less branch would drop every other id-less entry.
 */
function menuEntryKey(entry: NavigationEntry): string | undefined {
    return entry.id ?? entry.path;
}

const adminMenuStore = Shopware.Store.register({
    id: 'adminMenu',

    state: () => ({
        /**
         * The expanded state of the sidebar menu
         */
        isExpanded: localStorage.getItem('sw-admin-menu-expanded') !== 'false',
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
            const key = menuEntryKey(entry);

            // Entries without id and path share the key `undefined`; deduplicating
            // them would collapse distinct entries into one.
            if (key !== undefined && this.expandedEntries.some((e) => menuEntryKey(e) === key)) {
                return;
            }

            this.expandedEntries.push(entry);
        },
        /**
         * Collapses a sidebar menu entry
         * @param entry The Navigation Entry to collapse
         */
        collapseMenuEntry(entry: NavigationEntry) {
            const key = menuEntryKey(entry);

            if (key === undefined) {
                this.expandedEntries = this.expandedEntries.filter((e) => e !== entry);
                return;
            }

            this.expandedEntries = this.expandedEntries.filter((e) => menuEntryKey(e) !== key);
        },
        /**
         * Collapses the sidebar menu
         */
        collapseSidebar() {
            this.isExpanded = false;
            localStorage.setItem('sw-admin-menu-expanded', 'false');
        },
        /**
         * Expands the sidebar menu
         */
        expandSidebar() {
            this.isExpanded = true;
            localStorage.setItem('sw-admin-menu-expanded', 'true');
        },
    },

    getters: {
        appModuleNavigation() {
            const menuService = Shopware.Service('menuService') as MenuService;
            // eslint-disable-next-line no-warning-comments
            // TODO: Change this when `shopwareApps` store is converted to Pinia
            const shopwareAppsState = Shopware.Store.get('shopwareApps') as { apps: AppModuleDefinition[] };

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
