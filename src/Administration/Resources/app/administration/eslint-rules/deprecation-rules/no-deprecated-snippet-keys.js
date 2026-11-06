/**
 * @sw-package framework
 */

const { loadRegistry } = require('./registry/load-registry');
const { selectedUsages } = require('./registry/filter-migrations');
const { appendRegistryContext } = require('./registry/format-migration-message');
const { withRunLevel } = require('./registry/run-level');

function buildMessage(migration, usage, snippetKey) {
    if (usage.kind === 'rename-snippet-key') {
        return appendRegistryContext([`The Administration snippet key "${snippetKey}" is renamed to "${usage.to}".`], migration);
    }

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

    return [...text.matchAll(mustachePattern)].flatMap((mustache) => {
        const expressionStart = mustache.index + 2;

        return [...mustache[1].matchAll(stringLiteralPattern)].map((literal) => ({
            value: literal[2],
            quote: literal[1],
            // Offsets of the literal, quotes included, relative to the text
            range: [expressionStart + literal.index, expressionStart + literal.index + literal[0].length],
        }));
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
            // A renamed key only covers itself, a removed key also its children
            return value === usage.from || (usage.kind === 'remove-snippet-key' && value.startsWith(`${usage.from}.`));
        }) ?? null
    );
}

/**
 * This rule reports snippet keys the registry lists as removed, in scripts and in template mustaches.
 *
 * @type {import('eslint').Rule.RuleModule}
 */
module.exports = withRunLevel({
    meta: {
        type: 'problem',
        docs: {
            description: 'No usage of removed Administration snippet keys',
            recommended: true,
        },
        fixable: 'code',
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

            const isRename = match.usage.kind === 'rename-snippet-key';

            context.report({
                node,
                message: buildMessage(match.migration, match.usage, value),
                fix: isRename && node.type === 'Literal'
                    ? (fixer) => {
                        const quote = context.sourceCode.getText(node)[0];

                        return fixer.replaceText(node, `${quote}${match.usage.to}${quote}`);
                    }
                    : null,
            });
        }

        function checkTemplateText(node) {
            // The raw text, because the parsed value decodes HTML entities and shifts the offsets
            const raw = context.sourceCode.text.slice(node.range[0], node.range[1]);

            findMustacheStringLiterals(raw).forEach(({ value, quote, range }) => {
                const match = findDeprecatedSnippet(value, usages);

                if (!match) {
                    return;
                }

                const isRename = match.usage.kind === 'rename-snippet-key';
                const absolute = [node.range[0] + range[0], node.range[0] + range[1]];

                context.report({
                    node,
                    message: buildMessage(match.migration, match.usage, value),
                    fix: isRename ? (fixer) => fixer.replaceTextRange(absolute, `${quote}${match.usage.to}${quote}`) : null,
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
});
