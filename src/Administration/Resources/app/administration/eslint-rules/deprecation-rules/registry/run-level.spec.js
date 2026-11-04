/**
 * @sw-package framework
 */

const { Linter, RuleTester } = require('eslint');
const { withRunLevel } = require('./run-level');

/**
 * A rule that reports every <old-*> element with the fix level its name asks for:
 * <old-auto> renames it, <old-review> renames it as `unsafe-auto`, <old-manual> has no fix.
 */
const testRule = withRunLevel({
    meta: { fixable: 'code', schema: [{ enum: ['disableFix'] }], messages: { found: 'Found {{ name }}.' } },
    create(context) {
        return context.sourceCode.parserServices.defineTemplateBodyVisitor({
            VElement(node) {
                const kind = node.name.match(/^old-(auto|review|manual)$/)?.[1];

                if (!kind) {
                    return;
                }

                const nameRange = [node.range[0] + 1, node.range[0] + 1 + node.name.length];

                context.report({
                    node,
                    messageId: 'found',
                    data: { name: node.name },
                    fixLevel: { auto: 'auto', review: 'unsafe-auto', manual: 'manual' }[kind],
                    fix:
                        kind === 'manual' || context.options.includes('disableFix')
                            ? null
                            : (fixer) => fixer.replaceTextRange(nameRange, 'new'),
                });
            },
        });
    },
});

const tester = new RuleTester({
    languageOptions: { parser: require('vue-eslint-parser'), ecmaVersion: 2015 },
});

function atLevel(level) {
    return { 'sw-deprecation-rules': { level } };
}

function testCase(level, code, output) {
    return {
        name: `${level ?? 'no level'}: ${code.replace(/\n/g, ' ')}`,
        filename: 'test.html.twig',
        ...(level ? { settings: atLevel(level) } : {}),
        code,
        output,
        errors: [{ message: /^Found old-/ }],
    };
}

function template(...lines) {
    return ['<template>', ...lines.map((line) => `    ${line}`), '</template>'].join('\n');
}

tester.run('withRunLevel', testRule, {
    valid: [],
    invalid: [
        testCase('safe', template('<old-auto />'), template('<new />')),
        testCase('safe', template('<old-review />'), null),
        testCase('review', template('<old-review />'), template('<!-- TODO Codemod: Found old-review. -->', '<new />')),
        testCase('review', template('<old-manual />'), null),
        testCase(null, template('<old-manual />'), null),
        testCase('partial', template('<old-manual />'), template('<!-- TODO Codemod: Found old-manual. -->', '<old-manual />')),
        testCase('partial', template('<!-- TODO Codemod: Found old-manual. -->', '<old-manual />'), null),
        testCase('manual', template('<old-auto />'), template('<!-- TODO Codemod: Found old-auto. -->', '<old-auto />')),
        {
            ...testCase('manual', template('<old-auto />'), null),
            name: 'manual: adds no review comment when fixes are disabled',
            options: ['disableFix'],
        },
        {
            ...testCase('review', template('<div>', '    <old-review />', '</div>'), null),
            name: 'review: the review comment goes above the reported element, not above its parent',
            output: template('<div>', '    <!-- TODO Codemod: Found old-review. -->', '    <new />', '</div>'),
        },
        {
            ...testCase('partial', template('<p><old-manual /> text</p>'), null),
            name: 'partial: the review comment goes above the first element that starts its line',
            output: template('<!-- TODO Codemod: Found old-manual. -->', '<p><old-manual /> text</p>'),
        },
        {
            ...testCase('partial', '<template>\n\t<old-manual />\n</template>', null),
            name: 'partial: the review comment keeps tab indentation',
            output: '<template>\n\t<!-- TODO Codemod: Found old-manual. -->\n\t<old-manual />\n</template>',
        },
    ],
});

const scriptRule = withRunLevel({
    meta: { fixable: 'code', schema: [] },
    create(context) {
        return {
            CallExpression(node) {
                if (node.callee.name === 'oldCall') {
                    context.report({ node, message: 'Found oldCall.', fix: null });
                }
            },
        };
    },
});

new RuleTester({ languageOptions: { ecmaVersion: 2020, sourceType: 'module' } }).run('withRunLevel (script)', scriptRule, {
    valid: [],
    invalid: [
        {
            name: 'partial: a script usage gets a block comment above its line',
            settings: atLevel('partial'),
            code: 'function a() {\n    return oldCall();\n}',
            output: 'function a() {\n    /* TODO Codemod: Found oldCall. */\n    return oldCall();\n}',
            errors: [{ message: 'Found oldCall.' }],
        },
        {
            name: 'partial: the block comment is not added twice',
            settings: atLevel('partial'),
            code: 'function a() {\n    /* TODO Codemod: Found oldCall. */\n    return oldCall();\n}',
            output: null,
            errors: [{ message: 'Found oldCall.' }],
        },
    ],
});

describe('registry/run-level', () => {
    it('rejects an unknown level', () => {
        const config = {
            files: ['**/*.twig'],
            plugins: { test: { rules: { level: testRule } } },
            languageOptions: { parser: require('vue-eslint-parser') },
            settings: atLevel('all'),
            rules: { 'test/level': 'error' },
        };

        expect(() => new Linter().verify('<template><old-auto /></template>', config, 'test.html.twig')).toThrow(
            'Unknown deprecation run level "all"',
        );
    });
});
