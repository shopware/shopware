/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const { renamed, removed } = require('../twig-blocks-6-8');

/**
 * The RuleTester every twig-blocks-6-8 spec runs its cases with: templates parsed by
 * vue-eslint-parser, at the default run level `safe`.
 *
 * @example
 * tester.run('twig-blocks-6-8 renamed', renamed, { valid, invalid });
 */
const tester = new RuleTester({
    languageOptions: {
        parser: require('vue-eslint-parser'),
        ecmaVersion: 2015,
    },
});

/**
 * Wraps lines in the `<template>` the twig-vue processor puts around a `.html.twig` file.
 *
 * @example
 * template('{% block a %}{% endblock %}'); // '<template>\n{% block a %}{% endblock %}\n</template>'
 */
function template(...lines) {
    return [
        '<template>',
        ...lines,
        '</template>',
    ].join('\n');
}

module.exports = {
    removed,
    renamed,
    template,
    tester,
};
