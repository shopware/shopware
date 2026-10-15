/**
 * @sw-package discovery
 */

import type { ContentSystemMappingCandidate } from 'src/core/service/api/content-system-mapping-candidate.api.service';
import type { ExperienceStudioMappingCandidateStore } from './experience-studio-mapping-candidate.store';
import './experience-studio-mapping-candidate.store';

describe('src/module/sw-experience-studio/store/experience-studio-mapping-candidate.store.ts', () => {
    const candidate: ContentSystemMappingCandidate = {
        path: 'category.name',
        source: { type: 'root', id: 'category', path: 'name' },
        label: 'category.name',
        description: 'Category name',
        group: 'basic',
        valueType: 'string',
        contextType: 'single',
        projection: null,
    };

    const getStore = () =>
        Shopware.Store.get('experienceStudioMappingCandidate' as never) as ExperienceStudioMappingCandidateStore;

    beforeEach(() => {
        getStore().$reset();
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('returns candidates for a root source and an empty list for unknown sources', async () => {
        jest.spyOn(Shopware, 'Service').mockReturnValue({
            getMappingCandidates: jest.fn().mockResolvedValue({ category: [candidate] }),
        } as never);

        const store = getStore();
        await store.loadMappingCandidates();

        expect(store.getByRootSource('category')).toEqual([candidate]);
        expect(store.getByRootSource('product')).toEqual([]);
        expect(store.getByRootSource(null)).toEqual([]);
        expect(store.hasLoaded).toBe(true);
        expect(store.isLoading).toBe(false);
    });

    it('does not reload after a successful request unless forced', async () => {
        const getMappingCandidates = jest.fn().mockResolvedValue({ category: [candidate] });
        jest.spyOn(Shopware, 'Service').mockReturnValue({ getMappingCandidates } as never);

        const store = getStore();
        await store.loadMappingCandidates();
        await store.loadMappingCandidates();

        expect(getMappingCandidates).toHaveBeenCalledTimes(1);

        await store.loadMappingCandidates(true);

        expect(getMappingCandidates).toHaveBeenCalledTimes(2);
    });

    it('records a load error and clears the loading state', async () => {
        jest.spyOn(Shopware, 'Service').mockReturnValue({
            getMappingCandidates: jest.fn().mockRejectedValue(new Error('catalogue unavailable')),
        } as never);

        const store = getStore();
        await store.loadMappingCandidates();

        expect(store.loadError).toBe('catalogue unavailable');
        expect(store.hasLoaded).toBe(false);
        expect(store.isLoading).toBe(false);
    });
});
