/**
 * @sw-package framework
 */

import conditionRemovedKeys from './condition-removed-keys';
import type { DeprecationMigration } from '../helpers';

export default [
    conditionRemovedKeys,
] satisfies DeprecationMigration[];
