/**
 * @sw-package framework
 */

const { builtinRules } = require('eslint/use-at-your-own-risk');
const { getComponentDeclaration, getPropertyName } = require('./component-options');

const coreRule = builtinRules.get('max-lines-per-function');

function isComponentSetup(reportedNode) {
    // The core rule reports the property for `setup() {}` and the function for `setup: () => {}`
    const property = reportedNode.type === 'Property' ? reportedNode : reportedNode.parent;

    return (
        property?.type === 'Property' &&
        getPropertyName(property) === 'setup' &&
        getComponentDeclaration(property.parent) !== null
    );
}

// Runs the core rule with other options and only lets through the reports `accept` keeps
function runCoreRule(context, options, accept) {
    const scopedContext = Object.create(context, {
        options: { value: [options] },
        report: {
            value: (descriptor) => {
                if (accept(descriptor.node)) {
                    context.report(descriptor);
                }
            },
        },
    });

    return coreRule.create(scopedContext);
}

/**
 * `max-lines-per-function` with a separate limit for the `setup()` of a component, which wires all of its
 * composables in one function, where the Options API spreads the same logic over many small ones.
 */
module.exports = {
    meta: {
        type: 'suggestion',
        docs: {
            description: 'Limit the lines of a function, with a separate limit for the setup() of a component',
        },
        schema: [
            {
                type: 'object',
                properties: {
                    max: { type: 'integer', minimum: 0 },
                    setupMax: { type: 'integer', minimum: 0 },
                    skipBlankLines: { type: 'boolean' },
                    skipComments: { type: 'boolean' },
                },
                additionalProperties: false,
            },
        ],
        messages: coreRule.meta.messages,
    },

    create(context) {
        const { max = 50, setupMax = max, ...countOptions } = context.options[0] ?? {};

        const functionHandlers = runCoreRule(context, { ...countOptions, max }, (node) => !isComponentSetup(node));
        const setupHandlers = runCoreRule(context, { ...countOptions, max: setupMax }, isComponentSetup);

        return Object.fromEntries(
            Object.keys(functionHandlers).map((selector) => [
                selector,
                (node) => {
                    functionHandlers[selector](node);
                    setupHandlers[selector](node);
                },
            ]),
        );
    },
};
