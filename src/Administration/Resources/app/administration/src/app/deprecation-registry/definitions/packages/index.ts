/**
 * @sw-package framework
 */

import adminExtensionSdk from './admin-extension-sdk';
import type { PackageMigration } from '../helpers';

export default [
    adminExtensionSdk,
] satisfies PackageMigration[];
