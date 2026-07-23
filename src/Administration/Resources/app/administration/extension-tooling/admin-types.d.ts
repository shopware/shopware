/**
 * @sw-package framework
 *
 * The single type surface for Administration extensions: the live installed
 * types of this Administration. Leaf tsconfigs and plugin shims inject this
 * file via "files" so extension code sees exactly the API of the installed
 * Shopware version, including the installation-specific entity schema.
 *
 * The entity schema import resolves to the generated
 * `src/entity-schema-definition.d.ts`. When that file has not been generated
 * yet, the setup command writes a stub that keeps `EntitySchema.Entities`
 * empty so missing schema types fail loudly instead of degrading to `any`.
 */

/// <reference types="node" />

import '../src/global.types';
import '../src/entity-schema-definition';
import '../src/html-shim';

// Global `ServiceContainer` augmentations that live outside the module graph
// reachable from `global.types.ts`. The Administration's own program compiles
// all of `src/**/*`, so these are implicitly present there; extension programs
// only see them through an explicit import. `type-surface.spec.ts` guards this
// list against new augmentations drifting out of the surface.
import '../src/module/sw-flow/service';
import '../src/module/sw-extension/service';
import '../src/module/sw-settings-services/service';
