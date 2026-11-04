/**
 * @sw-package framework
 */
import * as SdkLocationStore from './index';
import { sdkLocationStoreOptions } from './private';

Shopware.Store.register(sdkLocationStoreOptions);
Shopware._private.publish('stores', 'sdkLocation', SdkLocationStore);
