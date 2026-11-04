/**
 * @sw-package framework
 */
import * as ExtensionSdkModulesStore from './index';
import { extensionSdkModulesStoreOptions } from './private';

Shopware.Store.register(extensionSdkModulesStoreOptions);
Shopware._private.publish('stores', 'extensionSdkModules', ExtensionSdkModulesStore);
