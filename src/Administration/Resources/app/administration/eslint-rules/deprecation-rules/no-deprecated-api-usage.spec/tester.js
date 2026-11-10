/**
 * @sw-package framework
 *
 * Shared setup of the no-deprecated-api-usage specs: the rule against the synthetic registry of
 * `mock-registry.js`, RuleTesters for scripts, TypeScript and `.vue` files, the real twig-vue
 * processor for `.html.twig` files, and temporary extension files on disk.
 */

jest.mock('../registry/load-registry', () => ({
    loadRegistry: () => require('./mock-registry'),
}));

const fs = require('fs');
const os = require('os');
const path = require('path');
const { Linter, RuleTester } = require('eslint');
const vueParser = require('vue-eslint-parser');
const tsParser = require('@typescript-eslint/parser');
const twigVue = require('eslint-plugin-twig-vue');
const rule = require('../no-deprecated-api-usage');

const RULE_ID = 'sw-deprecation-rules/no-deprecated-api-usage';

const scriptTester = new RuleTester({
    languageOptions: { ecmaVersion: 2022, sourceType: 'module' },
});

const typeScriptTester = new RuleTester({
    languageOptions: { parser: tsParser, ecmaVersion: 2022, sourceType: 'module' },
});

const vueTester = new RuleTester({
    languageOptions: {
        parser: vueParser,
        parserOptions: { parser: tsParser },
        ecmaVersion: 2022,
        sourceType: 'module',
    },
});

function escapeRegExp(text) {
    return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/**
 * The full message of a report with the headline, followed by the registry context of a mock migration.
 *
 * @example
 * errors: [{ message: reportOf('Shopware.State is removed.') }]
 */
function reportOf(headline) {
    return new RegExp(`^${escapeRegExp(headline)}\\nDescription of test\\.[\\w-]+\\.\\nRemoved in Shopware 6\\.8\\.0\\.$`);
}

/**
 * The review comment the run level writes above a script spot.
 */
function todoScript(text) {
    return `/* TODO Codemod: ${text} */`;
}

/**
 * The review comment the run level writes above a template spot.
 */
function todoTemplate(text) {
    return `<!-- TODO Codemod: ${text} -->`;
}

/**
 * The ESLint settings of a run level.
 */
function atLevel(level) {
    return { 'sw-deprecation-rules': { level } };
}

/**
 * Writes files below a new temporary folder.
 *
 * @example
 * const files = createTempFiles({ 'src/sw-x/index.js': '…' });
 * files.path('src/sw-x/index.js');
 * files.remove();
 */
function createTempFiles(files) {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'sw-api-usage-'));

    Object.entries(files).forEach(([relativePath, content]) => {
        const filename = path.join(root, relativePath);

        fs.mkdirSync(path.dirname(filename), { recursive: true });
        fs.writeFileSync(filename, content);
    });

    return {
        root,
        path: (relativePath) => path.join(root, relativePath),
        remove: () => fs.rmSync(root, { recursive: true, force: true }),
    };
}

/**
 * Lints `.html.twig` code the way `admin:upgrade-extensions` does: the twig-vue processor wraps it in
 * a `<template>` and the Vue parser parses it, see `extension-tooling/codemods.mjs`.
 */
function twigConfig(level) {
    return [
        {
            files: ['**/*.html.twig'],
            languageOptions: { parser: vueParser },
            plugins: {
                'sw-deprecation-rules': { rules: { 'no-deprecated-api-usage': rule } },
                twigVue,
            },
            processor: 'twigVue/twig-vue',
            settings: atLevel(level),
            rules: { [RULE_ID]: 'error' },
        },
    ];
}

const twigLinter = new Linter({ configType: 'flat', cwd: path.parse(os.tmpdir()).root });

function twigOptions(filename) {
    // Linter keeps only `.js` blocks of a processor by default; the ESLint CLI keeps every block it has a config for.
    return { filename, filterCodeBlock: () => true };
}

/**
 * The reports of the rule for a `.html.twig` file, with their messages and lines in the original file.
 */
function lintTwig(code, filename, level = 'partial') {
    return twigLinter.verify(code, twigConfig(level), twigOptions(filename));
}

/**
 * The `.html.twig` code after an `eslint --fix` run.
 */
function fixTwig(code, filename, level = 'partial') {
    return twigLinter.verifyAndFix(code, twigConfig(level), twigOptions(filename)).output;
}

module.exports = {
    atLevel,
    createTempFiles,
    fixTwig,
    lintTwig,
    reportOf,
    rule,
    scriptTester,
    todoScript,
    todoTemplate,
    typeScriptTester,
    vueTester,
};
