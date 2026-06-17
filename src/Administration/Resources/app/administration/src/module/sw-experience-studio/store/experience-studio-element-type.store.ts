import type { ContentSystemElementTypeSpecification } from 'src/core/service/api/content-system-element-type.api.service';

type ExperienceStudioElementTypeState = {
    isLoading: boolean;
    hasLoaded: boolean;
    loadError: string | null;
    typesByName: Record<string, ContentSystemElementTypeSpecification>;
};

/**
 * @private
 * @sw-package discovery
 */
const experienceStudioElementTypeStore = Shopware.Store.register({
    id: 'experienceStudioElementType',

    state: (): ExperienceStudioElementTypeState => ({
        isLoading: false,
        hasLoaded: false,
        loadError: null,
        typesByName: {},
    }),

    getters: {
        getByName: (state) => {
            return (name: string): ContentSystemElementTypeSpecification | null => {
                return state.typesByName[name] ?? null;
            };
        },

        allTypes: (state): ContentSystemElementTypeSpecification[] => {
            return Object.values(state.typesByName);
        },
    },

    actions: {
        async loadTypes(force = false): Promise<void> {
            if (this.isLoading) {
                return;
            }

            if (this.hasLoaded && !force) {
                return;
            }

            this.isLoading = true;
            this.loadError = null;

            try {
                const service = Shopware.Service('contentSystemElementTypeService');
                const types = await service.getTypes();
                const nextMap: Record<string, ContentSystemElementTypeSpecification> = {};

                for (const type of types) {
                    nextMap[type.name] = type;
                }

                this.typesByName = nextMap;
                this.hasLoaded = true;
            } catch (error) {
                this.loadError = (error as Error)?.message ?? 'Failed to load content system element types.';
            } finally {
                this.isLoading = false;
            }
        },
    },
});

/**
 * @private
 */
export type ExperienceStudioElementTypeStore = ReturnType<typeof experienceStudioElementTypeStore>;

/**
 * @private
 */
export default experienceStudioElementTypeStore;
