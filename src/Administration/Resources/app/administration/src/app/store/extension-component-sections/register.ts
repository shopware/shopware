/**
 * @sw-package framework
 */
import * as ExtensionComponentSectionsStore from './index';
import { extensionComponentSectionsStoreOptions } from './private';

Shopware.Store.register(extensionComponentSectionsStoreOptions);
Shopware._private.publish('stores', 'extensionComponentSections', ExtensionComponentSectionsStore);
