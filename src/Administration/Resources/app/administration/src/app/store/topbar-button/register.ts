/**
 * @sw-package innovation
 */
import * as TopBarButtonStore from './index';
import { topBarButtonStoreOptions } from './private';

Shopware.Store.register(topBarButtonStoreOptions);
Shopware._private.publish('stores', 'topBarButton', TopBarButtonStore);
