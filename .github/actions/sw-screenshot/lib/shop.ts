/**
 * Coordinates of the Shopware instance this run drives.
 *
 * Every command in the CLI needs the same three things to talk to the shop, and they always come
 * from the environment the workflow exported. Resolving them in one place means a command never
 * has to know which env var carries what. There is deliberately no filesystem path here: the shop
 * lives outside the agent's sandbox and is reached over HTTP only.
 */
export interface ShopCoordinates {
    /** Origin of the running shop, always with a trailing slash (the Admin API path is appended to it). */
    appUrl: string;
    /** Admin user with API access. Provisioned installs use `admin`. */
    username: string;
    /** Password for {@link username}. Provisioned installs use `shopware`. */
    password: string;
}

/**
 * Read the shop coordinates from the environment, applying the defaults a provisioned CI shop uses.
 *
 * Use this at the top of every CLI command instead of reading `process.env` inline — it is the only
 * place that knows `APP_URL` must end in a slash, which `new URL(path, base)` silently depends on
 * (without it, `new URL('api/product', 'http://x/shop')` drops the last segment).
 *
 * @example
 * const shop = resolveShop();
 * // { appUrl: 'http://host.docker.internal/', username: 'admin', password: 'shopware' }
 */
export function resolveShop(): ShopCoordinates {
    const raw = process.env['APP_URL'] ?? 'http://localhost:8000';

    return {
        appUrl: raw.endsWith('/') ? raw : `${raw}/`,
        username: process.env['SHOPWARE_ADMIN_USERNAME'] || 'admin',
        password: process.env['SHOPWARE_ADMIN_PASSWORD'] || 'shopware',
    };
}
