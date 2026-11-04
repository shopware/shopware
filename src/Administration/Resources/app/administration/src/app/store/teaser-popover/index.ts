/**
 * @sw-package innovation
 *
 * The `teaserPopover` Pinia store of the Administration.
 * This file is the public API, also for extensions, which reach the store as `shopware:stores/teaserPopover`.
 */
/**
 * @public
 * @export-namespace stores
 */
export { teaserPopoverStoreId, useTeaserPopoverStore } from './src/teaser-popover.store';

/** @public */
export type { TeaserPopoverConfig, TeaserSalesChannelConfig, TeaserPopoverStore } from './src/teaser-popover.store';
