import { loadEnv } from 'vite';

/**
 * How the Storefront dev server brings a source change to the open page.
 *
 *   reload: the page reloads (default)
 *   hmr:    the change is applied to the open page in place
 *   manual: nothing is sent to the page, the developer reloads it
 */
export type StorefrontDevUpdate = 'reload' | 'hmr' | 'manual';

export const STOREFRONT_DEV_UPDATE_ENV = 'SHOPWARE_STOREFRONT_DEV_UPDATE';

const MODES: readonly StorefrontDevUpdate[] = ['reload', 'hmr', 'manual'];

function isStorefrontDevUpdate(value: string): value is StorefrontDevUpdate {
    return (MODES as readonly string[]).includes(value);
}

/**
 * Reads `SHOPWARE_STOREFRONT_DEV_UPDATE` from the shell or from the `.env` files of the project root.
 * The shell wins over `.env.local`, which wins over `.env`.
 *
 * @param projectRoot  Absolute path to the Shopware project root.
 * @param mode         The Vite mode, which selects the `.env.[mode]` files.
 * @param warn         Reports an unknown value.
 */
export function resolveStorefrontDevUpdate(
    projectRoot: string,
    mode: string,
    warn: (message: string) => void = console.warn,
): StorefrontDevUpdate {
    const value = loadEnv(mode, projectRoot, STOREFRONT_DEV_UPDATE_ENV)[STOREFRONT_DEV_UPDATE_ENV]?.trim() ?? '';

    if (value === '') {
        return 'reload';
    }

    if (isStorefrontDevUpdate(value)) {
        return value;
    }

    warn(`[sw-dev-update] Unknown ${STOREFRONT_DEV_UPDATE_ENV} value "${value}". Use ${MODES.join(', ')}. Falling back to reload.`);

    return 'reload';
}
