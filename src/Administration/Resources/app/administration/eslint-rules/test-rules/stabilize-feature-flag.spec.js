const { RuleTester } = require('eslint');
const rule = require('./stabilize-feature-flag');

const ruleTester = new RuleTester({
    languageOptions: {
        ecmaVersion: 2022,
        sourceType: 'module',
    },
});

ruleTester.run('stabilize-feature-flag', rule, {
    valid: [
        {
            name: 'leaves other active feature flags unchanged',
            code: "it.activeFeatureFlags(['EXPERIMENTAL_FEATURE'])('runs with a feature flag', () => {});",
            options: ['STABLE_FEATURE'],
        },
        {
            name: 'leaves other test functions unchanged',
            code: "test.activeFeatureFlags(['STABLE_FEATURE'])('runs with a feature flag', () => {});",
            options: ['STABLE_FEATURE'],
        },
        {
            name: 'leaves dynamic feature flag lists unchanged',
            code: "it.activeFeatureFlags(activeFeatureFlags)('runs with feature flags', () => {});",
            options: ['STABLE_FEATURE'],
        },
    ],
    invalid: [
        {
            name: 'removes the stabilized feature flag from tests with other active flags',
            code: `it.activeFeatureFlags(['STABLE_FEATURE', 'EXPERIMENTAL_FEATURE'])(
    'runs with feature flags',
    () => {},
);`,
            output: `it.activeFeatureFlags(['EXPERIMENTAL_FEATURE'])(
    'runs with feature flags',
    () => {},
);`,
            options: ['STABLE_FEATURE'],
            errors: [{ messageId: 'stabilizedFeatureFlag' }],
        },
        {
            name: 'turns the helper into a regular test when no active flags remain',
            code: "it.activeFeatureFlags(['STABLE_FEATURE'])('runs with a feature flag', () => {});",
            output: "it('runs with a feature flag', () => {});",
            options: ['STABLE_FEATURE'],
            errors: [{ messageId: 'stabilizedFeatureFlag' }],
        },
        {
            name: 'turns a table-driven helper into a regular it.each when no active flags remain',
            code: "it.activeFeatureFlags(['STABLE_FEATURE']).each(rows)('runs with %s', () => {});",
            output: "it.each(rows)('runs with %s', () => {});",
            options: ['STABLE_FEATURE'],
            errors: [{ messageId: 'stabilizedFeatureFlag' }],
        },
        {
            name: 'keeps the remaining flags on a table-driven helper',
            code: "it.activeFeatureFlags(['STABLE_FEATURE', 'EXPERIMENTAL_FEATURE']).each(rows)('runs with %s', () => {});",
            output: "it.activeFeatureFlags(['EXPERIMENTAL_FEATURE']).each(rows)('runs with %s', () => {});",
            options: ['STABLE_FEATURE'],
            errors: [{ messageId: 'stabilizedFeatureFlag' }],
        },
        {
            name: 'removes every duplicate occurrence of the stabilized feature flag',
            code: "it.activeFeatureFlags(['STABLE_FEATURE', 'STABLE_FEATURE'])('runs with a feature flag', () => {});",
            output: "it('runs with a feature flag', () => {});",
            options: ['STABLE_FEATURE'],
            errors: [{ messageId: 'stabilizedFeatureFlag' }],
        },
        {
            name: 'updates every matching test and leaves unrelated calls unchanged',
            code: `it.activeFeatureFlags(['STABLE_FEATURE'])('first test', () => {});
it.activeFeatureFlags(['OTHER_FEATURE'])('second test', () => {});
test.activeFeatureFlags(['STABLE_FEATURE'])('third test', () => {});`,
            output: `it('first test', () => {});
it.activeFeatureFlags(['OTHER_FEATURE'])('second test', () => {});
test.activeFeatureFlags(['STABLE_FEATURE'])('third test', () => {});`,
            options: ['STABLE_FEATURE'],
            errors: [{ messageId: 'stabilizedFeatureFlag' }],
        },
        {
            name: 'preserves comments for remaining feature flags',
            code: `it.activeFeatureFlags([
    'STABLE_FEATURE',
    // This flag is still experimental.
    'EXPERIMENTAL_FEATURE',
])('runs with feature flags', () => {});`,
            output: `it.activeFeatureFlags([
    // This flag is still experimental.
    'EXPERIMENTAL_FEATURE',
])('runs with feature flags', () => {});`,
            options: ['STABLE_FEATURE'],
            errors: [{ messageId: 'stabilizedFeatureFlag' }],
        },
    ],
});
