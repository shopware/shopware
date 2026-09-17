import type { ContentSystemMappingCandidate } from 'src/core/service/api/content-system-mapping-candidate.api.service';

type ExperienceStudioMappingCandidateState = {
    isLoading: boolean;
    hasLoaded: boolean;
    loadError: string | null;
    candidatesByRootSource: Record<string, ContentSystemMappingCandidate[]>;
};

/**
 * @private
 * @sw-package discovery
 */
const experienceStudioMappingCandidateStore = Shopware.Store.register({
    id: 'experienceStudioMappingCandidate',

    state: (): ExperienceStudioMappingCandidateState => ({
        isLoading: false,
        hasLoaded: false,
        loadError: null,
        candidatesByRootSource: {},
    }),

    getters: {
        getByRootSource: (state) => {
            return (rootSource: string | null): ContentSystemMappingCandidate[] => {
                if (rootSource === null) {
                    return [];
                }

                return state.candidatesByRootSource[rootSource] ?? [];
            };
        },
    },

    actions: {
        async loadMappingCandidates(force = false): Promise<void> {
            if (this.isLoading) {
                return;
            }

            if (this.hasLoaded && !force) {
                return;
            }

            this.isLoading = true;
            this.loadError = null;

            try {
                const service = Shopware.Service('contentSystemMappingCandidateService');
                const candidates = await service.getMappingCandidates();
                const nextMap: Record<string, ContentSystemMappingCandidate[]> = {};

                for (const [
                    rootSource,
                    rootSourceCandidates,
                ] of Object.entries(candidates)) {
                    nextMap[rootSource] = Array.isArray(rootSourceCandidates) ? rootSourceCandidates : [];
                }

                this.candidatesByRootSource = nextMap;
                this.hasLoaded = true;
            } catch (error) {
                this.loadError = (error as Error)?.message ?? 'Failed to load content system mapping candidates.';
            } finally {
                this.isLoading = false;
            }
        },
    },
});

/**
 * @private
 */
export type ExperienceStudioMappingCandidateStore = ReturnType<typeof experienceStudioMappingCandidateStore>;

/**
 * @private
 */
export default experienceStudioMappingCandidateStore;
