/**
 * @sw-package framework
 */

const ESLINT_DIRECTIVE = /^\s*eslint-(?:disable|enable)(?:-line|-next-line)?(?:\s|$)/;

/**
 * @private
 *
 * Whether a comment body, without its `//` or `/* *\/` delimiters, is an `eslint-disable` or `eslint-enable`
 * directive. The rule `private-feature-declarations` and the public API generator skip these comments when
 * they look for the declaration comment of a statement.
 */
export function isEslintDirective(body: string): boolean {
    return ESLINT_DIRECTIVE.test(body);
}
