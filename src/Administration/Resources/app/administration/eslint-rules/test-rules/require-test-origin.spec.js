/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./require-test-origin');

const ruleTester = new RuleTester({
    languageOptions: {
        ecmaVersion: 'latest',
        sourceType: 'module',
    },
});

ruleTester.run('require-test-origin', rule, {
    valid: [
        {
            name: 'a requirement with its decision',
            filename: 'src/x/tests/requirements.spec.ts',
            code: `
/**
 * @requirement Rounds half up.
 */
it('rounds half up', () => {});`,
        },
        {
            name: 'a curried helper with a regression link',
            filename: 'src/x/tests/regressions.spec.ts',
            code: `
/**
 * @regression #21760 The list showed "Open" while the detail page showed "Shipped".
 * @deprecated tag:v6.8.0 - Removed with the 6.7 fallback.
 */
it.deprecated('v6.8.0.0')('shows the same delivery as the detail page', () => {});`,
        },
        {
            name: 'pinned tests need no comment',
            filename: 'src/x/tests/pinned.spec.ts',
            code: "it('returns null without deliveries', () => {});",
        },
        {
            name: 'a deprecated pinned test with its tag',
            filename: 'src/x/tests/pinned.spec.ts',
            code: `
/** @deprecated tag:v6.8.0 - Removed with the 6.7 fallback. */
it.deprecated('v6.8.0.0')('falls back to the first delivery', () => {});`,
        },
        {
            name: 'other specs are not checked',
            filename: 'src/x/x.spec.ts',
            code: "it('works', () => {});",
        },
    ],
    invalid: [
        {
            name: 'a deprecated pinned test without its tag',
            filename: 'src/x/tests/pinned.spec.ts',
            code: "it.deprecated('v6.8.0.0')('falls back to the first delivery', () => {});",
            errors: [{ messageId: 'missingDeprecation' }],
        },
        {
            name: 'a deprecated requirement without its tag',
            filename: 'src/x/tests/requirements.spec.ts',
            code: `
/**
 * @requirement Falls back to the most expensive delivery.
 */
it.deprecated('v6.8.0.0')('falls back to the most expensive delivery', () => {});`,
            errors: [{ messageId: 'missingDeprecation' }],
        },
        {
            name: 'a requirement without a comment',
            filename: 'src/x/tests/requirements.spec.ts',
            code: "it('rounds half up', () => {});",
            errors: [{ messageId: 'missingOrigin' }],
        },
        {
            name: 'a line comment does not count',
            filename: 'src/x/tests/requirements.spec.ts',
            code: `
// @requirement Rounds half up.
it('rounds half up', () => {});`,
            errors: [{ messageId: 'missingOrigin' }],
        },
        {
            name: 'a regression without an issue',
            filename: 'src/x/tests/regressions.spec.ts',
            code: `
/**
 * @regression The list showed the wrong state.
 */
it.activeFeatureFlags(['v6.8.0.0'])('shows the same delivery', () => {});`,
            errors: [{ messageId: 'missingOrigin' }],
        },
    ],
});
