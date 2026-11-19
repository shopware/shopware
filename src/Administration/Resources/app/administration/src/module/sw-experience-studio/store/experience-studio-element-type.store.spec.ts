/**
 * @sw-package discovery
 */

import type { ContentSystemElementTypeSpecification } from 'src/core/service/api/content-system-element-type.api.service';
import type { ExperienceStudioElementTypeStore } from './experience-studio-element-type.store';
import './experience-studio-element-type.store';

describe('experienceStudioElementType store', () => {
    const grid: ContentSystemElementTypeSpecification = {
        name: 'Sw:Grid:Container',
        label: 'Grid',
        description: '',
        source: 'core',
        icon: null,
        illustration: null,
        category: null,
        priority: 0,
        copilot: { summary: '', hints: [] },
        properties: {},
        slots: [],
    };
    let store: ExperienceStudioElementTypeStore;

    beforeEach(() => {
        store = Shopware.Store.get('experienceStudioElementType' as never) as ExperienceStudioElementTypeStore;
        store.$reset();
    });

    afterEach(() => jest.restoreAllMocks());

    it('stores fallbacks with the types and replaces both on a forced refresh', async () => {
        const getMetadata = jest
            .fn()
            .mockResolvedValueOnce({ types: [grid], breakpointFallbacks: { xs: ['sm', 'lg'] } })
            .mockResolvedValueOnce({ types: [], breakpointFallbacks: { xs: ['lg'] } });
        jest.spyOn(Shopware, 'Service').mockReturnValue({ getMetadata } as never);

        expect(store.breakpointFallbacks).toEqual({});
        await store.loadTypes();

        expect(store.getByName(grid.name)).toEqual(grid);
        expect(store.allTypes).toEqual([grid]);
        expect(store.breakpointFallbacks).toEqual({ xs: ['sm', 'lg'] });
        expect(store.hasLoaded).toBe(true);

        await store.loadTypes();
        expect(getMetadata).toHaveBeenCalledTimes(1);

        await store.loadTypes(true);
        expect(store.getByName(grid.name)).toBeNull();
        expect(store.breakpointFallbacks).toEqual({ xs: ['lg'] });
    });

    it('records a failed metadata load and allows a retry', async () => {
        const getMetadata = jest
            .fn()
            .mockRejectedValueOnce(new Error('Metadata unavailable'))
            .mockResolvedValueOnce({ types: [grid], breakpointFallbacks: { xxl: ['xl', 'lg'] } });
        jest.spyOn(Shopware, 'Service').mockReturnValue({ getMetadata } as never);

        await store.loadTypes();

        expect(store.loadError).toBe('Metadata unavailable');
        expect(store.hasLoaded).toBe(false);
        expect(store.isLoading).toBe(false);

        await store.loadTypes();
        expect(store.loadError).toBeNull();
        expect(store.breakpointFallbacks).toEqual({ xxl: ['xl', 'lg'] });
    });
});
