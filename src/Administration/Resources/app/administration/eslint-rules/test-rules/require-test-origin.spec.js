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
            name: 'a public-api test that names its member',
            filename: 'src/x/tests/public-api.spec.ts',
            code: `
/** @public-api usedRules */
it('lists the used rules', () => {});`,
        },
        {
            name: 'a private-api test that names its member',
            filename: 'src/x/src/y/tests/private-api.spec.ts',
            code: `
/** @private-api readableVersion */
it('shows the version without its build', () => {});`,
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
            name: 'a private-api test without its member',
            filename: 'src/x/src/y/tests/private-api.spec.ts',
            code: "it('shows the version without its build', () => {});",
            errors: [{ messageId: 'missingOrigin' }],
        },
        {
            name: 'a public-api test without its member',
            filename: 'src/x/tests/public-api.spec.ts',
            code: "it('lists the used rules', () => {});",
            errors: [{ messageId: 'missingOrigin' }],
        },
        {
            name: 'a deprecated public-api test without its tag',
            filename: 'src/x/tests/public-api.spec.ts',
            code: `
/** @public-api usedRules */
it.deprecated('v6.8.0.0')('lists the used rules', () => {});`,
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
            name: 'a requirement without a comment in a split requirements spec',
            filename: 'src/x/tests/requirements.spec/rounding.spec.ts',
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
