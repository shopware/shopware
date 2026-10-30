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

/** Checks the privileges of the logged-in user. @public */
export const acl = 'acl';

/** Feature flags, as a service for templates and components. @public */
export const feature = 'feature';

/** Builds the filters of listing sidebars. @public */
export const filterFactory = 'filterFactory';

/** Stores and merges the filters a user set in a listing. @public */
export const filterService = 'filterService';

/** Calls the order API, for example to recalculate an order. @public */
export const orderService = 'orderService';

/** Moves orders, deliveries and transactions through their state machines. @public */
export const orderStateMachineService = 'orderStateMachineService';

/** Picks the colour and icon of a state. @public */
export const stateStyleDataProviderService = 'stateStyleDataProviderService';
