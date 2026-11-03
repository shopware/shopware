/**
 * @sw-package framework
 *
 * Everything `src/` needs from outside the module. Only the generator command loads it, so it may load the TypeScript compiler.
 */

/** @private */
export { default as fs } from 'node:fs';

/** @private */
export { default as path } from 'node:path';

/** @private */
export { default as ts } from 'typescript';

/** @private */
export type { ModuleRegistry } from '../shopware-modules';

/** @private The kinds a mini-module is published under, each a `shopware:<kind>` family. */
export { PUBLIC_KINDS, type PublicKind } from '../../src/core/mini-module-registry';

/** @private */
export { REGISTRY_FILE } from '../shopware-modules/private'; // eslint-disable-line sw-core-rules/mini-module-boundaries -- it writes that file
