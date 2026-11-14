/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const { reportTodo, todoTag, wrapComment } = require('./todo-comment');

const scriptRule = {
    meta: { type: 'problem', fixable: 'code', schema: [] },
    create(context) {
        const seen = new Set();

        return {
            Identifier(node) {
                if (node.name === 'oldApi') {
                    reportTodo(context, { id: 'test', message: 'oldApi is removed. Use newApi.', range: node.range, scope: 'script', seen });
                }
            },
        };
    },
};

const templateRule = {
    meta: { type: 'problem', fixable: 'code', schema: [] },
    create(context) {
        return context.sourceCode.parserServices.defineTemplateBodyVisitor({
            VElement(node) {
                if (node.name === 'sw-old') {
                    reportTodo(context, { id: 'test', message: 'sw-old is removed.', range: node.range, scope: 'template' });
                }
            },
        });
    },
};

const MESSAGE = 'oldApi is removed. Use newApi.';
const TODO = '// TODO Codemod(6.8, test): oldApi is removed. Use newApi.';

new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } }).run('todo-comment (script)', scriptRule, {
    valid: [{ name: 'ignores other code', code: 'newApi();' }],
    invalid: [
        {
            name: 'writes the TODO above the finding with its indentation',
            code: 'function run() {\n    oldApi();\n}',
            output: `function run() {\n    ${TODO}\n    oldApi();\n}`,
            errors: [{ message: MESSAGE }],
        },
        {
            name: 'reports without a fix when the TODO is already there',
            code: `function run() {\n    ${TODO}\n    oldApi();\n}`,
            output: null,
            errors: [{ message: MESSAGE }],
        },
        {
            name: 'finds the TODO in a block of comments above',
            code: `${TODO}\n// TODO Codemod(6.8, other): something else.\noldApi();`,
            output: null,
            errors: [{ message: MESSAGE }],
        },
        {
            name: 'writes one TODO for several findings with the same text on one line',
            code: 'oldApi(oldApi);',
            output: `${TODO}\noldApi(oldApi);`,
            errors: [{ message: MESSAGE }],
        },
        {
            name: 'does not split a multi-line template literal',
            code: 'const text = `first\n${oldApi}`;',
            output: null,
            errors: [{ message: MESSAGE }],
        },
        {
            name: 'does not split a multi-line block comment',
            code: '/* start\n */ oldApi();',
            output: null,
            errors: [{ message: MESSAGE }],
        },
    ],
});

const vueTester = new RuleTester({ languageOptions: { parser: require('vue-eslint-parser'), ecmaVersion: 2022 } });

vueTester.run('todo-comment (template)', templateRule, {
    valid: [{ name: 'ignores other elements', filename: 'test.html.twig', code: '<template><sw-new /></template>' }],
    invalid: [
        {
            name: 'writes a Twig comment in .html.twig files',
            filename: 'test.html.twig',
            code: '<template>\n{% block test %}\n    <sw-old />\n{% endblock %}\n</template>',
            output: '<template>\n{% block test %}\n    {# TODO Codemod(6.8, test): sw-old is removed. #}\n    <sw-old />\n{% endblock %}\n</template>',
            errors: [{ message: 'sw-old is removed.' }],
        },
        {
            name: 'writes an HTML comment in .vue files',
            filename: 'test.vue',
            code: '<template>\n    <sw-old />\n</template>',
            output: '<template>\n    <!-- TODO Codemod(6.8, test): sw-old is removed. -->\n    <sw-old />\n</template>',
            errors: [{ message: 'sw-old is removed.' }],
        },
        {
            name: 'reports without a fix when the Twig TODO is already there',
            filename: 'test.html.twig',
            code: '<template>\n    {# TODO Codemod(6.8, test): sw-old is removed. #}\n    <sw-old />\n</template>',
            output: null,
            errors: [{ message: 'sw-old is removed.' }],
        },
    ],
});

describe('todo-comment helpers', () => {
    it('builds the tag from id and version', () => {
        expect(todoTag('vuex-state')).toBe('TODO Codemod(6.8, vuex-state)');
        expect(todoTag('vuex-state', '6.9')).toBe('TODO Codemod(6.9, vuex-state)');
    });

    it('keeps comment terminators out of the comment text', () => {
        expect(wrapComment('vue', 'use --with')).toBe('<!-- use - -with -->');
        expect(wrapComment('twig', 'a #} b')).toBe('{# a # } b #}');
        expect(wrapComment('script', 'a\nb')).toBe('// a b');
    });
});
