/**
 * @sw-package framework
 */
import * as NotificationStore from './index';
import { notificationStoreOptions } from './private';

Shopware.Store.register(notificationStoreOptions);
Shopware._private.publish('stores', 'notification', NotificationStore);
