const { atLevel, createTempFiles, reportOf, rule, scriptTester, todoScript, typeScriptTester } = require('./tester');

const HEADLINE = 'Module.register() no longer accepts the "snippets" property.';
const REMOVED = reportOf(HEADLINE);

function manual(...problems) {
    return reportOf([
        HEADLINE,
        ...problems,
    ].join('\n'));
}

const MODULE = 'src/Resources/app/administration/src/module/swag-example';

// The cases name their files when they are defined, so the files exist before the tests run.
describe('createApiUsageRule() module-snippets', () => {
    const files = createTempFiles({
        [`${MODULE}/snippet/de-DE.json`]: '{}',
        [`${MODULE}/snippet/en-GB.json`]: '{}',
        [`${MODULE}/snippet/en.json`]: '{}',
        [`${MODULE}/snippet/de_DE.json`]: '{}',
        [`${MODULE}/snippet/messages.json`]: '{}',
        [`${MODULE}/js-snippet/de-DE.js`]: 'export default {};',
        [`${MODULE}/js-snippet/de-DE.json`]: '{}',
        'src/Resources/app/administration/snippet/de-DE.json': '{}',
    });

    const moduleFile = files.path(`${MODULE}/index.js`);

    afterAll(() => {
        files.remove();
    });

    scriptTester.run('createApiUsageRule() module-snippets', rule, {
        valid: [
            {
                name: 'a module without snippets',
                filename: moduleFile,
                code: "Shopware.Module.register('swag-example', { routes: {} });",
            },
            {
                name: 'a snippets property of another call',
                filename: moduleFile,
                code: "Shopware.Component.register('swag-example', { snippets: {} });",
            },
        ],
        invalid: [
            {
                name: 'removes the property and the imports of snippet files the Administration loads',
                filename: moduleFile,
                code: [
                    "import deDE from './snippet/de-DE.json';",
                    "import enGB from './snippet/en-GB.json';",
                    "import './page/swag-example-list';",
                    '',
                    "Shopware.Module.register('swag-example', {",
                    "    type: 'plugin',",
                    '    snippets: {',
                    "        'de-DE': deDE,",
                    "        'en-GB': enGB,",
                    '    },',
                    '    routes: {},',
                    '});',
                ].join('\n'),
                output: [
                    "import './page/swag-example-list';",
                    '',
                    "Shopware.Module.register('swag-example', {",
                    "    type: 'plugin',",
                    '    routes: {},',
                    '});',
                ].join('\n'),
                errors: [{ message: REMOVED }],
            },
            {
                name: 'removes the last property with the comma before it and keeps an import used elsewhere',
                filename: moduleFile,
                code: [
                    "import en from './snippet/en.json';",
                    '',
                    'console.log(en);',
                    "Module.register('swag-example', { type: 'plugin', snippets: { en } });",
                ].join('\n'),
                output: [
                    "import en from './snippet/en.json';",
                    '',
                    'console.log(en);',
                    "Module.register('swag-example', { type: 'plugin' });",
                ].join('\n'),
                errors: [{ message: REMOVED }],
            },
            {
                name: 'removes an extensionless import that resolves to a snippet file',
                filename: moduleFile,
                code: "import deDE from './snippet/de-DE';\nShopware.Module.register('swag-example', {\n    snippets: { 'de-DE': deDE },\n});",
                output: "Shopware.Module.register('swag-example', {\n});",
                errors: [{ message: REMOVED }],
            },
            {
                name: 'leaves no blank line at the start of the file when the removed imports came first',
                filename: moduleFile,
                code: "import deDE from './snippet/de-DE.json';\nimport enGB from './snippet/en-GB.json';\n\nShopware.Module.register('swag-example', {\n    snippets: { 'de-DE': deDE, 'en-GB': enGB },\n});",
                output: "Shopware.Module.register('swag-example', {\n});",
                errors: [{ message: REMOVED }],
            },
            {
                name: 'removes dynamic imports and requires of snippet files',
                filename: moduleFile,
                code: "Shopware.Module.register('swag-example', {\n    snippets: { 'de-DE': () => import('./snippet/de-DE.json'), 'en-GB': require('./snippet/en-GB.json') },\n    routes: {},\n});",
                output: "Shopware.Module.register('swag-example', {\n    routes: {},\n});",
                errors: [{ message: REMOVED }],
            },
            {
                name: 'reports an extensionless import that resolves to a JavaScript file',
                filename: moduleFile,
                code: "import deDE from './js-snippet/de-DE';\nShopware.Module.register('swag-example', { snippets: { 'de-DE': deDE } });",
                output: null,
                errors: [
                    {
                        message: manual(
                            '"./js-snippet/de-DE" resolves to de-DE.js; the Administration only loads .json snippet files, so convert it to a <language>.json file',
                        ),
                    },
                ],
            },
            {
                name: 'reports a snippet file outside of the Administration source folder',
                filename: moduleFile,
                code: "import deDE from '../../../snippet/de-DE.json';\nShopware.Module.register('swag-example', { snippets: { 'de-DE': deDE } });",
                output: null,
                errors: [{ message: manual('move de-DE.json below Resources/app/administration/src') }],
            },
            {
                name: 'reports snippet files with other names',
                filename: moduleFile,
                code: "import deDE from './snippet/de_DE.json';\nimport messages from './snippet/messages.json';\nShopware.Module.register('swag-example', { snippets: { 'de-DE': deDE, 'en-GB': messages } });",
                output: null,
                errors: [
                    {
                        message: manual(
                            'rename de_DE.json to de-DE.json',
                            'rename messages.json to <language>.json or <language>-<COUNTRY>.json, e.g. de-DE.json',
                        ),
                    },
                ],
            },
            {
                name: 'reports missing files, package imports and inline snippets',
                filename: moduleFile,
                code: "import deDE from './snippet/fr-FR.json';\nimport enGB from '@swag/snippets/en-GB.json';\nShopware.Module.register('swag-example', { snippets: { 'de-DE': deDE, 'en-GB': enGB, nl: { a: 'b' } } });",
                output: null,
                errors: [
                    {
                        message: manual(
                            '"./snippet/fr-FR.json" is no snippet file of the extension; move its snippets into a <language>.json file below Resources/app/administration/src',
                            '"@swag/snippets/en-GB.json" is no snippet file of the extension; move its snippets into a <language>.json file below Resources/app/administration/src',
                            'move the snippets of nl into a nl.json file below Resources/app/administration/src',
                        ),
                    },
                ],
            },
            {
                name: 'reports snippets that are no object literal',
                filename: moduleFile,
                code: "Shopware.Module.register('swag-example', { snippets });",
                output: null,
                errors: [{ message: manual('put the snippet files below Resources/app/administration/src, named <language>.json or <language>-<COUNTRY>.json') }],
            },
            {
                name: 'writes what to do as review comment at the partial level',
                filename: moduleFile,
                settings: atLevel('partial'),
                code: "import deDE from './snippet/de_DE.json';\nShopware.Module.register('swag-example', {\n    snippets: { 'de-DE': deDE },\n});",
                output: `import deDE from './snippet/de_DE.json';\nShopware.Module.register('swag-example', {\n    ${todoScript(`${HEADLINE} Before you remove it: rename de_DE.json to de-DE.json.`)}\n    snippets: { 'de-DE': deDE },\n});`,
                errors: [{ message: manual('rename de_DE.json to de-DE.json') }],
            },
            {
                name: 'reports a module outside of the Administration source folder',
                filename: files.path('src/Resources/app/administration/main.js'),
                code: "import deDE from './snippet/de-DE.json';\nShopware.Module.register('swag-example', { snippets: { 'de-DE': deDE } });",
                output: null,
                errors: [{ message: manual('move de-DE.json below Resources/app/administration/src') }],
            },
        ],
    });

    typeScriptTester.run('createApiUsageRule() module-snippets (TypeScript)', rule, {
        valid: [],
        invalid: [
            {
                name: 'removes the property of options wrapped in as',
                filename: files.path(`${MODULE}/index.ts`),
                code: "import deDE from './snippet/de-DE.json';\nShopware.Module.register('swag-example', {\n    snippets: { 'de-DE': deDE },\n    routes: {},\n} as ModuleManifest);",
                output: "Shopware.Module.register('swag-example', {\n    routes: {},\n} as ModuleManifest);",
                errors: [{ message: REMOVED }],
            },
        ],
    });
});
