/**
 * @sw-package framework
 */

type ContextSettings = {
    disableExtensionManagement?: boolean;
    airGapped?: boolean;
};

function getSettings(): ContextSettings | undefined {
    return Shopware.Store.get('context').app.config.settings as ContextSettings | undefined;
}

/**
 * Returns true when this installation must not call Shopware-operated SaaS hosts.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function isAirGapped(): boolean {
    return getSettings()?.airGapped === true;
}

/**
 * Returns true when the Shopware Store / account / marketplace is unavailable.
 * Air-gapped mode and disabled runtime extension management both skip Store traffic.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function isShopwareStoreUnavailable(): boolean {
    const settings = getSettings();

    return settings?.disableExtensionManagement === true || settings?.airGapped === true;
}
