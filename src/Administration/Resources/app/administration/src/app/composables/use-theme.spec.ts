/**
 * @sw-package framework
 */
import { nextTick } from 'vue';
import useTheme, { USER_THEME_CONFIG_KEY } from './use-theme';

describe('src/app/composables/use-theme.ts', () => {
    beforeEach(() => {
        (Shopware.Service('userConfigService').search as jest.Mock).mockClear();
        (Shopware.Service('userConfigService').upsert as jest.Mock).mockClear();
    });

    afterEach(async () => {
        useTheme().setTheme('light');
        await nextTick();

        localStorage.removeItem('mt-theme');
    });

    it('returns the same state on every call', () => {
        expect(useTheme()).toBe(useTheme());
    });

    it('defaults to the light theme', () => {
        const { theme, resolvedTheme } = useTheme();

        expect(theme.value).toBe('light');
        expect(resolvedTheme.value).toBe('light');
    });

    it('resolves the chosen theme and applies it to the document root', async () => {
        const { setTheme, resolvedTheme } = useTheme();

        setTheme('dark');
        await nextTick();

        expect(resolvedTheme.value).toBe('dark');
        expect(document.documentElement.getAttribute('data-theme')).toBe('dark');

        setTheme('light');
        await nextTick();

        expect(resolvedTheme.value).toBe('light');
        expect(document.documentElement.getAttribute('data-theme')).toBe('light');
    });

    it('persists the preference to localStorage', async () => {
        useTheme().setTheme('dark');
        await nextTick();

        expect(localStorage.getItem('mt-theme')).toBe('dark');
    });

    it('saves the preference to the user configuration', async () => {
        await useTheme().saveUserTheme('dark');

        expect(useTheme().theme.value).toBe('dark');
        // eslint-disable-next-line @typescript-eslint/unbound-method
        expect(Shopware.Service('userConfigService').upsert).toHaveBeenCalledWith({
            [USER_THEME_CONFIG_KEY]: { theme: 'dark' },
        });
    });

    it('loads and applies the persisted user preference', async () => {
        (Shopware.Service('userConfigService').search as jest.Mock).mockResolvedValueOnce({
            data: {
                [USER_THEME_CONFIG_KEY]: { theme: 'dark' },
            },
        });

        await useTheme().loadUserTheme();

        // eslint-disable-next-line @typescript-eslint/unbound-method
        expect(Shopware.Service('userConfigService').search).toHaveBeenCalledWith([
            USER_THEME_CONFIG_KEY,
        ]);
        expect(useTheme().theme.value).toBe('dark');
    });

    it('keeps the current preference when nothing is persisted', async () => {
        useTheme().setTheme('system');

        await useTheme().loadUserTheme();

        expect(useTheme().theme.value).toBe('system');
    });

    it('ignores invalid persisted values', async () => {
        (Shopware.Service('userConfigService').search as jest.Mock).mockResolvedValueOnce({
            data: {
                [USER_THEME_CONFIG_KEY]: { theme: 'not-a-theme' },
            },
        });

        await useTheme().loadUserTheme();

        expect(useTheme().theme.value).toBe('light');
    });
});
