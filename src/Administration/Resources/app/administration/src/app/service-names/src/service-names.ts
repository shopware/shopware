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

/**
 * Checks the privileges of the logged-in user.
 *
 * @example
 * export default { inject: [acl] };
 *
 * @public
 */
export const acl = 'acl';

/**
 * Creates, previews and downloads order documents through the document generation v1 endpoints.
 *
 * @example
 * export default { inject: [documentService] };
 *
 * @public
 */
export const documentService = 'documentService';

/**
 * Creates, uploads, previews and downloads order documents through the document generation v2 endpoints.
 *
 * @example
 * export default { inject: [documentV2ApiService] };
 *
 * @public
 */
export const documentV2ApiService = 'documentV2ApiService';

/**
 * Knows the document types and file formats, and translates their labels and errors.
 *
 * @example
 * export default { inject: [documentV2Service] };
 *
 * @public
 */
export const documentV2Service = 'documentV2Service';

/**
 * Reserves and previews numbers from the number ranges.
 *
 * @example
 * export default { inject: [numberRangeService] };
 *
 * @public
 */
export const numberRangeService = 'numberRangeService';
