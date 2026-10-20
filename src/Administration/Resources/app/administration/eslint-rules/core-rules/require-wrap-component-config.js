/**
 * @sw-package framework
 */

const { getComponentDeclaration } = require('./component-options');

module.exports = {
    meta: {
        type: 'suggestion',
        docs: {
            description: 'Require component options to be wrapped, so the Vue rules recognize them as a component',
        },
        schema: [],
        messages: {
            plainObject:
                'Wrap the component options in Shopware.Component.wrapComponentConfig(), so the Vue rules can check them.',
        },
    },

    create(context) {
        return {
            'ExportDefaultDeclaration > ObjectExpression.declaration, CallExpression > ObjectExpression.arguments'(node) {
                const declaration = getComponentDeclaration(node);

                if (declaration === 'export' || declaration === 'factory') {
                    context.report({ node, messageId: 'plainObject' });
                }
            },
        };
    },
};
