import type { ContentSystemLayoutPreset } from 'src/core/service/api/content-system-layout-preset.api.service';

type ExperienceStudioLayoutPresetState = {
    isLoading: boolean;
    hasLoaded: boolean;
    loadError: string | null;
    presetsById: Record<string, ContentSystemLayoutPreset>;
};

/**
 * @private
 * @sw-package discovery
 */
const experienceStudioLayoutPresetStore = Shopware.Store.register({
    id: 'experienceStudioLayoutPreset',

    state: (): ExperienceStudioLayoutPresetState => ({
        isLoading: false,
        hasLoaded: false,
        loadError: null,
        presetsById: {},
    }),

    getters: {
        getById: (state) => {
            return (id: string): ContentSystemLayoutPreset | null => {
                return state.presetsById[id] ?? null;
            };
        },

        allPresets: (state): ContentSystemLayoutPreset[] => {
            return Object.values(state.presetsById);
        },
    },

    actions: {
        async loadPresets(force = false): Promise<void> {
            if (this.isLoading) {
                return;
            }

            if (this.hasLoaded && !force) {
                return;
            }

            this.isLoading = true;
            this.loadError = null;

            try {
                const service = Shopware.Service('contentSystemLayoutPresetService');
                const presets = await service.getPresets();
                const nextMap: Record<string, ContentSystemLayoutPreset> = {};

                for (const preset of presets) {
                    nextMap[preset.id] = preset;
                }

                this.presetsById = nextMap;
                this.hasLoaded = true;
            } catch (error) {
                this.loadError = (error as Error)?.message ?? 'Failed to load content system layout presets.';
            } finally {
                this.isLoading = false;
            }
        },
    },
});

/**
 * @private
 */
export type ExperienceStudioLayoutPresetStore = ReturnType<typeof experienceStudioLayoutPresetStore>;

/**
 * @private
 */
export default experienceStudioLayoutPresetStore;
