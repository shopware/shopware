/**
 * @sw-package framework
 */

const { RUN_LEVELS } = require('./registry/run-level');

const RULES = [
    require('./no-deprecated-components'),
    require('./no-deprecated-component-usage'),
    require('./no-deprecated-template-blocks'),
    require('./no-deprecated-js-api'),
];

/**
 * Merges the visitors of several rules into one; handlers of the same selector run in the given order.
 *
 * @example
 * mergeVisitors([{ Program: a }, { Program: b }]); // { Program: (node) => { a(node); b(node); } }
 */
function mergeVisitors(visitors) {
    return visitors.reduce((merged, visitor) => {
        Object.entries(visitor).forEach(([selector, handler]) => {
            const previous = merged[selector];

            merged[selector] = previous
                ? (...args) => {
                      previous(...args);
                      handler(...args);
                  }
                : handler;
        });

        return merged;
    }, {});
}

/**
 * One codemod of `administration:upgrade-extensions` made of registry migrations: runs the registry rules
 * for the migrations with the given `ids`, at the given run level. The runner keys its config by rule name,
 * so `index.js` registers this rule once per codemod.
 *
 * @example
 * 'sw-deprecation-rules/upgrade-mt-tabs': ['error', { ids: ['component.sw-tabs-conversion'], level: 'marked' }]
 *
 * @type {import('eslint').Rule.RuleModule}
 */
module.exports = {
    meta: {
        type: 'problem',
        fixable: 'code',
        docs: {
            description: 'Run the deprecation registry rules for the migrations of one upgrade codemod',
        },
        schema: [
            {
                type: 'object',
                properties: {
                    ids: {
                        type: 'array',
                        items: {
                            type: 'string',
                        },
                    },
                    level: {
                        enum: RUN_LEVELS,
                    },
                },
                additionalProperties: false,
            },
        ],
    },

    create(context) {
        return mergeVisitors(RULES.map((rule) => rule.create(context)));
    },
};
