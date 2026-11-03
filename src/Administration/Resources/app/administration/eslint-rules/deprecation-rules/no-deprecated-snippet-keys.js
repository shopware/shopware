/**
 * @sw-package framework
 */

const { loadRegistry } = require('./registry/load-registry');
const { selectedUsages } = require('./registry/filter-migrations');
const { appendRegistryContext } = require('./registry/format-migration-message');

function buildMessage(migration, usage, snippetKey) {
    return appendRegistryContext(
        [`The Administration snippet key "${snippetKey}" uses removed key "${usage.from}".`],
        migration,
    );
}

function getStringValue(node) {
    if (node.type === 'Literal' && typeof node.value === 'string') {
        return node.value;
    }

    if (node.type === 'TemplateElement') {
        return node.value.cooked ?? node.value.raw;
    }

    return null;
}

/**
 * Lists the string literals inside the `{{ … }}` segments of a template text. vue-eslint-parser keeps
 * mustaches of .html.twig templates as raw VText, so their snippet keys never reach a Literal visitor.
 *
 * @example
 * findMustacheStringLiterals("Hi {{ $t('a.b') }}"); // ['a.b']
 */
function findMustacheStringLiterals(text) {
    const mustachePattern = /\{\{([\s\S]*?)}}/g;
    const stringLiteralPattern = /(['"`])((?:\\.|(?!\1).)*)\1/g;

    return [...text.matchAll(mustachePattern)].flatMap(([, expression]) => {
        return [...expression.matchAll(stringLiteralPattern)].map(([, , value]) => value);
    });
}

/**
 * Finds the removed key a snippet key uses: the key itself or one of its parents.
 *
 * @example
 * findDeprecatedSnippet('sw-foo.bar.title', usages); // the usage with from: 'sw-foo.bar'
 */
function findDeprecatedSnippet(value, usages) {
    return (
        usages.find(({ usage }) => {
            return value === usage.from || value.startsWith(`${usage.from}.`);
        }) ?? null
    );
}

/**
 * This rule reports snippet keys the registry lists as removed, in scripts and in template mustaches.
 *
 * @type {import('eslint').Rule.RuleModule}
 */
module.exports = {
    meta: {
        type: 'problem',
        docs: {
            description: 'No usage of removed Administration snippet keys',
            recommended: true,
        },
        schema: [],
    },

    create(context) {
        const usages = selectedUsages(loadRegistry().snippetKeyMigrations);

        function checkNode(node) {
            const value = getStringValue(node);

            if (!value) {
                return;
            }

            const match = findDeprecatedSnippet(value, usages);

            if (!match) {
                return;
            }

            context.report({
                node,
                message: buildMessage(match.migration, match.usage, value),
            });
        }

        function checkTemplateText(node) {
            findMustacheStringLiterals(node.value).forEach((value) => {
                const match = findDeprecatedSnippet(value, usages);

                if (!match) {
                    return;
                }

                context.report({
                    node,
                    message: buildMessage(match.migration, match.usage, value),
                });
            });
        }

        const stringLiteralVisitors = {
            Literal: checkNode,
            TemplateElement: checkNode,
        };
        // Plain .ts files run without vue-eslint-parser, so only templates get the template visitor.
        const defineTemplateBodyVisitor = context.sourceCode.parserServices?.defineTemplateBodyVisitor;

        if (!defineTemplateBodyVisitor) {
            return stringLiteralVisitors;
        }

        return defineTemplateBodyVisitor(
            {
                ...stringLiteralVisitors,
                VText: checkTemplateText,
            },
            stringLiteralVisitors,
        );
    },
};
