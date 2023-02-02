/* eslint-disable max-len */

/**
 * @deprecated tag:v6.5.0 - Will no longer be necessary.
 */
module.exports = {
    create(context) {
        return {
            'NewExpression'(node) {
                if (node.callee.loc.identifierName !== 'Criteria') {
                    return;
                }

                if (node.arguments.length >= 2) {
                    return;
                }

                context.report({
                    node: node.parent,
                    message: 'Please specify the constructor argument of the criteria. The defaults will change with the next major release.',
                });
            },
        };
    },
};
