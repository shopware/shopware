/**
 * @sw-package framework
 */
import * as ExtensionsStore from './index';
import { extensionsStoreOptions } from './private';

Shopware.Store.register(extensionsStoreOptions);
Shopware._private.publish('stores', 'extensions', ExtensionsStore);
