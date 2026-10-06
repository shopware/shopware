/**
 * @sw-package framework
 */

/**
 * Identifier-flow analysis for the Shopware setup transform.
 *
 * A small, encapsulated API over the Babel AST for the one question the transform keeps asking in
 * different shapes: every occurrence of a top-level setup name that the base-mode rename pass must
 * rewrite, with function-scope shadowing.
 *
 * Generic AST traversal primitives live in `../utils/ast-traversal`; this module builds the
 * identifier-aware layer on top. It is the intended home for future binding-flow work (e.g. locating
 * `watch(...)` calls to manage, or rewriting reactive-props destructuring).
 */

/**
 * @private
 */
export { type SetupRenameExpansion, type SetupRenameTarget, collectSetupRenameTargets } from './setup-references';
