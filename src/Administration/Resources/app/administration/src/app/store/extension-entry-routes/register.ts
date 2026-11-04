/**
 * @sw-package framework
 */
import * as ExtensionEntryRoutesStore from './index';
import { extensionEntryRoutesStoreOptions } from './private';

Shopware.Store.register(extensionEntryRoutesStoreOptions);
Shopware._private.publish('stores', 'extensionEntryRoutes', ExtensionEntryRoutesStore);
