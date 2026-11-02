/**
 * @sw-package framework
 */

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
