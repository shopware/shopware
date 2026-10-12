/**
 * @sw-package framework
 */

const RuleTester = require('eslint').RuleTester;
const rule = require('../no-deprecated-components');

/**
 * The RuleTester every no-deprecated-components spec runs its cases with: Vue templates parsed
 * by vue-eslint-parser.
 *
 * @example
 * tester.run('no-deprecated-components (valid)', rule, { valid, invalid: [] });
 */
const tester = new RuleTester({
    languageOptions: {
        parser: require('vue-eslint-parser'),
        ecmaVersion: 2015,
    },
});

module.exports = {
    rule,
    tester,
};
