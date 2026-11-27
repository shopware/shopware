/**
 * @sw-package framework
 */

const { Linter } = require('eslint');
const { twigInterpolations, locOf } = require('./twig-interpolations');

/** The interpolations of every text node of a template, as ESLint sees them in an `.html.twig` file. */
function collect(code, filename = 'acme.html.twig') {
    const found = [];
    const rule = {
        create: (context) => context.sourceCode.parserServices.defineTemplateBodyVisitor({
            VText(node) {
                twigInterpolations(context.sourceCode, node).forEach((interpolation) => {
                    found.push({ ...interpolation, sourceCode: context.sourceCode });
                });
            },
        }),
    };

    new Linter({ cwd: '/' }).verify(code, [{
        files: ['**/*.twig', '**/*.vue'],
        languageOptions: { parser: require('vue-eslint-parser'), ecmaVersion: 2022 },
        plugins: { test: { rules: { collect: rule } } },
        rules: { 'test/collect': 'error' },
    }], { filename: `/plugin/${filename}` });

    return found;
}

describe('twig-interpolations', () => {
    it('parses {{ … }} in .html.twig templates with ranges in the linted source', () => {
        const code = "<template>\n{% block acme %}\n    <span>{{ dateFilter(item.createdAt, { month: '2-digit' }) }}</span>\n{% endblock %}\n</template>";
        const [interpolation] = collect(code);

        expect(interpolation.sourceCode.getText(interpolation.expression)).toBe("dateFilter(item.createdAt, { month: '2-digit' })");
        expect(interpolation.references.map(({ name }) => name)).toEqual(['dateFilter', 'item']);
        expect(locOf(interpolation.sourceCode, interpolation.expression).start).toEqual({ line: 3, column: 13 });
    });

    it('skips Twig comments and the variables of slot scopes', () => {
        const code = '<template>\n{# {{ hidden }} #}\n<template #column="{ item }">{{ item.name }} {{ label }}</template>\n</template>';
        const found = collect(code);

        expect(found.map(({ sourceCode, expression }) => sourceCode.getText(expression))).toEqual(['item.name', 'label']);
        expect(found.flatMap(({ references }) => references.map(({ name }) => name))).toEqual(['label']);
    });

    it('finds nothing in .vue files, where ESLint parses {{ … }} itself', () => {
        expect(collect('<template><span>{{ label }}</span></template>', 'acme.vue')).toEqual([]);
    });
});
