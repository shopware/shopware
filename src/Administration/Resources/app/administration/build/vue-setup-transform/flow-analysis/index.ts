/**
 * @sw-package framework
 */

/**
 * Identifier-flow analysis for the Shopware setup transform.
 *
 * A small, encapsulated API over the Babel AST for the one question the transform keeps asking in
 * different shapes: which identifiers does a piece of code read, write, or declare, and how do nested
 * scopes shadow them. Two families:
 *
 * - Template-expression references (`references`): what a Vue expression reads / writes / a binding
 *   pattern declares, honoring template and JS scopes.
 * - Setup-script references (`setup-references`): every occurrence of a top-level setup name that the
 *   base-mode rename pass must rewrite, with function-scope shadowing.
 *
 * Generic AST traversal primitives live in `../utils/ast-traversal`; this module builds the
 * identifier-aware layer on top. It is the intended home for future binding-flow work (e.g. locating
 * `watch(...)` calls to manage, or rewriting reactive-props destructuring).
 */

/**
 * @private
 */
export {
    addPatternNames,
    collectExpressionReferences,
    collectExpressionWriteTargets,
    collectPatternReferences,
    parseBindingPattern,
} from './references';

/**
 * @private
 */
export { type SetupRenameExpansion, type SetupRenameTarget, collectSetupRenameTargets } from './setup-references';
