/**
 * @sw-package framework
 */
import * as ExtensionMainModulesStore from './index';
import { extensionMainModulesStoreOptions } from './private';

Shopware.Store.register(extensionMainModulesStoreOptions);
Shopware._private.publish('stores', 'extensionMainModules', ExtensionMainModulesStore);
