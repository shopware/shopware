const { RuleTester } = require('eslint');
const rule = require('./stabilize-feature-flag');

const ruleTester = new RuleTester({
    languageOptions: {
        ecmaVersion: 2022,
        sourceType: 'module',
    },
});

// Handcrafted list of stabilized flag names, mirroring the ESLint config shape.
const FLAGS = [
    'v6.7.0.0',
    'STABLE_FEATURE',
];

const options = [{ stabilizedFlags: FLAGS }];

ruleTester.run('stabilize-feature-flag', rule, {
    valid: [
        {
            name: 'keeps a flag that is not in the stabilized list',
            code: "it.activeFeatureFlags(['v6.8.0.0'])('runs with a feature flag', () => {});",
            options,
        },
        {
            name: 'keeps an experimental flag that is not in the stabilized list',
            code: "it.activeFeatureFlags(['EXPERIMENTAL_FEATURE'])('runs with a feature flag', () => {});",
            options,
        },
        {
            name: 'leaves other test functions unchanged',
            code: "test.activeFeatureFlags(['STABLE_FEATURE'])('runs with a feature flag', () => {});",
            options,
        },
    ],
    invalid: [
        {
            name: 'removes a stabilized flag and keeps the remaining ones',
            code: `it.activeFeatureFlags(['STABLE_FEATURE', 'EXPERIMENTAL_FEATURE'])(
    'runs with feature flags',
    () => {},
);`,
            output: `it.activeFeatureFlags(['EXPERIMENTAL_FEATURE'])(
    'runs with feature flags',
    () => {},
);`,
            options,
            errors: [{ messageId: 'stabilizedFeatureFlag' }],
        },
        {
            name: 'turns the helper into a regular test when only stabilized flags remain',
            code: "it.activeFeatureFlags(['STABLE_FEATURE'])('runs with a feature flag', () => {});",
            output: "it('runs with a feature flag', () => {});",
            options,
            errors: [{ messageId: 'stabilizedFeatureFlag' }],
        },
        {
            name: 'normalizes flag notation before matching the config',
            code: "it.activeFeatureFlags(['V6_7_0_0'])('runs with a feature flag', () => {});",
            output: "it('runs with a feature flag', () => {});",
            options,
            errors: [{ messageId: 'stabilizedFeatureFlag' }],
        },
        {
            name: 'turns a table-driven helper into a regular it.each',
            code: "it.activeFeatureFlags(['STABLE_FEATURE']).each(rows)('runs with %s', () => {});",
            output: "it.each(rows)('runs with %s', () => {});",
            options,
            errors: [{ messageId: 'stabilizedFeatureFlag' }],
        },
        {
            name: 'requires an inline array literal',
            code: "it.activeFeatureFlags(activeFeatureFlags)('runs with feature flags', () => {});",
            output: null,
            options,
            errors: [{ messageId: 'arrayLiteralRequired' }],
        },
        {
            name: 'requires the array to contain only string literals',
            code: "it.activeFeatureFlags([flag])('runs with feature flags', () => {});",
            output: null,
            options,
            errors: [{ messageId: 'arrayLiteralRequired' }],
        },
    ],
});
