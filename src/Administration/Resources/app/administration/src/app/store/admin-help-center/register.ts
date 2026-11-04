/**
 * @sw-package framework
 */
import * as AdminHelpCenterStore from './index';
import { adminHelpCenterStoreOptions } from './private';

Shopware.Store.register(adminHelpCenterStoreOptions);
Shopware._private.publish('stores', 'adminHelpCenter', AdminHelpCenterStore);
