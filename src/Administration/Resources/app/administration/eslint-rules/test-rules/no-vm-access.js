/**
 * @sw-package framework
 */

// `.vm.$emit()` is how Vue Test Utils simulates an event from a child or stub
function isEmitCall(vmAccess) {
    const { parent } = vmAccess;

    return (
        parent.type === 'MemberExpression' &&
        parent.object === vmAccess &&
        !parent.computed &&
        parent.property.name === '$emit'
    );
}

module.exports = {
    meta: {
        type: 'problem',
        docs: {
            description: 'Disallow reaching into component instances through `.vm` in tests',
        },
        schema: [],
        messages: {
            vmAccess: 'Test what the user sees. `.vm` reaches into component internals.',
        },
    },

    create(context) {
        return {
            "MemberExpression[computed=false][property.name='vm']"(node) {
                if (isEmitCall(node)) {
                    return;
                }

                context.report({ node, messageId: 'vmAccess' });
            },
        };
    },
};
