const fs = require('fs');
const path = require('path');
const RuleTester = require('eslint').RuleTester;
const { withRegistryMessageContext } = require('./test-utils/with-registry-message-context');
const { describeWithSelectedDeprecationIds } = require('./test-utils/selected-deprecation-ids');
const rule = require('./no-deprecated-component-usage');
const { loadRegistry } = require('./registry/load-registry');

// One file per replacement component or rule concern, each exporting `{ valid, invalid }` RuleTester cases.
const FIXTURE_DIRECTORY = path.join(__dirname, 'fixtures/no-deprecated-component-usage');

const tester = new RuleTester({
    languageOptions: {
        parser: require('vue-eslint-parser'),
        ecmaVersion: 2015,
    },
});

describe('registry helper ownership', () => {
    it('keeps component usage ESLint behavior owned by registry helpers', () => {
        const registry = loadRegistry();
        const componentLevelKinds = new Set([
            'rename-component',
            'codemod-rule',
        ]);
        const usagesWithoutHelperEslint = registry.componentApiMigrations.flatMap((migration) => {
            return migration.usage
                .filter((usage) => !componentLevelKinds.has(usage.kind) && !usage.eslint?.report)
                .map((usage) => `${migration.id}:${usage.kind}`);
        });

        expect(usagesWithoutHelperEslint).toEqual([]);
    });
});

tester.run('no-deprecated-component-usage', rule, {
    valid: [
        {
            name: 'Empty file',
            filename: 'test.html.twig',
            code: '',
        },
    ],
    invalid: [],
});

fs.readdirSync(FIXTURE_DIRECTORY)
    .filter((file) => file.endsWith('.js'))
    .sort()
    .forEach((file) => {
        const { valid, invalid } = require(path.join(FIXTURE_DIRECTORY, file));

        tester.run(`no-deprecated-component-usage: ${path.basename(file, '.js')}`, rule, {
            valid,
            invalid: withRegistryMessageContext(invalid),
        });
    });

describeWithSelectedDeprecationIds('component.sw-alert', () => {
    tester.run('no-deprecated-component-usage with an unrelated deprecation id selected', rule, {
        valid: [
            {
                name: '"mt-text-field" value prop is not reported when only another migration is selected',
                filename: 'test.html.twig',
                code: '<template><mt-text-field value="old" /></template>',
            },
        ],
        invalid: [],
    });
});
