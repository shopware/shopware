/**
 * @sw-package framework
 */

import adminExtensionSdk from './admin-extension-sdk';
import type { DeprecationMigration } from '../helpers';

export default [
    adminExtensionSdk,
] satisfies DeprecationMigration[];
