/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-module-snippets');

const tester = new RuleTester({
    languageOptions: { ecmaVersion: 2022, sourceType: 'module' },
});

const MODULE_FILE = '/plugin/src/Resources/app/administration/src/module/acme-module/index.js';
const HINT = 'Module.register() no longer accepts snippets in 6.8. Remove the property and put the snippet files ' +
    'below Resources/app/administration/src, named like en.json or de-DE.json, so the Administration loads them on its own.';

tester.run('no-module-snippets', rule, {
    valid: [
        {
            name: 'ignores modules without snippets',
            filename: MODULE_FILE,
            code: 'Shopware.Module.register("acme-module", { title: "acme.title" });',
        },
        {
            name: 'ignores snippets properties of other objects',
            filename: MODULE_FILE,
            code: 'Shopware.Component.register("acme-field", { data() { return { snippets: {} }; } });',
        },
    ],
    invalid: [
        {
            name: 'removes the property and the snippet imports only it used',
            filename: MODULE_FILE,
            code: [
                'import deDE from \'./snippet/de-DE.json\';',
                'import enGB from \'./snippet/en-GB\';',
                'import \'./page/acme-list\';',
                '',
                'Shopware.Module.register(\'acme-module\', {',
                '    title: \'acme.title\',',
                '    snippets: {',
                '        \'de-DE\': deDE,',
                '        \'en-GB\': enGB,',
                '    },',
                '    routes: {},',
                '});',
            ].join('\n'),
            output: [
                'import \'./page/acme-list\';',
                '',
                'Shopware.Module.register(\'acme-module\', {',
                '    title: \'acme.title\',',
                '    routes: {},',
                '});',
            ].join('\n'),
            errors: [{ messageId: 'remove' }],
        },
        {
            name: 'removes lazy imports and keeps an import used elsewhere',
            filename: MODULE_FILE,
            code: [
                'import en from \'./snippet/en.json\';',
                'const { Module } = Shopware;',
                'Module.register(\'acme-module\', { routes: {}, snippets: { \'en-GB\': en, \'de-DE\': () => import(\'./snippet/de.json\') } });',
                'export { en };',
            ].join('\n'),
            output: [
                'import en from \'./snippet/en.json\';',
                'const { Module } = Shopware;',
                'Module.register(\'acme-module\', { routes: {} });',
                'export { en };',
            ].join('\n'),
            errors: [{ messageId: 'remove' }],
        },
        {
            name: 'comments snippet files the Administration does not load on its own',
            filename: MODULE_FILE,
            code: [
                'import deDE from \'./snippet/de_DE.json\';',
                'Shopware.Module.register(\'acme-module\', {',
                '    snippets: { \'de-DE\': deDE },',
                '});',
            ].join('\n'),
            output: [
                'import deDE from \'./snippet/de_DE.json\';',
                'Shopware.Module.register(\'acme-module\', {',
                `    // TODO Codemod: ${HINT}`,
                '    snippets: { \'de-DE\': deDE },',
                '});',
            ].join('\n'),
            errors: [{ messageId: 'todo' }],
        },
        {
            name: 'comments snippet files outside of the Administration source and inline snippets',
            filename: MODULE_FILE,
            code: [
                'import deDE from \'../../../../../snippet/de-DE.json\';',
                'Shopware.Module.register(\'acme-module\', { snippets: { \'de-DE\': deDE } });',
                'Shopware.Module.register(\'acme-other\', { snippets: { \'en-GB\': { acme: { title: \'Acme\' } } } });',
            ].join('\n'),
            output: [
                'import deDE from \'../../../../../snippet/de-DE.json\';',
                `// TODO Codemod: ${HINT}`,
                'Shopware.Module.register(\'acme-module\', { snippets: { \'de-DE\': deDE } });',
                `// TODO Codemod: ${HINT}`,
                'Shopware.Module.register(\'acme-other\', { snippets: { \'en-GB\': { acme: { title: \'Acme\' } } } });',
            ].join('\n'),
            errors: [{ messageId: 'todo' }, { messageId: 'todo' }],
        },
    ],
});
