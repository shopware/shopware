/**
 * @sw-package framework
 */

// `.vm.$emit()` is how Vue Test Utils simulates an event from a child or stub.
// On the mounted `wrapper` it skips the component's own code and only asserts the test's setup.
function isChildEmitCall(vmAccess) {
    const { parent, object } = vmAccess;

    return (
        !(object.type === 'Identifier' && object.name === 'wrapper') &&
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
                if (isChildEmitCall(node)) {
                    return;
                }

                context.report({ node, messageId: 'vmAccess' });
            },
        };
    },
};
