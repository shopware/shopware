/**
 * @package admin
 */

/* eslint-disable max-len */

const getAsyncExpressionName = (node) => {
    // Shopware.Component.build
    if (node.callee?.object?.object?.name === 'Shopware' && node.callee?.object?.property?.name === 'Component' && node.callee?.property?.name === 'build') {
        return 'Shopware.Component.build';
    }

    const asyncFunctions = [
        'setChecked',
        'setData',
        'setMethods',
        'setProps',
        'setSelected',
        'setComputed',
        'setValue',
        'trigger',
        '$nextTick',
    ];

    if (asyncFunctions.includes(node.callee?.property?.name)) {
        return node.callee?.property?.name;
    }

    if (node.callee?.name === 'flushPromises') {
        return 'flushPromises';
    }

    return false;
};

module.exports = {
    meta: {
        type: 'problem',
        fixable: 'code',
    },

    create(context) {
        return {
            CallExpression(node) {
                // Check if the call is awaited
                if (node.parent.type === 'AwaitExpression') {
                    return;
                }

                const asyncExpressionName = getAsyncExpressionName(node);
                if (asyncExpressionName === false) {
                    return;
                }

                context.report({
                    node,
                    message: `${asyncExpressionName} must be awaited`,

                    fix(fixer) {
                        return [
                            fixer.replaceTextRange([node.start, node.start], 'await '),
                        ];
                    },
                });
            },
        };
    },
};
