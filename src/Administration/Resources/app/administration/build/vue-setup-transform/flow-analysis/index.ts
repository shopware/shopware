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
 * - Setup-script references (`setup-references`): whether a hoisted node references a binding that
 *   stays inside the setup callback (value or type position), with function-scope shadowing.
 *
 * Generic AST traversal primitives live in `../utils/ast-traversal`; this module builds the
 * identifier-aware layer on top. It is the intended home for future binding-flow work (e.g. locating
 * `watch(...)` calls to manage, or rewriting reactive-props destructuring).
 */

export {
    addPatternNames,
    collectExpressionReferences,
    collectExpressionWriteTargets,
    collectPatternReferences,
    parseBindingPattern,
} from './references';

export { findLocalSetupReference, findLocalSetupTypeReference } from './setup-references';
