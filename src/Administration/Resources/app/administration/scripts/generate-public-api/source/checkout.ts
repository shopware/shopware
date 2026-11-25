/**
 * @sw-package framework
 *
 * The Administration checkout this script runs in: where it is, and which of its feature flags are major flags.
 */
import path from 'node:path';
import { loadFeatureConfig, majorFlagsOf } from '../../feature-flags';

/** @private */
export const ADMINISTRATION_ROOT = path.resolve(__dirname, '../../..');

/** @private */
export function currentMajorFlags(): string[] {
    return majorFlagsOf(loadFeatureConfig());
}
