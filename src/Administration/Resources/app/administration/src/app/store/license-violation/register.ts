/**
 * @sw-package framework
 */
import * as LicenseViolationStore from './index';
import { licenseViolationStoreOptions } from './private';

Shopware.Store.register(licenseViolationStoreOptions);
Shopware._private.publish('stores', 'licenseViolation', LicenseViolationStore);
