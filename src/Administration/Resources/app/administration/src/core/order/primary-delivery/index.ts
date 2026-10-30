/**
 * @sw-package checkout
 *
 * Picks the delivery an order is shown with.
 * This file is the public API, also for extensions. The files in `./src` are private.
 */
export { getPrimaryDelivery } from './src/primary-delivery';
export type { OrderWithDeliveries } from './src/primary-delivery';
