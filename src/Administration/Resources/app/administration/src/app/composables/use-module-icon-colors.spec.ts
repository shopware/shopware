/**
 * @sw-package framework
 */
import useModuleIconColors, { USER_MODULE_ICON_COLORS_CONFIG_KEY } from './use-module-icon-colors';

describe('src/app/composables/use-module-icon-colors.ts', () => {
    afterEach(() => {
        useModuleIconColors().enabled.value = false;
    });

    it('is disabled by default', () => {
        expect(useModuleIconColors().enabled.value).toBe(false);
    });

    it('returns the same state on every call', () => {
        expect(useModuleIconColors().enabled).toBe(useModuleIconColors().enabled);
    });

    it('applies and persists the preference to the user configuration', async () => {
        await useModuleIconColors().saveUserModuleIconColors(true);

        expect(useModuleIconColors().enabled.value).toBe(true);
        // eslint-disable-next-line @typescript-eslint/unbound-method
        expect(Shopware.Service('userConfigService').upsert).toHaveBeenCalledWith({
            [USER_MODULE_ICON_COLORS_CONFIG_KEY]: { enabled: true },
        });
    });

    it('loads the persisted preference', async () => {
        (Shopware.Service('userConfigService').search as jest.Mock).mockResolvedValueOnce({
            data: {
                [USER_MODULE_ICON_COLORS_CONFIG_KEY]: { enabled: true },
            },
        });

        await useModuleIconColors().loadUserModuleIconColors();

        // eslint-disable-next-line @typescript-eslint/unbound-method
        expect(Shopware.Service('userConfigService').search).toHaveBeenCalledWith([
            USER_MODULE_ICON_COLORS_CONFIG_KEY,
        ]);
        expect(useModuleIconColors().enabled.value).toBe(true);
    });

    it('falls back to disabled when nothing is persisted', async () => {
        useModuleIconColors().enabled.value = true;

        await useModuleIconColors().loadUserModuleIconColors();

        expect(useModuleIconColors().enabled.value).toBe(false);
    });
});
