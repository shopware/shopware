/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('../no-deprecated-template-blocks');

/**
 * The RuleTester every no-deprecated-template-blocks spec runs its cases with: templates parsed by
 * vue-eslint-parser, at the default run level `safe`.
 *
 * @example
 * tester.run('no-deprecated-template-blocks (renames)', rule, { valid, invalid });
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
    rule,
    template,
    tester,
};
