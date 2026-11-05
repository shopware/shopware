/**
 * @sw-package framework
 */

const { Linter } = require('eslint');
const cases = require('../fixtures/sw-empty-state-migration');
const { describeWithSelectedDeprecationIds } = require('../test-utils/selected-deprecation-ids');

const plugin = {
    rules: {
        'no-deprecated-components': require('../no-deprecated-components'),
        'no-deprecated-component-usage': require('../no-deprecated-component-usage'),
        'no-sw-empty-state-usage': require('../no-sw-empty-state-usage'),
    },
};

/**
 * Runs the component conversion and its follow-up steps until no fix is left, like
 * `deprecation:migrate --fix` does, and returns the fixed code or null when nothing changed.
 */
function migrate(code, filename, level) {
    const config = {
        files: ['**/*.twig', '**/*.vue'],
        plugins: { 'sw-deprecation-rules': plugin },
        settings: { 'sw-deprecation-rules': { level } },
        languageOptions: { parser: require('vue-eslint-parser'), ecmaVersion: 2021 },
        rules: {
            'sw-deprecation-rules/no-deprecated-components': 'error',
            'sw-deprecation-rules/no-deprecated-component-usage': 'error',
            'sw-deprecation-rules/no-sw-empty-state-usage': 'error',
        },
    };
    const { output } = new Linter().verifyAndFix(code, config, { filename });

    return output === code ? null : output;
}

describe('sw-empty-state migration', () => {
    describe.each(['partial', 'safe'])('at the %s level', (level) => {
        it.each(cases.map((testCase) => [testCase.name, testCase]))('%s', (name, testCase) => {
            const output = migrate(testCase.code, testCase.filename, level);

            expect(output).toBe(testCase[level]);
            expect(output && migrate(output, testCase.filename, level)).toBeNull();
        });
    });
});

describeWithSelectedDeprecationIds('component.sw-alert', () => {
    it('leaves sw-empty-state alone when only another migration is selected', () => {
        expect(migrate('<template><sw-empty-state title="Title" /></template>', 'test.html.twig', 'partial')).toBeNull();
    });
});
