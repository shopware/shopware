/**
 * @sw-package framework
 */
import { nextTick } from 'vue';
import useTheme from 'src/app/composables/use-theme';
import useModuleIconColors from 'src/app/composables/use-module-icon-colors';
import { SESSION_SNAPSHOT_EVENT, trackSessionSnapshot } from './product-analytics-session-snapshot.service';

describe('src/app/service/product-analytics-session-snapshot.service.ts', () => {
    let trackSpy: jest.SpyInstance;

    beforeEach(() => {
        trackSpy = jest.spyOn(Shopware.Telemetry, 'track');
    });

    afterEach(async () => {
        trackSpy.mockRestore();

        useTheme().setTheme('light');
        useModuleIconColors().enabled.value = false;
        await nextTick();

        localStorage.removeItem('mt-theme');
    });

    it('tracks the applied theme, the stored preference and the module icon colors', () => {
        useTheme().setTheme('dark');
        useModuleIconColors().enabled.value = true;

        trackSessionSnapshot();

        expect(trackSpy).toHaveBeenCalledTimes(1);
        expect(trackSpy).toHaveBeenCalledWith({
            eventName: SESSION_SNAPSHOT_EVENT,
            theme: 'dark',
            theme_preference: 'dark',
            module_icon_colors: true,
        });
    });

    it('tracks the resolved theme instead of the system preference', () => {
        useTheme().setTheme('system');

        trackSessionSnapshot();

        expect(trackSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                theme: 'light',
                theme_preference: 'system',
            }),
        );
    });
});
