/**
 * @sw-package discovery
 */

import type { ContentSystemLayoutPreset } from 'src/core/service/api/content-system-layout-preset.api.service';
import type { ExperienceStudioLayoutPresetStore } from './experience-studio-layout-preset.store';
import './experience-studio-layout-preset.store';

describe('src/module/sw-experience-studio/store/experience-studio-layout-preset.store.ts', () => {
    const presets: ContentSystemLayoutPreset[] = [
        { id: 'core.text-block', name: 'Text block', description: 'A text block', icon: 'regular-align-left', payload: [] },
    ];

    const getStore = () =>
        Shopware.Store.get('experienceStudioLayoutPreset' as never) as ExperienceStudioLayoutPresetStore;

    beforeEach(() => {
        getStore().$reset();
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('registers the store', () => {
        expect(getStore()).toBeDefined();
    });

    it('loads presets into an id-keyed map', async () => {
        jest.spyOn(Shopware, 'Service').mockReturnValue({ getPresets: () => Promise.resolve(presets) } as never);

        const store = getStore();
        await store.loadPresets();

        expect(store.allPresets).toEqual(presets);
        expect(store.getById('core.text-block')).toEqual(presets[0]);
        expect(store.getById('core.unknown')).toBeNull();
        expect(store.hasLoaded).toBe(true);
    });

    it('does not reload once loaded unless forced', async () => {
        const getPresets = jest.fn().mockResolvedValue(presets);
        jest.spyOn(Shopware, 'Service').mockReturnValue({ getPresets } as never);

        const store = getStore();
        await store.loadPresets();
        await store.loadPresets();

        expect(getPresets).toHaveBeenCalledTimes(1);

        await store.loadPresets(true);

        expect(getPresets).toHaveBeenCalledTimes(2);
    });

    it('records a load error and stays unloaded', async () => {
        jest.spyOn(Shopware, 'Service').mockReturnValue({ getPresets: () => Promise.reject(new Error('boom')) } as never);

        const store = getStore();
        await store.loadPresets();

        expect(store.loadError).toBe('boom');
        expect(store.hasLoaded).toBe(false);
    });
});
