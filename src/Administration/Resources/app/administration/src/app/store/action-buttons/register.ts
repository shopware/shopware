/**
 * @sw-package framework
 */
import * as ActionButtonsStore from './index';
import { actionButtonsStoreOptions } from './private';

Shopware.Store.register(actionButtonsStoreOptions);
Shopware._private.publish('stores', 'actionButtons', ActionButtonsStore);
