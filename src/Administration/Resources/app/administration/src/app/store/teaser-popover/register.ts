/**
 * @sw-package innovation
 */
import * as TeaserPopoverStore from './index';
import { teaserPopoverStoreOptions } from './private';

Shopware.Store.register(teaserPopoverStoreOptions);
Shopware._private.publish('stores', 'teaserPopover', TeaserPopoverStore);
