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

        removeSidebar(locationId: string): void {
            this.locationIds = this.locationIds.filter((id) => id !== locationId);

            if (this.activeLocationId === locationId) {
                this.activeLocationId = null;
            }
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
