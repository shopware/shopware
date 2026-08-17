/**
 * @sw-package framework
 *
 * Jest globals (describe, it, expect, jest, beforeEach, …) for Administration
 * extension spec files. Injected via the generated spec tsconfig's "files" so
 * test code can be type-checked without leaking the runner globals into the
 * runtime program — the preset keeps `types: []`, and this explicit reference
 * opts jest back in for the spec program only. It resolves `@types/jest` from
 * the Administration's own node_modules because this file lives inside the
 * admin tree (the same mechanism admin-types.d.ts uses for `node`).
 */

/// <reference types="jest" />
