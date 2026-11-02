/**
 * @sw-package framework
 */

function isMixinObject(node) {
    return (node.type === 'Identifier' && node.name === 'Mixin') ||
        (node.type === 'MemberExpression' &&
            node.property.name === 'Mixin' &&
            node.object.type === 'Identifier' &&
            node.object.name === 'Shopware');
}

module.exports = {
    meta: {
        type: 'problem',
        docs: {
            description: 'Disallow registering new mixins',
        },
        schema: [],
        messages: {
            noNewMixins: 'Do not register new mixins. Share the logic as a composable in src/app/composables instead.',
        },
    },
    create(context) {
        return {
            CallExpression(node) {
                const { callee } = node;
                if (callee.type === 'MemberExpression' &&
                    callee.property.name === 'register' &&
                    isMixinObject(callee.object)) {
                    context.report({ node, messageId: 'noNewMixins' });
                }
            },
        };
    },
};
