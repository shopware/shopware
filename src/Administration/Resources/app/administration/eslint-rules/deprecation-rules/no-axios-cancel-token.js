/**
 * @sw-package framework
 *
 * Upgrade codemod `axios-v1`: 6.8 sends direct requests of the Administration HTTP client with axios v1. The rule marks
 * `CancelToken` (on any object, destructured or imported), `cancelToken` request options, `isCancel`/`Cancel` taken
 * from axios, `useAxiosV1: false`, the version-specific client fields `axiosV0`, `axiosV1`, `interceptorsV0`,
 * `interceptorsV1`, `defaultsV0` and `defaultsV1`.
 * The texts are in `no-axios-cancel-token.data.js`.
 */

const { createTodoRule } = require('./upgrade-helpers/create-todo-rule');
const data = require('./no-axios-cancel-token.data');

function keyName(node) {
    if (node.computed) {
        return null;
    }

    if (node.type === 'MemberExpression') {
        return node.property.type === 'Identifier' ? node.property.name : null;
    }

    return node.key.type === 'Identifier' ? node.key.name : (node.key.value ?? null);
}

module.exports = createTodoRule({
    ...data,
    visitors: (context, api) => ({
        MemberExpression(node) {
            const name = keyName(node);

            if (name === 'CancelToken') {
                api.todo(node, data.cancelTokenMessage);
            } else if (Object.hasOwn(data.internalFields, name ?? '')) {
                api.todo(node, data.internalFieldMessage(name));
            }
        },
        Property(node) {
            const name = keyName(node);

            if (node.parent.type === 'ObjectPattern') {
                // `const { CancelToken } = Axios`
                if (name === 'CancelToken') {
                    api.todo(node, data.cancelTokenMessage);
                }

                return;
            }

            if (name === 'cancelToken') {
                api.todo(node, data.cancelTokenMessage);
            } else if (name === 'useAxiosV1' && node.value.type === 'Literal' && node.value.value === false) {
                api.todo(node, data.useAxiosV0Message);
            }
        },
        ImportSpecifier(node) {
            if (!data.axiosSources.includes(node.parent.source.value)) {
                return;
            }

            const imported = node.imported.name ?? node.imported.value;

            if (imported === 'CancelToken') {
                api.todo(node.parent, data.cancelTokenMessage);
            } else if (imported === 'isCancel' || imported === 'Cancel') {
                api.todo(node.parent, data.isCancelMessage);
            }
        },
        CallExpression(node) {
            const callee = node.callee;

            if (
                callee.type === 'MemberExpression' &&
                keyName(callee) === 'isCancel' &&
                callee.object.type === 'Identifier' &&
                data.axiosObjects.includes(callee.object.name)
            ) {
                api.todo(node, data.isCancelMessage);
            }
        },
    }),
});
