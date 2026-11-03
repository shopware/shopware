/**
 * @sw-package framework
 */

/**
 * Tells whether a feature flag is active.
 *
 * @example
 * export default { inject: [feature] };
 *
 * @public
 */
export const feature = 'feature';

/**
 * Creates the repositories for entities.
 *
 * @example
 * export default { inject: [repositoryFactory] };
 *
 * @public
 */
export const repositoryFactory = 'repositoryFactory';

/**
 * Knows the rule conditions, and which rules an entity may not use.
 *
 * @example
 * export default { inject: [ruleConditionDataProviderService] };
 *
 * @public
 */
export const ruleConditionDataProviderService = 'ruleConditionDataProviderService';

/**
 * Activates and deactivates Shopware services and reads the permissions they ask for.
 *
 * @example
 * const servicesService = Shopware.Service(shopwareServicesService);
 *
 * @public
 */
export const shopwareServicesService = 'shopwareServicesService';
