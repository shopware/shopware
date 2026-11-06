const RuleTester = require('eslint').RuleTester;

// A CLI `--rule` reaches every linted file, so the template rules must tolerate scripts without a template.
const TEMPLATE_RULES = [
    'no-deprecated-components',
    'no-deprecated-component-usage',
    'no-deprecated-template-events',
    'no-sw-tabs-usage',
];

const tester = new RuleTester({
    languageOptions: {
        ecmaVersion: 2022,
        sourceType: 'module',
    },
});

TEMPLATE_RULES.forEach((ruleName) => {
    tester.run(`${ruleName} on a script`, require(`./${ruleName}`), {
        valid: [
            {
                name: 'reports nothing in a file without a Vue template',
                filename: 'sw-foo.store.ts',
                code: `export default { template: '<sw-button />' };`,
            },
        ],
        invalid: [],
    });
});
