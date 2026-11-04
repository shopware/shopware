/**
 * @sw-package framework
 */
import * as SettingsItemsStore from './index';
import { settingsItemsStoreOptions } from './private';

Shopware.Store.register(settingsItemsStoreOptions);
Shopware._private.publish('stores', 'settingsItems', SettingsItemsStore);
