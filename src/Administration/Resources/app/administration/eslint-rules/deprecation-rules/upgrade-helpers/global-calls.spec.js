/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const { shopwareAliases, memberPath } = require('./global-calls');

const rule = {
    meta: { type: 'problem', schema: [] },
    create(context) {
        let aliases = new Map();

        return {
            Program(node) {
                aliases = shopwareAliases(node);
            },
            'ExpressionStatement > CallExpression'(node) {
                context.report({ node, message: `path:${memberPath(node.callee, aliases)}` });
            },
        };
    },
};

new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } }).run('global-calls', rule, {
    valid: [],
    invalid: [
        {
            name: 'resolves global paths through aliases',
            code: "const { State } = Shopware; const Store = Shopware.Store; State.get('x'); Store.get('y'); Shopware.State.commit('a');",
            errors: [
                { message: 'path:Shopware.State.get' },
                { message: 'path:Shopware.Store.get' },
                { message: 'path:Shopware.State.commit' },
            ],
        },
    ],
});
