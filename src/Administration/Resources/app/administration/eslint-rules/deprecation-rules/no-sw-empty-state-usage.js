/**
 * @sw-package framework
 */

/**
 * @deprecated tag:v6.9.0 - Will be removed. The deprecation registry migrates sw-empty-state through
 * `no-deprecated-components` and `no-deprecated-component-usage`, which the extension tooling enables.
 * This rule reports nothing and stays only so ESLint configs that name it keep loading.
 *
 * @type {import('eslint').Rule.RuleModule}
 */
module.exports = {
    meta: {
        type: 'problem',
        deprecated: true,
        replacedBy: [
            'sw-deprecation-rules/no-deprecated-components',
            'sw-deprecation-rules/no-deprecated-component-usage',
        ],
        docs: {
            description: 'Migrated sw-empty-state to mt-empty-state; replaced by the registry rules',
        },
        schema: [],
    },

    create() {
        return {};
    },
};
