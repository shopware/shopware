/**
 * @sw-package framework
 */

const fs = require('fs');
const os = require('os');
const path = require('path');
const { RuleTester } = require('eslint');
const rule = require('./no-module-snippets-property');

const REMOVED = 'Shopware.Module.register() no longer accepts snippets in 6.8 and throws with the v6.8.0.0 feature flag. '
    + 'The server loads these snippet files already, so the property is removed.';
const TODO = 'Shopware.Module.register() no longer accepts snippets in 6.8 and throws with the v6.8.0.0 feature flag. '
    + 'Move the snippets to JSON files below the administration src folder, named after the language (de.json) '
    + 'or the locale (de-DE.json), then remove this property.';
const todo = `// TODO Codemod(6.8, module-snippets): ${TODO}`;

const lines = (...rows) => rows.join('\n');

// The rule checks the imported snippet files on disk.
const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'sw-module-snippets-'));
const administration = path.join(directory, 'AcmePlugin/src/Resources/app/administration');
const writeFile = (file, content = '{}') => {
    fs.mkdirSync(path.dirname(path.join(administration, file)), { recursive: true });
    fs.writeFileSync(path.join(administration, file), content);
};

['de-DE.json', 'en-GB.json', 'de.json', 'en.json', 'de_DE.json', 'messages.js'].forEach((file) => writeFile(`src/module/acme-example/snippet/${file}`));
writeFile('src/module/acme-example/snippet/fr-FR.js', 'export default {};');
writeFile('src/module/acme-example/snippet/fr-FR.json');
writeFile('snippet/de-DE.json');
const moduleFile = path.join(administration, 'src/module/acme-example/index.js');

const ruleTester = new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } });

describe('no-module-snippets-property', () => {
    afterAll(() => {
        fs.rmSync(directory, { recursive: true, force: true });
    });

    ruleTester.run('no-module-snippets-property', rule, {
        valid: [
            {
                name: 'ignores modules without snippets',
                filename: moduleFile,
                code: "Shopware.Module.register('acme-example', { type: 'plugin', title: 'acme.title' });",
            },
            {
                name: 'ignores other register calls',
                filename: moduleFile,
                code: "Shopware.Component.register('acme-example', { snippets: { 'de-DE': {} } });",
            },
        ],
        invalid: [
            {
                name: 'removes the property and its imports when the server loads the files',
                filename: moduleFile,
                code: lines(
                    "import deDE from './snippet/de-DE.json';",
                    "import enGB from './snippet/en-GB.json';",
                    '',
                    "Shopware.Module.register('acme-example', {",
                    "    type: 'plugin',",
                    '    snippets: {',
                    "        'de-DE': deDE,",
                    "        'en-GB': enGB,",
                    '    },',
                    '});',
                ),
                output: lines(
                    "Shopware.Module.register('acme-example', {",
                    "    type: 'plugin',",
                    '});',
                ),
                errors: [{ message: REMOVED }],
            },
            {
                name: 'takes one blank line along when the property stands between blank lines',
                filename: moduleFile,
                code: lines(
                    "import deDE from './snippet/de-DE.json';",
                    '',
                    "Shopware.Module.register('acme-example', {",
                    "    type: 'plugin',",
                    '',
                    '    snippets: {',
                    "        'de-DE': deDE,",
                    '    },',
                    '',
                    "    routes: {},",
                    '',
                    '    snippetsAfter: true,',
                    '});',
                ),
                output: lines(
                    "Shopware.Module.register('acme-example', {",
                    "    type: 'plugin',",
                    '',
                    "    routes: {},",
                    '',
                    '    snippetsAfter: true,',
                    '});',
                ),
                errors: [{ message: REMOVED }],
            },
            {
                name: 'does not leave a blank line before the end of the object',
                filename: moduleFile,
                code: lines(
                    "import deDE from './snippet/de-DE.json';",
                    '',
                    "Shopware.Module.register('acme-example', {",
                    "    type: 'plugin',",
                    '',
                    '    snippets: {',
                    "        'de-DE': deDE,",
                    '    },',
                    '});',
                ),
                output: lines(
                    "Shopware.Module.register('acme-example', {",
                    "    type: 'plugin',",
                    '});',
                ),
                errors: [{ message: REMOVED }],
            },
            {
                name: 'accepts language files, imports without extension and a destructured Module',
                filename: moduleFile,
                code: lines(
                    "import './page/acme-example-list';",
                    "import de from './snippet/de';",
                    "import en from './snippet/en.json';",
                    '',
                    'const { Module } = Shopware;',
                    '',
                    "Module.register('acme-example', {",
                    '    snippets: {',
                    "        'de-DE': de,",
                    "        'en-GB': en,",
                    '    },',
                    "    title: en.acme.title,",
                    '});',
                ),
                output: lines(
                    "import './page/acme-example-list';",
                    "import en from './snippet/en.json';",
                    '',
                    'const { Module } = Shopware;',
                    '',
                    "Module.register('acme-example', {",
                    "    title: en.acme.title,",
                    '});',
                ),
                errors: [{ message: REMOVED }],
            },
            {
                name: 'removes a property that shares its line with others',
                filename: moduleFile,
                code: lines(
                    "import deDE from './snippet/de-DE.json';",
                    "Shopware.Module.register('acme-example', { type: 'plugin', snippets: { 'de-DE': deDE } });",
                ),
                output: "Shopware.Module.register('acme-example', { type: 'plugin' });",
                errors: [{ message: REMOVED }],
            },
            {
                name: 'removes empty snippets',
                filename: moduleFile,
                code: lines(
                    "Shopware.Module.register('acme-example', {",
                    '    snippets: {},',
                    "    type: 'plugin',",
                    '});',
                ),
                output: lines(
                    "Shopware.Module.register('acme-example', {",
                    "    type: 'plugin',",
                    '});',
                ),
                errors: [{ message: REMOVED }],
            },
            ...[
                ['an inline object', "{ 'de-DE': { acme: { title: 'Beispiel' } } }", ''],
                ['a JS module', "{ 'de-DE': messages }", "import messages from './snippet/messages.js';"],
                ['an import that Vite resolves to a JS module', "{ 'fr-FR': frFR }", "import frFR from './snippet/fr-FR';"],
                ['a file the server does not find by name', "{ 'de-DE': deDE }", "import deDE from './snippet/de_DE.json';"],
                ['a file named after another locale', "{ 'de-DE': enGB }", "import enGB from './snippet/en-GB.json';"],
                ['a file outside of src', "{ 'de-DE': deDE }", "import deDE from '../../../snippet/de-DE.json';"],
                ['a variable', 'snippets', "import snippets from './snippet';"],
            ].map(([name, value, importLine]) => ({
                name: `marks ${name}`,
                filename: moduleFile,
                code: lines(
                    importLine,
                    "Shopware.Module.register('acme-example', {",
                    `    snippets: ${value},`,
                    '});',
                ),
                output: lines(
                    importLine,
                    "Shopware.Module.register('acme-example', {",
                    `    ${todo}`,
                    `    snippets: ${value},`,
                    '});',
                ),
                errors: [{ message: TODO }],
            })),
        ],
    });
});
