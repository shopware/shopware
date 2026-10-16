/**
 * @sw-package framework
 */

const { findProperty, getComponentDeclaration } = require('./component-options');

const DEFAULT_MAX = 50;
const MEMBER_GROUPS = [
    'methods',
    'computed',
    'watch',
];

function countDataKeys(dataProperty) {
    const data = dataProperty.value;

    if (data.type === 'ObjectExpression') {
        return data.properties.length;
    }

    if (!['FunctionExpression', 'ArrowFunctionExpression'].includes(data.type)) {
        return 0;
    }

    if (data.body.type === 'ObjectExpression') {
        return data.body.properties.length;
    }

    const returnStatement = data.body.body?.find((statement) => {
        return statement.type === 'ReturnStatement' && statement.argument?.type === 'ObjectExpression';
    });

    return returnStatement ? returnStatement.argument.properties.length : 0;
}

function countMembers(options) {
    const groupMembers = MEMBER_GROUPS.reduce((count, group) => {
        const property = findProperty(options, group);

        return property?.value.type === 'ObjectExpression' ? count + property.value.properties.length : count;
    }, 0);
    const data = findProperty(options, 'data');

    return groupMembers + (data ? countDataKeys(data) : 0);
}

module.exports = {
    meta: {
        type: 'suggestion',
        docs: {
            description: 'Limit the number of methods, computed properties, watchers and data keys of a component',
        },
        schema: [
            {
                type: 'object',
                properties: {
                    max: {
                        type: 'integer',
                        minimum: 0,
                    },
                },
                additionalProperties: false,
            },
        ],
        messages: {
            tooManyMembers:
                'Component has {{count}} methods, computed properties, watchers and data keys. Maximum allowed is {{max}}.',
        },
    },

    create(context) {
        const max = context.options[0]?.max ?? DEFAULT_MAX;

        return {
            'ExportDefaultDeclaration > ObjectExpression.declaration, CallExpression > ObjectExpression.arguments'(node) {
                if (!getComponentDeclaration(node)) {
                    return;
                }

                const count = countMembers(node);

                if (count > max) {
                    context.report({ node, messageId: 'tooManyMembers', data: { count, max } });
                }
            },
        };
    },
};
