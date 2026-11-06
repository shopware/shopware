/**
 * @sw-package framework
 */

interface ExampleSidebarState {
    activeLocationId: string | null;
    locationIds: string[];
}

const exampleSidebarStore = Shopware.Store.register({
    id: 'exampleSidebar',

    state: (): ExampleSidebarState => ({
        activeLocationId: null,
        locationIds: [],
    }),

    getters: {
        hasActiveSidebar(): boolean {
            return this.activeLocationId !== null;
        },
    },

    actions: {
        addSidebar(locationId: string): void {
            if (this.locationIds.includes(locationId)) {
                return;
            }

            this.locationIds.push(locationId);
        },

        setActiveSidebar(locationId: string | null): void {
            this.activeLocationId = locationId;
        },
    },
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type ExampleSidebarStore = ReturnType<typeof exampleSidebarStore>;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default exampleSidebarStore;
