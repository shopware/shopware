import type { ContentSystemStyleOptionSpecification } from 'src/core/service/api/content-system-style-option.api.service';

type ExperienceStudioStyleOptionState = {
    isLoading: boolean;
    hasLoaded: boolean;
    loadError: string | null;
    optionsByName: Record<string, ContentSystemStyleOptionSpecification>;
};

/**
 * @private
 * @sw-package discovery
 */
const experienceStudioStyleOptionStore = Shopware.Store.register({
    id: 'experienceStudioStyleOption',

    state: (): ExperienceStudioStyleOptionState => ({
        isLoading: false,
        hasLoaded: false,
        loadError: null,
        optionsByName: {},
    }),

    getters: {
        getByName: (state) => {
            return (name: string): ContentSystemStyleOptionSpecification | null => {
                return state.optionsByName[name] ?? null;
            };
        },

        allOptions: (state): ContentSystemStyleOptionSpecification[] => {
            return Object.values(state.optionsByName);
        },
    },

    actions: {
        async loadStyleOptions(force = false): Promise<void> {
            if (this.isLoading) {
                return;
            }

            if (this.hasLoaded && !force) {
                return;
            }

            this.isLoading = true;
            this.loadError = null;

            try {
                const service = Shopware.Service('contentSystemStyleOptionService') as {
                    getStyleOptions: () => Promise<Record<string, ContentSystemStyleOptionSpecification>>;
                };
                const styleOptions = await service.getStyleOptions();
                const nextMap: Record<string, ContentSystemStyleOptionSpecification> = {};

                for (const [
                    name,
                    option,
                ] of Object.entries(styleOptions)) {
                    nextMap[name] = option;
                }

                this.optionsByName = nextMap;
                this.hasLoaded = true;
            } catch (error) {
                this.loadError = (error as Error)?.message ?? 'Failed to load content system style options.';
            } finally {
                this.isLoading = false;
            }
        },
    },
});

/**
 * @private
 */
export type ExperienceStudioStyleOptionStore = ReturnType<typeof experienceStudioStyleOptionStore>;

/**
 * @private
 */
export default experienceStudioStyleOptionStore;
