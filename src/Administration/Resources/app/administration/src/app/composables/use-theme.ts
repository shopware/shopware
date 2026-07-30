/**
 * @sw-package framework
 */
import { effectScope } from 'vue';
import { useTheme as useMeteorTheme } from '@shopware-ag/meteor-component-library';
import type { Theme, UseThemeReturn } from '@shopware-ag/meteor-component-library';

/**
 * user_config key the theme preference is persisted under for the current user.
 *
 * @private
 */
export const USER_THEME_CONFIG_KEY = 'core.userTheme';

type UseAdminThemeReturn = UseThemeReturn & {
    /**
     * Loads the persisted theme preference of the current user from the
     * server and applies it. Keeps the current (localStorage) preference
     * when the user has not persisted one yet.
     */
    loadUserTheme: () => Promise<void>;
    /**
     * Applies the given theme preference and persists it server-side in the
     * user configuration of the current user.
     */
    saveUserTheme: (theme: Theme) => Promise<void>;
};

let themeState: UseAdminThemeReturn | null = null;

function isTheme(value: unknown): value is Theme {
    return value === 'light' || value === 'dark' || value === 'system';
}

async function loadUserTheme(): Promise<void> {
    const response = await Shopware.Service('userConfigService').search([USER_THEME_CONFIG_KEY]);
    const value = response?.data?.[USER_THEME_CONFIG_KEY] as { theme?: unknown } | undefined;

    if (value && isTheme(value.theme)) {
        useTheme().setTheme(value.theme);
    }
}

async function saveUserTheme(theme: Theme): Promise<void> {
    useTheme().setTheme(theme);

    await Shopware.Service('userConfigService').upsert({
        [USER_THEME_CONFIG_KEY]: { theme },
    });
}

/**
 * App-wide singleton around the Meteor `useTheme` composable. The state is
 * created in a detached effect scope so its watchers are never bound to a
 * component lifecycle, even when the first call happens inside a component.
 *
 * @private
 */
export default function useTheme(): UseAdminThemeReturn {
    if (!themeState) {
        const scope = effectScope(true);

        themeState = {
            ...scope.run(() => useMeteorTheme())!,
            loadUserTheme,
            saveUserTheme,
        };
    }

    return themeState;
}
