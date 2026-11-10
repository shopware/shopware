/**
 * @sw-package framework
 *
 * Upgrade codemod `snippet-keys`: replaces Administration snippet keys that are gone in 6.8 by their new key, in any
 * string that is exactly the key: the first argument of `$t()`/`$tc()`, column labels, static attributes and the
 * strings inside `{{ … }}` of `.html.twig` templates. Keys without replacement get a codemod comment.
 * The data is in `no-removed-snippet-keys.data.js`.
 */

const { reportTodo } = require('./upgrade-helpers/todo-comment');
const { templateAnchor } = require('./upgrade-helpers/create-todo-rule');
const { defineStringVisitors } = require('./utils/string-literals');
const { locOf } = require('./upgrade-helpers/twig-interpolations');
const { renamed: RENAMED, removed: REMOVED } = require('./no-removed-snippet-keys.data');

const ID = 'snippet-keys';
const REMOVED_KEYS = new Set(REMOVED);

/** @type {import('eslint').Rule.RuleModule} */
module.exports = {
    meta: {
        type: 'problem',
        fixable: 'code',
        docs: {
            description: 'Replace Administration snippet keys that are gone in 6.8',
        },
        schema: [],
    },

    /** @param {import('eslint').Rule.RuleContext} context */
    create(context) {
        const sourceCode = context.sourceCode;
        const seen = new Set();

        return defineStringVisitors(context, (node, value, scope) => {
            // Only a template literal without expressions is exactly a key
            if (node.type === 'TemplateElement' && node.parent?.expressions?.length > 0) {
                return;
            }

            if (REMOVED_KEYS.has(value)) {
                reportTodo(context, {
                    id: ID,
                    message: `Snippet key ${value} is removed without replacement.`,
                    range: (scope === 'template' ? templateAnchor(node) : node).range,
                    scope,
                    seen,
                });

                return;
            }

            if (!Object.hasOwn(RENAMED, value)) {
                return;
            }

            const start = node.range[0] + sourceCode.text.slice(node.range[0], node.range[1]).indexOf(value);

            context.report({
                loc: locOf(sourceCode, node),
                message: `Snippet key ${value} is removed. Use ${RENAMED[value]} instead.`,
                fix: (fixer) => fixer.replaceTextRange([start, start + value.length], RENAMED[value]),
            });
        });
    },
};
