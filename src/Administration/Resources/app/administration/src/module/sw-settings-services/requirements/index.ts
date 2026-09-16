/**
 * @sw-package framework
 *
 * Centralizes service requirement checks used to determine
 * available actions and messaging in the Services UI,
 * including services that cannot be removed.
 */

/**
 * @private
 */
export const SHOPWARE_ACCOUNT_REQUIREMENT = 'shopware_account';

/**
 * @private
 */
export interface ServiceWithShopwareAccountRequirement {
    name: string;
    label: string;
}

/**
 * Minimal ServiceDescription type used by this module.
 *
 * @private
 */
export interface ServiceDescription extends ServiceWithShopwareAccountRequirement {
    requirements: string[];
}

/**
 * Services that require a Shopware account cannot be removed or deactivated.
 *
 * @private
 */
export function serviceHasShopwareAccountRequirement(requirements: string[]): boolean {
    return requirements.includes(SHOPWARE_ACCOUNT_REQUIREMENT);
}

/**
 * List of services that require a Shopware account, used to determine messaging in the UI.
 *
 * @private
 */
export function getServicesWithShopwareAccountRequirement(
    services: ServiceDescription[],
): ServiceWithShopwareAccountRequirement[] {
    return services
        .filter((service) => serviceHasShopwareAccountRequirement(service.requirements))
        .map((service) => ({
            name: service.name,
            label: service.label,
        }));
}
