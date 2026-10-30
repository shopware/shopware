/**
 * @sw-package checkout
 *
 * Bulk edits orders, including their order, transaction and delivery states.
 * This file is the public API, also for extensions. The files in `./src` are private.
 */
/** @public */
export { default } from './src/bulk-edit-order.handler';
