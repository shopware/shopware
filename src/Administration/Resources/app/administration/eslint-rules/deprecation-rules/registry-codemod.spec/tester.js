/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('../registry-codemod');
const { withRegistryMessageContext } = require('../test-utils/with-registry-message-context');
const { CODEMODS } = require('../../../scripts/extensionTooling/upgrade-codemods');

/**
 * Runs the registry rules with the options of an `administration:upgrade-extensions` codemod: its migration ids
 * at the `marked` level. Each expected message is the headline of the report, before the registry context.
 *
 * @example
 * runCodemod('mt-tabs', { valid: [...], invalid: [...] });
 */
function runCodemod(id, { valid, invalid }) {
    const { options } = CODEMODS.find((codemod) => codemod.id === id);
    const withOptions = (cases) => cases.map((testCase) => ({ options: [options], ...testCase }));

    new RuleTester({ languageOptions: { parser: require('vue-eslint-parser'), ecmaVersion: 2022 } }).run(
        `upgrade codemod ${id}`,
        rule,
        {
            valid: withOptions(valid),
            // A case that only counts its errors keeps the count
            invalid: withOptions(invalid).map((testCase) => {
                return Array.isArray(testCase.errors) ? withRegistryMessageContext([testCase])[0] : testCase;
            }),
        },
    );
}

/** The review comment `marked` writes above a spot that needs manual work. */
const todo = (text) => `<!-- TODO Codemod: ${text} -->`;

module.exports = {
    runCodemod,
    todo,
};
