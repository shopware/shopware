/**
 * @sw-package framework
 */
import * as MediaModalStore from './index';
import { mediaModalStoreOptions } from './private';

Shopware.Store.register(mediaModalStoreOptions);
Shopware._private.publish('stores', 'mediaModal', MediaModalStore);
