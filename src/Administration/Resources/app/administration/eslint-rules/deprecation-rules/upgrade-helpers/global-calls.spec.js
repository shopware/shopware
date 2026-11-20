/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const { shopwareAliases, memberPath, serviceCall } = require('./global-calls');

const rule = {
    meta: { type: 'problem', schema: [] },
    create(context) {
        let aliases = new Map();

        return {
            Program(node) {
                aliases = shopwareAliases(node);
            },
            'ExpressionStatement > CallExpression'(node) {
                const call = serviceCall(node, aliases);

                context.report({ node, message: call ? `service:${call.service}.${call.method}` : `path:${memberPath(node.callee, aliases)}` });
            },
        };
    },
};

new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } }).run('global-calls', rule, {
    valid: [],
    invalid: [
        {
            name: 'resolves services on this, Shopware.Service and injected identifiers',
            code: [
                'this.mailService.send();',
                "Shopware.Service('mailService').send();",
                'mailService.send();',
            ].join('\n'),
            errors: [{ message: 'service:mailService.send' }, { message: 'service:mailService.send' }, { message: 'service:mailService.send' }],
        },
        {
            name: 'resolves destructured Service',
            code: "const { Service } = Shopware; Service('orderService').toggle();",
            errors: [{ message: 'service:orderService.toggle' }],
        },
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
