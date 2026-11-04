/**
 * @sw-package framework
 */
import { ref } from 'vue';
import type { Ref } from 'vue';

/**
 * user_config key the module icon color preference is persisted under for the current user.
 *
 * @private
 */
export const USER_MODULE_ICON_COLORS_CONFIG_KEY = 'core.userModuleIconColors';

type UseModuleIconColorsReturn = {
    /**
     * Whether module colors are painted onto the module icons. Off by default, so the
     * icons use the neutral icon token.
     */
    enabled: Ref<boolean>;
    /**
     * Loads the persisted preference of the current user from the server.
     */
    loadUserModuleIconColors: () => Promise<void>;
    /**
     * Applies the given preference and persists it server-side in the user configuration
     * of the current user.
     */
    saveUserModuleIconColors: (enabled: boolean) => Promise<void>;
};

const enabled = ref(false);

async function loadUserModuleIconColors(): Promise<void> {
    const response = await Shopware.Service('userConfigService').search([USER_MODULE_ICON_COLORS_CONFIG_KEY]);
    const value = response?.data?.[USER_MODULE_ICON_COLORS_CONFIG_KEY] as { enabled?: unknown } | undefined;

    enabled.value = value?.enabled === true;
}

async function saveUserModuleIconColors(next: boolean): Promise<void> {
    enabled.value = next;

    await Shopware.Service('userConfigService').upsert({
        [USER_MODULE_ICON_COLORS_CONFIG_KEY]: { enabled: next },
    });
}

/**
 * App-wide singleton for the opt-in preference that paints the admin menu and search bar
 * icons and the default media folders in the color of their module (`Module.register({ color })`).
 * The state lives at module scope, so it is shared by every consumer without a store registration.
 *
 * @private
 */
export default function useModuleIconColors(): UseModuleIconColorsReturn {
    return { enabled, loadUserModuleIconColors, saveUserModuleIconColors };
}
