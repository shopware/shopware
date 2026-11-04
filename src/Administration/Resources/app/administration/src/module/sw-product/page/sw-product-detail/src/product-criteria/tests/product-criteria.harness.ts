import type { Criteria } from '../dependencies';

/**
 * @sw-package inventory
 */

/** @private */
export { createDefaultFeatureSetCriteria, createProductCriteria, createPropertyCriteria } from '../private';

/**
 * The request body a criteria sends to the API, which is what the server sees.
 *
 * @private
 */
export function toRequest(criteria: Criteria): Record<string, unknown> {
    return criteria.parse() as Record<string, unknown>;
}
